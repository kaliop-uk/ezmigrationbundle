<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;

class ChainRegexpResolver extends ChainResolver implements RegexpBasedResolverInterface
{
    public function addResolver(ReferenceResolverInterface $resolver)
    {
        if (!$resolver instanceof RegexpBasedResolverInterface) {
            throw new MigrationBundleException("Can not add resolver of class " . get_class($resolver) . " to a chain regexp resolver");
        }

        parent::addResolver($resolver);
    }

    /**
     * NB: assumes that all the resolvers we chain use '/' as delimiter...
     * @return string
     */
    public function getRegexp()
    {
        $regexps = array();
        /** @var RegexpBasedResolverInterface $resolver */
        foreach ($this->resolvers as $resolver) {
            $regexp = substr($resolver->getRegexp(), 1, -1);
            if ($regexp !== '') {
                $regexps[] = $regexp;
            }
        }
        return '/(' . implode(')|(', $regexps) . '))/';
    }
}
