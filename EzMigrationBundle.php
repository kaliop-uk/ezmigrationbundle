<?php

namespace Kaliop\eZMigrationBundle;

use Kaliop\eZMigrationBundle\DependencyInjection\CompilerPass\TaggedServicesCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EzMigrationBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new TaggedServicesCompilerPass());
    }
}
