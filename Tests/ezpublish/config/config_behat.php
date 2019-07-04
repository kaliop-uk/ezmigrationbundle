<?php

// Used to set parameters based on env vars, regardless of the SF version in use

$container->setParameter('database_host', getenv('MYSQL_HOST'));
$container->setParameter('database_user', getenv('MYSQL_USER'));
$container->setParameter('database_password', getenv('MYSQL_PASSWORD'));
$container->setParameter('database_name', getenv('MYSQL_DATABASE'));
