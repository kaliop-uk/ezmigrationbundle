<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Used to resolve references
 */
interface ReferenceResolverInterface
{
    /**
     * @param string $stringIdentifier
     * @return bool
     */
    public function isReference($stringIdentifier);

    /**
     * @param string $stringIdentifier
     * @return mixed
     * @throws \Exception if the given Identifier is not a reference
     */
    public function getReferenceValue($stringIdentifier);
}
