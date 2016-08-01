<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use Kaliop\eZMigrationBundle\API\ComplexFieldInterface;
use eZ\Publish\API\Repository\Repository as eZRepository;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Kaliop\eZMigrationBundle\Core\Executor\ContentManager;

class ComplexFieldManager
{
    /** @var array  */
    var $classMap;

    /** @var eZRepository  */
    var $repository;

    /** @var ContainerInterface  */
    var $container;

    /**
     * The bundle object representing the bundle the currently processed migration is in.
     *
     * @var BundleInterface
     */
    protected $bundle;


    public function __construct(
        eZRepository $repository,
        ContainerInterface $container,
        array $classMap
    )
    {
        $this->container = $container;
        $this->repository = $repository;
        foreach ($classMap as $contentTypeIdentifier => $className) {
            $this->registerClass($className, $contentTypeIdentifier);
        }

    }

    public function setBundle($bundle)
    {
        $this->bundle = $bundle;
    }

    /**
     * @param array $fieldValueArray
     * @param ContentManager $contentManager
     * @return AbstractComplexField
     */
    public function getComplexField($fieldTypeIdentifier = null, array $fieldValueArray, ContentManager $contentManager)
    {
        if ( $fieldTypeIdentifier != null )
        {
            $type = $fieldTypeIdentifier;
            if ( array_key_exists($type, $this->classMap) )
            {
                /** @var AbstractComplexField $class */
                $class = $this->classMap[$type];

                // Building the class and sending through the services we need.
                return new $class( $fieldValueArray, $this->repository, $this->bundle, $this->container, $contentManager );
            }
            else
            {
                throw new \InvalidArgumentException("Field value type '$type' does not have a complex field class defined.");
            }
        }
        else
        {
            throw new \InvalidArgumentException('Field value array does not have a type defined.');
        }
    }

    /**
     * Registers a php class to be used as wrapper for a given content type
     * @var string $className
     * @var string $contentTypeIdentifier
     * @throws \InvalidArgumentException
     *
     * @todo validate contentTypeIdentifier as well. Reject null identifier and integers (unless 0 is a valid content type identifier...)
     */
    private function registerClass($className, $contentTypeIdentifier)
    {
        if (!is_subclass_of($className, '\Kaliop\eZMigrationBundle\API\ComplexFieldInterface')) {
            throw new \InvalidArgumentException("Class '$className' can not be registered as class because it lacks the necessary interface");
        }
        $this->classMap[$contentTypeIdentifier] = $className;
    }
}
