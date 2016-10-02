<?php

namespace Kaliop\eZMigrationBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class TaggedServicesCompilerPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->has('ez_migration_bundle.migration_service')) {
            $migrationService = $container->findDefinition('ez_migration_bundle.migration_service');

            $DefinitionParsers = $container->findTaggedServiceIds('ez_migration_bundle.definition_parser');
            foreach ($DefinitionParsers as $id => $tags) {
                $migrationService->addMethodCall('addDefinitionParser', array(
                    new Reference($id)
                ));
            }

            $executors = $container->findTaggedServiceIds('ez_migration_bundle.executor');
            foreach ($executors as $id => $tags) {
                $migrationService->addMethodCall('addExecutor', array(
                    new Reference($id)
                ));
            }
        }

        if ($container->has('ez_migration_bundle.complex_field_manager')) {
            $migrationService = $container->findDefinition('ez_migration_bundle.complex_field_manager');

            $DefinitionParsers = $container->findTaggedServiceIds('ez_migration_bundle.complex_field');
            foreach ($DefinitionParsers as $id => $tags) {
                foreach ($tags as $attributes) {
                    $migrationService->addMethodCall('addComplexField', array(
                        new Reference($id),
                        $attributes['fieldtype']
                    ));
                }
            }
        }

        if ($container->has('ez_migration_bundle.handler.location_resolver')) {
            $locationResolverHandler = $container->findDefinition('ez_migration_bundle.handler.location_resolver');
            $locationResolvers = $container->findTaggedServiceIds('ez_migration_bundle.location_resolver');

            foreach ($locationResolvers as $id => $tags) {
                $locationResolverHandler->addMethodCall('addResolver', array(
                    new Reference($id)
                ));
            }
        }

        if ($container->has('ez_migration_bundle.reference_resolver.customreference.flexible')) {
            $customReferenceResolver = $container->findDefinition('ez_migration_bundle.reference_resolver.customreference.flexible');
            $extraResolvers = $container->findTaggedServiceIds('ez_migration_bundle.reference_resolver.customreference');

            foreach ($extraResolvers as $id => $tags) {
                $customReferenceResolver->addMethodCall('addResolver', array(
                    new Reference($id)
                ));
            }
        }
    }
}
