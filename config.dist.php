<?php

return [
    /**
     * The ID of the folder in you Google Drive
     * @see https://ploi.io/documentation/mysql/where-do-i-get-google-drive-folder-id
     */
    'google_drive_folder_id' => '',

    'webdav_url' => '',

    /**
     * The name of the directory  in your WebDAV storage (e.g. "inbox").
     */
    'webdav_dir' => '',

    /**
     * Your WebDAV user. The user has to have write permissions to `webdav_dir`.
     */
    'webdav_user' => '',

    /**
     * The password of you WebDAV user
     */
    'webdav_password' => '',

    /**
     * If enabled, the files are deleted from the Google Drive after syncing them to the WebDAV storage.
     */
    'remove_files_after_sync' => true
];