<?php
namespace marioklump\googledrivewebdav;

use Exception;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Sabre\DAV\Client;

class GoogleDriveWebDavBridge
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    private const DEFAULT_CONFIG = [
        'google_drive_credentials_file' => 'credentials.json',
        'google_drive_token_file' => 'token.json',
    ];

    /**
     * @var array
     */
    private $config = [];

    /**
     * @var Google_Service_Drive
     */
    private $googleDriveService;

    /**
     * @var Client
     */
    private $webDavClient;

    /**
     * GoogleDriveWebDavBridge constructor.
     *
     * @param LoggerInterface $logger
     * @param array $config
     * @throws Exception
     */
    public function __construct(LoggerInterface $logger, array $config)
    {
        $this->logger = $logger;
        $this->config = array_merge(static::DEFAULT_CONFIG, $config);

        $this->googleDriveService = $this->getGoogleDriveService();
        $this->webDavClient = $this->getWebDavClient();
    }

    /**
     * Sync all files from the Google Drive storage to the WebDAV storage.
     *
     * @throws Exception
     */
    public function sync(): void
    {
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
                }
            }
        }
    }

    /**
     * Find a directory in the Google Drive storage.
     *
     * @param string $path
     * @param Google_Service_Drive $service
     * @return Google_Service_Drive_DriveFile
     * @throws Exception
     */
    private function findDirectory(string $path, Google_Service_Drive $service): Google_Service_Drive_DriveFile
    {
        $directories = $service->files->listFiles([
            'q' => "trashed = false AND mimeType='application/vnd.google-apps.folder' AND name='{$path}'"
        ]);

        if (empty($directories)) {
            throw new Exception('Unable to find directory ' . $path);
        }

        return $directories->getFiles()[0];
    }

    /**
     * @return Google_Service_Drive_DriveFile|array
     * @throws Exception
     */
    private function getFilesFromGoogleDrive(): array
    {
        $inputDir = $this->findDirectory($this->config['google_drive_dir'], $this->googleDriveService);

        $results = $this->googleDriveService->files->listFiles([
            'q' => "trashed = false AND '{$inputDir->getId()}' IN parents"
        ]);

        return $results->getFiles();
    }

    /**
     * @param Google_Service_Drive_DriveFile $file
     * @return StreamInterface
     */
    private function downloadFileFromGoogleDrive(Google_Service_Drive_DriveFile $file)
    {
        /* @var Response $response */
        $response = $this->googleDriveService->files->get($file->getId(), ['alt' => 'media']);

        return $response->getBody();
    }

    /**
     * Remove the file from Google Drive.
     *
     * @param Google_Service_Drive_DriveFile $file
     */
    private function deleteFileFromGoogleDrive(Google_Service_Drive_DriveFile $file)
    {
        $this->googleDriveService->files->delete($file->getId());
    }

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

    /**
     * Returns an authorized Google API client.
     * @return Google_Client the authorized client object
     * @throws Exception
     * @see https://developers.google.com/drive/api/v3/quickstart/php
     */
    private function getGoogleClient(): Google_Client
    {
        $client = new Google_Client();
        $client->setApplicationName('GoogleDrive-WebDAV-Bridge');
        $client->setScopes(Google_Service_Drive::DRIVE);
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
     * @return Google_Service_Drive
     * @throws Exception
     */
    private function getGoogleDriveService(): Google_Service_Drive
    {
        return new Google_Service_Drive($this->getGoogleClient());
    }

    /**
     * @return Client
     */
    private function getWebDavClient(): Client
    {
        return new Client([
            'baseUri' => $this->config['webdav_url'],
            'userName' => $this->config['webdav_user'],
            'password' => $this->config['webdav_password'],
        ]);
    }
}