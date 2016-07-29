<?php

namespace Kaliop\eZMigrationBundle\API;

use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * The BundleAwareInterface should be implemented by classes that require access to a bundle object.
 */
interface BundleAwareInterface
{
    /**
     * Sets the bundle
     * @param BundleInterface $bundle
     * @api
     */
    public function setBundle(BundleInterface $bundle = null);
}
