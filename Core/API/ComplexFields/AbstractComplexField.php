<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 11/12/15
 * Time: 12:32
 */

namespace Kaliop\eZMigrationBundle\Core\API\ComplexFields;

use Kaliop\eZMigrationBundle\Interfaces\API\ComplexFieldInterface;
use eZ\Publish\API\Repository\Repository as eZRepository;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Kaliop\eZMigrationBundle\Core\API\Managers\ContentManager;

abstract class AbstractComplexField implements ComplexFieldInterface
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