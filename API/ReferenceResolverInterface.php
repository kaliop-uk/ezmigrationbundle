<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Used to resolve references.
 * References can be variables (or functions) whose value is used to replace completely strings
 */
interface ReferenceResolverInterface
{
    /**
     * @param string|mixed $stringIdentifier
     * @return bool true if the given $stringIdentifier identifies a reference
     */
    public function isReference($stringIdentifier);

    /**
     * @param string $stringIdentifier
     * @return mixed the value of the reference identified by $stringIdentifier
     * @throws \Exception if the given Identifier is not a reference
     */
    public function getReferenceValue($stringIdentifier);

    /**
     * Like getReferenceValue, but instead of throwing returns the $stringIdentifier when not a reference
     *
     * In pseudocode: return $this->isReference($stringIdentifier) ? $this->getReferenceValue($stringIdentifier) : $stringIdentifier
     *
     * @param string|mixed $stringIdentifier
     * @return mixed $stringIdentifier if not a reference, otherwise the reference vale
     * @throws \Exception (when ?)
     */
    public function resolveReference($stringIdentifier);
}
