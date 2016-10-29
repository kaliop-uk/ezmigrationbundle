<?php

namespace Kaliop\eZMigrationBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class QueryManagerCompilerPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->has('ez_migration_bundle.executor.query_manager') && $container->has('ez_migration_bundle.migration_service')) {
            $queryManagerDefinition = $container->findDefinition('ez_migration_bundle.executor.query_manager');
            $migrationServiceDefinition = $container->findDefinition('ez_migration_bundle.migration_service');

            foreach ($migrationServiceDefinition->getMethodCalls() as $methodCall) {
                if ($methodCall[0] === 'addExecutor') {
                    $queryManagerDefinition->addMethodCall($methodCall[0], $methodCall[1]);
                }
            }
        }
    }
}
