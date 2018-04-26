<?php

namespace Kaliop\eZMigrationBundle\API;

interface ConfigResolverInterface
{
    /**
     * @param string $paramName
     * @param string $scope
     * @return mixed
     * @throws \Exception
     */
    public function getParameter($paramName, $scope = null);
}
