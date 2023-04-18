<?php

// try to load autoloader both when extension is top-level project and when it is installed as part of a working eZPublish
if (!file_exists($file = __DIR__.'/../vendor/autoload.php') && !file_exists($file = __DIR__.'/../../../../vendor/autoload.php')) {
    throw new \RuntimeException('Install the dependencies to run the test suite.');
}

$loader = require $file;

/// @todo if the kernel is booted first with this variable set to false, by eg. running a cli command instead of phpunit,
///       then it will be cached with the test config not loaded, which will make tests fail. How to prevent that?
Kaliop\eZMigrationBundle\DependencyInjection\EzMigrationExtension::$loadTestConfig = true;
