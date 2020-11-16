<?php

namespace Kaliop\eZMigrationBundle\Tests\helper;

use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\Core\QueryType\QueryType;

class MigrationTestQueryType implements QueryType
{
    public static function getName()
    {
        return 'kezmbtest_query';
    }

    public function getQuery(array $parameters = [])
    {
        $query = new LocationQuery();
        return $query;
    }

    public function getSupportedParameters()
    {
        return array();
    }
}
