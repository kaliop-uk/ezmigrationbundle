<?php
/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Kaliop\eZMigrationBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ExpressionLanguageCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('ez_migration_bundle.expression_language')) {
            return;
        }

        $providers = [];
        foreach ($container->findTaggedServiceIds('ez_migration_bundle.expression_language.function_provider') as $serviceId => $tags) {
            $providers[] = new Reference($serviceId);
        }

        $container
            ->getDefinition('ez_migration_bundle.expression_language')
            ->setArguments([null, $providers]);
    }
}
