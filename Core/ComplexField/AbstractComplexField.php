<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use eZ\Publish\API\Repository\Repository as eZRepository;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Kaliop\eZMigrationBundle\Core\Executor\ContentManager;

abstract class AbstractComplexField
{
    /** @var array  */
    var $fieldValueArray;

    /** @var eZRepository  */
    var $repository;

    /** @var BundleInterface  */
    var $bundle;

    /** @var ContainerInterface  */
    var $container;

    /** @var ContentManager  */
    var $contentManager;

    public function __construct(
        array $fieldValueArray,
        eZRepository $repository,
        BundleInterface $bundle,
        ContainerInterface $container,
        ContentManager $contentManager
    )
    {
        $this->fieldValueArray = $fieldValueArray;
        $this->repository = $repository;
        $this->bundle = $bundle;
        $this->container = $container;
        $this->contentManager = $contentManager;
    }
}
