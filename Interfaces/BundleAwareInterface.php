<?php
/**
 * Created by PhpStorm.
 * User: peterh
 * Date: 12/11/2013
 * Time: 10:49
 */

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