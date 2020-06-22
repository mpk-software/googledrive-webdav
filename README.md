# googledrive-webdav
A CLI script for moving files from Google Drive to a WebDAV storage

## Installation
Clone the repository:
```sh
git clone https://github.com/marioklump/googledrive-webdav.git
cd googledrive-webdav
```

Use [composer](https://getcomposer.org/) to install the third-party dependencies:
```sh
composer install
```

Go to [Google Drive's Quickstart](https://developers.google.com/drive/api/v3/quickstart/php#step_1_turn_on_the) guide and get the credentials for accessing the Google Drive API.
You'll get a file called `credentials.json` which have to be stored in this project's root directory.

Copy the `config.dist.php` file inside this repository to a file named `config.php`. Fill the missing properties of this configuration file.

Call `./bin/console init` to initialize the connection to the Google Drive API. This command uses the previously stored `credentials.json` file to authenticate with Google Drive's API.
The command prints an URL which you should open to get a verification code. Please type in the verification code to the console's prompt.

## Sync
After installing script and initializing the connection to the Google Drive API, you are able to start the synchronization.
Please call the following command:

```sh
./bin/console sync
```