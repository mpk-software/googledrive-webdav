# GoogleDrive-WebDAV-Sync
A CLI script for moving files from Google Drive to a WebDAV storage.

## Restrictions
This script is only able to move the files of **one** Google Drive folder to a defined folder of a WebDAV storage. The directory structure on the Google Drive's side isn't recreated on the WebDAV storage's side.

## Use cases
The [ScanSnap ix500](https://www.fujitsu.com/global/products/computing/peripheral/scanners/scansnap/ix500/) is a great document scanner. It's feature [ScanSnap Cloud](https://www.fujitsu.com/global/products/computing/peripheral/scanners/scansnap/software/sscloud/) allows the user to scan the documents directly to many cloud services. Unfortunately, the scanning to [Nextcloud](https://nextcloud.com/) instances is not supported. Also the usage of the WebDAV protocol is not supported by ScanSnap Cloud.
To use the "Scan to Cloud" feature, the scanner can be configured to scan to Google Drive. Using this script allows move the file from the Google Drive storage to the Nextcloud storage (or any other storage service supporting the WebDAV protocol).

## Installation
Clone the repository:
```sh
git clone https://github.com/mpk-software/googledrive-webdav.git
cd googledrive-webdav
```

Use [composer](https://getcomposer.org/) to install the third-party dependencies:
```sh
composer install
```

Go to [Google Drive's Quickstart](https://developers.google.com/drive/api/v3/quickstart/php#step_1_turn_on_the) guide, click on "Enable the Drive API" and get the credentials for accessing the Google Drive API.
You'll get a file called `credentials.json` which have to be stored in this project's root directory.

Copy the `config.dist.php` file inside this repository to a file named `config.php` and update the configuration file to fit your needs.

Call `./bin/sync` to initialize the connection to the Google Drive API. This command uses the previously stored `credentials.json` file to authenticate with Google Drive's API.
When running this command the first time, it prints an URL which you should open in your web browser to get a verification code. Please type in the verification code to the console's prompt ("Enter verification code: ...").

## Sync
After installing the script and initializing the connection to the Google Drive API, you are able to start the synchronization.
Please call the following command:

```sh
./bin/sync
```

For debugging purposes you are able to set verbosity flags: 
```sh
./bin/sync -vvv
```

It is recommended to run the above command as a cronjob to automatically move all files from your Google Drive to your WebDAV storage.