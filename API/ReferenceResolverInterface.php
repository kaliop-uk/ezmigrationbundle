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

    /**
     * Like getReferenceValue, but instead of throwing returns the $stringIdentifier when not a reference
     *
     * In pseudocode: return $this->isReference($stringIdentifier) ? $this->getReferenceValue($stringIdentifier) : $stringIdentifier
     *
     * @param string $stringIdentifier
     * @return mixed $stringIdentifier if not a reference, otherwise the reference vale
     * @throws \Exception if the given Identifier is not a reference
     */
    public function resolveReference($stringIdentifier);
}
