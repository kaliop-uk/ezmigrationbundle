<?php

namespace Kaliop\eZMigrationBundle\Core\API;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class LocationRemoteIdHandler
 *
 * Handle references to location remote ID's.
 */
class TagHandler
{
    /**
     * Constant defining the prefix for all reference identifier strings in definitions
     */
    const REFERENCE_PREFIX = 'tag:';

    /**
     * Array of all references set by the currently running migrations.
     *
     * @var array
     */
    private $tags = array();

    /**
     * The instance of the ReferenceHandler
     * @var ReferenceHandler
     */
    private static $instance;

    /**
     * Private constructor as this is a singleton.
     */
    private function __construct()
    {

    }

    /**
     * Get the ReferenceHandler instance
     *
     * @return TagHandler
     */
    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new TagHandler();
        }

        return self::$instance;
    }

    /**
     * @param $identifier
     * @param ContainerInterface $container
     * @return mixed
     */
    public function getTagId($identifier, ContainerInterface $container)
    {
        $translationHelper = $container->get( 'ezpublish.translation_helper' );
        $availableLanguages = $translationHelper->getAvailableLanguages();
        $tagService = $container->get('ezpublish.api.service.tags');

        if ($tagService->getTagsByKeywordCount($identifier, $availableLanguages[0]) > 0) {
            $tags = $tagService->loadTagsByKeyword($identifier, $availableLanguages[0], true, 0, 1);
            return $tags[0]->id;
        }

        throw new \Exception('Supplied tag: ' . $identifier . 'Not found.');
    }
}
