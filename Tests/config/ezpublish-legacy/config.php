<?php

// Configuration needed for the legacy kernel to be able to bootstrap autoloading when the eZ5 application configuration
// is not in the root folder. The path to the eZ5 app is relative to the parent folder of the legacy kernel...
define('EZP_APP_FOLDER_NAME', 'ezpublish-community/ezpublish');
