<?php

namespace Kaliop\eZMigrationBundle\Core\API\Managers;

use Kaliop\eZMigrationBundle\Core\API\Handler\LocationResolverHandler;
use Kaliop\eZMigrationBundle\Core\API\ReferenceHandler;
use Kaliop\eZMigrationBundle\Core\API\TagHandler;
use Kaliop\eZMigrationBundle\Core\API\LocationRemoteIdHandler;
use Kaliop\eZMigrationBundle\Interfaces\API\ManagerInterface;
use Kaliop\eZMigrationBundle\Interfaces\BundleAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * The core manager class that all migration action managers inherit from.
 */
abstract class AbstractManager implements ManagerInterface, ContainerAwareInterface, BundleAwareInterface
{

    /**
     * Constant defining the default language code
     */
    const DEFAULT_LANGUAGE_CODE = 'eng-GB';

    /**
     * Constant defining the default Admin user ID.
     */
    const ADMIN_USER_ID = 14;

    /**
     * The parsed DSL instruction array
     *
     * @var array
     */
    protected $dsl;

    /**
     * The Symfony2 service container to be used to get the required services
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * The eZ Publish 5 API repository.
     *
     * @var \eZ\Publish\API\Repository\Repository
     */
    protected $repository;

    /**
     * The bundle object representing the bundle the currently processed migration is in.
     *
     * @var BundleInterface
     */
    protected $bundle;

    /**
     * Set the parsed DSL array to be used for processing
     *
     * @param array $dsl
     */
    public function setDSL(array $dsl = array())
    {
        $this->dsl = $dsl;
    }

    /**
     * Set the bundle object.
     * @inheritdoc
     */
    public function setBundle(BundleInterface $bundle = null) {
        $this->bundle = $bundle;
    }

    /**
     * Set the service container and get the eZ Publish 5 API repository.
     * @inheritdoc
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
        $this->repository = $container->get('ezpublish.api.repository');
    }

    /**
     * Main handler method to handle the action based on the mode defined in the DSL
     *
     * @throws \Exception
     */
    public function handle()
    {
        $method = $this->dsl['mode'];

        if ( method_exists( $this, $method ) ) {
            $this->$method();
        } else {
            throw new \Exception('Unknown migration mode');
        }



/*
        switch ($this->dsl['mode']) {
            case 'create':
                $this->create();
                break;
            case 'update':
                $this->update();
                break;
            case 'delete':
                $this->delete();
                break;
            default:
                throw new \Exception('Unknown migration mode');
        }
*/
    }

    /**
     * Checks if a string is a reference identifier or not.
     *
     * @param string $string
     * @return boolean
     */
    public function isReference($string)
    {
        return $this->checkStringForReference($string, ReferenceHandler::REFERENCE_PREFIX);
    }

    /**
     * Checks if a string is a location remote id or not.
     *
     * @param string $string
     * @return boolean
     */
    public function isLocationRemoteId($string)
    {
        return $this->checkStringForReference($string, LocationRemoteIdHandler::REFERENCE_PREFIX);
    }

    /**
     * Checks if a string is a tag remote id or not.
     *
     * @param string $string
     * @return boolean
     */
    public function isTag($string)
    {
        return $this->checkStringForReference($string, TagHandler::REFERENCE_PREFIX);
    }

    protected function checkStringForReference($string, $reference)
    {
        if (!is_string($string)) {
            return false;
        }

        return (strpos($string, $reference) !== false);
    }

    /**
     * Get a referenced value from the handler
     *
     * @param string $identifier
     * @return mixed
     */
    public function getReference($identifier)
    {
        $identifier = $this->stripHandlerReference($identifier, ReferenceHandler::REFERENCE_PREFIX);

        $referenceHandler = ReferenceHandler::instance();

        return $referenceHandler->getReference($identifier);
    }

    public function getTagIdFromKeyword($identifier)
    {
        $identifier = $this->stripHandlerReference($identifier, TagHandler::REFERENCE_PREFIX);

        $tagHandler = TagHandler::instance();

        return $tagHandler->getTagId($identifier, $this->container);
    }

    public function getLocationByRemoteId($identifier)
    {
        $identifier = $this->stripHandlerReference($identifier, LocationRemoteIdHandler::REFERENCE_PREFIX);

        $locationHandler = LocationRemoteIdHandler::instance();

        return $locationHandler->getLocationId($identifier, $this->container);
    }

    protected function stripHandlerReference($string, $reference)
    {
        if (strpos($string, $reference) === 0) {
            $string = substr($string, strlen($reference));
        }

        return $string;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @return LocationResolverHandler
     */
    public function getLocationResolverHandler()
    {
        return $this->container->get('ez_migration_bundle.handler.location_resolver');
    }

    /**
     * Helper method to log in a user that can make changes to the system.
     */
    protected function loginUser()
    {
        // Login as admin to be able to post the content. Any other user who has access to
        // create content would be good as well
        $this->repository->setCurrentUser($this->repository->getUserService()->loadUser(self::ADMIN_USER_ID));
    }

    /**
     * Method that each manager needs to implement.
     *
     * It is used to set references based on the DSL instructions.
     *
     * @throws \InvalidArgumentException When trying to set a reference to an unsupported attribute.
     * @param $object
     * @return boolean
     */
    abstract protected function setReferences($object);
}
