<?php

namespace mpksoftware\googledrivewebdav;

use Exception;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google_Client;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use Psr\Http\Message\StreamInterface;
use Sabre\DAV\Client;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GoogleDriveWebDavSyncCommand extends Command
{
    private Logger $logger;

    private const DEFAULT_CONFIG = [
        'google_drive_credentials_file' => 'credentials.json',
        'google_drive_token_file' => 'token.json',
    ];

    private array $config;

    private Drive $googleDriveService;

    private Client $webDavClient;

    public function __construct(array $config)
    {
        $this->logger = new Logger('googledrive-webdav-sync');
        $this->config = array_merge(static::DEFAULT_CONFIG, $config);

        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('GoogleDrive to WebDAV sync')
            ->setDescription('Moves files from Google Drive to a WebDAV storage');
    }

    /**
     * Sync all files from the Google Drive storage to the WebDAV storage.
     *
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->setHandlers([new ConsoleHandler($output)]);

        try {
            $this->googleDriveService = $this->createGoogleDriveService();
            $this->webDavClient = $this->createWebDavClient();

            $files = $this->getFilesFromGoogleDrive();
            if (empty($files)) {
                $this->logger->info('No files to sync');
            } else {
                $this->logger->info(sprintf('Start syncing %d files' . PHP_EOL, count($files)));

                foreach ($files as $file) {
                    $this->logger->info(sprintf('Syncing file %s' . PHP_EOL, $file->getName()));

                    $fileContent = $this->downloadFileFromGoogleDrive($file);

                    if ($this->putFileToWebDAV($file->getName(), $fileContent)) {
                        $this->logger->info(sprintf('Successfully synced file %s' . PHP_EOL, $file->getName()));

                        // Remove the file from Google Drive
                        if ($this->config['remove_files_after_sync'] === true) {
                            $this->deleteFileFromGoogleDrive($file);
                        }
                    } else {
                        $this->logger->error(sprintf('Unable to store file "%s" to WebDAV storage', $file->getName()));

                        return Command::FAILURE;
                    }
                }
            }

        } catch (Exception $e) {
            $this->logger->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Returns an authorized Google API client.
     *
     * @return Google_Client the authorized client object
     * @throws Exception
     * @see https://developers.google.com/drive/api/v3/quickstart/php
     */
    private function createGoogleClient(): Google_Client
    {
        $client = new Google_Client();
        $client->setApplicationName('GoogleDrive-WebDAV-Bridge');
        $client->setScopes(Drive::DRIVE);
        $client->setAuthConfig($this->config['google_drive_credentials_file']);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        $tokenPath = $this->config['google_drive_token_file'];
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);
        }

        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                // Request authorization from the user.
                $authUrl = $client->createAuthUrl();

                printf("Open the following link in your browser:\n%s\n", $authUrl);
                print('Enter verification code: ');
                $authCode = trim(fgets(STDIN));

                // Exchange authorization code for an access token.
                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);

                // Check to see if there was an error.
                if (array_key_exists('error', $accessToken)) {
                    throw new Exception(join(', ', $accessToken));
                }
            }

            // Save the token to a file.
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }

        return $client;
    }

    /**
     * Create a Google Drive service.
     *
     * @throws Exception
     */
    private function createGoogleDriveService(): Drive
    {
        return new Drive($this->createGoogleClient());
    }

    /**
     * Create a WebDAV client.
     */
    private function createWebDavClient(): Client
    {
        return new Client([
            'baseUri' => $this->config['webdav_url'],
            'userName' => $this->config['webdav_user'],
            'password' => $this->config['webdav_password'],
        ]);
    }

    /**
     * @return DriveFile[]
     * @throws Exception
     */
    private function getFilesFromGoogleDrive(): array
    {
        $results = $this->googleDriveService->files->listFiles([
            'q' => "trashed = false AND '{$this->config['google_drive_folder_id']}' IN parents"
        ]);

        return $results->getFiles();
    }

    private function downloadFileFromGoogleDrive(DriveFile $file): StreamInterface
    {
        /* @var Response $response */
        $response = $this->googleDriveService->files->get($file->getId(), ['alt' => 'media']);

        return $response->getBody();
    }

    /**
     * Remove the file from Google Drive.
     */
    private function deleteFileFromGoogleDrive(DriveFile $file): void
    {
        $this->googleDriveService->files->delete($file->getId());
    }

    /**
     * Store a file to the WebDAV storage.
     *
     * @param string $filename
     * @param string|resource $content
     * @return bool true, if the file was successfully created
     */
    private function putFileToWebDAV(string $filename, $content): bool
    {
        // Move the file to the WebDAV storage
        $response = $this->webDavClient->request(
            'PUT',
            rtrim($this->config['webdav_dir'], '/') . '/' . $filename,
            $content
        );

        return $response['statusCode'] === 201;
    }
}
