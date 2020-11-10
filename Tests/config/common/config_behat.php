<?php

// Used to set parameters based on env vars, regardless of the SF and eZ versions in use

// we rely on Doctrine aliases for this to work
$container->setParameter('database_driver', getenv('DB_TYPE'));
// and on Docker conatienrs configuration for having hostnames that match db types
if (($dbHost = getenv('DB_HOST')) !== false) {
    $container->setParameter('database_host', $dbHost);
} else {
    $container->setParameter('database_host', getenv('DB_TYPE'));
}
$container->setParameter('database_user', getenv('DB_EZ_USER'));
$container->setParameter('database_password', getenv('DB_EZ_PASSWORD'));
$container->setParameter('database_name', getenv('DB_EZ_DATABASE'));
