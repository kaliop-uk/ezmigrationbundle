<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;

interface RegexpBasedResolverInterface extends ReferenceResolverInterface
{
    /**
     * Returns the regexp used to identify if a string is a reference
     * @return string
     */
    public function getRegexp();
}
