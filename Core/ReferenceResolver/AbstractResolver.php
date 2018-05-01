<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

use Kaliop\eZMigrationBundle\API\EmbeddedReferenceResolverInterface;

abstract class AbstractResolver extends PrefixBasedResolver implements EmbeddedReferenceResolverInterface
{
    use EmbeddedRegexpReferenceResolverTrait;
}
