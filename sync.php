#!/usr/bin/env php
<?php

use marioklump\googledrivewebdav\GoogleDriveWebDavBridge;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\NullLogger;

require __DIR__ . '/vendor/autoload.php';

if (php_sapi_name() != 'cli') {
    die('This application must be run on the command line.');
}

$config = require 'config.php';

if ($config['logging_enabled']) {
    $logger = new Logger('name');
    $logger->pushHandler(new StreamHandler('php://stdout'));
} else {
    $logger = new NullLogger();
}

try {
    $bridge = new GoogleDriveWebDavBridge(
        $logger,
        [
            'google_drive_dir' => $config['google_drive_dir'],
            'webdav_dir' => $config['webdav_dir'],
            'webdav_url' => $config['webdav_url'],
            'webdav_user' => $config['webdav_user'],
            'webdav_password' => $config['webdav_password'],
            'remove_files_after_sync' => $config['remove_files_after_sync'],
        ]
    );
    $bridge->sync();
} catch (Exception $e) {
    $logger->error($e->getMessage());
}