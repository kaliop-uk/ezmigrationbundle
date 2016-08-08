<?php

// try to load autoloade both when extension is top-level project and when it is installed as part of a working eZPUblish
if (!file_exists($file = __DIR__.'/../vendor/autoload.php') && !file_exists($file = __DIR__.'/../../../../vendor/autoload.php')) {
    throw new \RuntimeException('Install the dependencies to run the test suite.');
}

$loader = require $file;
