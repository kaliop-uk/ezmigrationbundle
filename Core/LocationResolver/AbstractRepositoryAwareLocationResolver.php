<?php

namespace Kaliop\eZMigrationBundle\Core\LocationResolver;

use eZ\Publish\API\Repository\Repository;

abstract class AbstractRepositoryAwareLocationResolver implements LocationResolverInterface
{
    /**
     * @var Repository
     */
    protected $repository;

    /**
     * AbstractRepositoryAwareLocationResolver constructor.
     *
     * @param Repository $repository
     */
    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }
}
