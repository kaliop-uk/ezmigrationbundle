<?php

namespace Kaliop\Migration\Interfaces;

use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * The BundleAwareInterface should be implemented by classes that require access to a bundle object.
 *
 * @package Kaliop\Migration\Interfaces
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
