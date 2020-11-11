<?php

// Used to set parameters based on env vars, regardless of the SF and eZ versions in use

/// @var ContainerBuilder $container
$container->setParameter('database_driver', 'pdo_' .str_replace(array('postgresql'), array('pgsql'), getenv('DB_TYPE')));
// we rely on Docker containers configuration for having hostnames that match db types
if (getenv('DB_HOST') !== false) {
    $container->setParameter('database_host', getenv('DB_HOST'));
} else {
    $container->setParameter('database_host', getenv('DB_TYPE'));
}
if (getenv('DB_TYPE') == 'postgresql' && getenv('POSTGRESQL_VERSION') !== false) {
    $container->setParameter('database_version', getenv('POSTGRESQL_VERSION'));
} else if (getenv('DB_TYPE') == 'mysql' && getenv('MYSQL_VERSION') !== false) {
    $container->setParameter('database_version', getenv('MYSQL_VERSION'));
}
$container->setParameter('database_user', getenv('DB_EZ_USER'));
$container->setParameter('database_password', getenv('DB_EZ_PASSWORD'));
$container->setParameter('database_name', getenv('DB_EZ_DATABASE'));
