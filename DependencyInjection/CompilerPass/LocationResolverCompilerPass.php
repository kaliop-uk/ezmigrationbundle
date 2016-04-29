<?php

namespace Kaliop\eZMigrationBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class LocationResolverCompilerPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('ez_migration_bundle.handler.location_resolver')) {
            return;
        }

        $locationResolverHandler = $container->findDefinition('ez_migration_bundle.handler.location_resolver');
        $locationResolvers = $container->findTaggedServiceIds('ez_migration_bundle.location_resolver');

        foreach ($locationResolvers as $id => $tags) {
            $locationResolverHandler->addMethodCall('addResolver', array(
                new Reference($id)
            ));
        }
    }
}
