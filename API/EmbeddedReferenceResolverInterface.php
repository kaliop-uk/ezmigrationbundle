<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Used to resolve references 'embedded' inside strings (as opposed to references which completely replace strings).
 */
interface EmbeddedReferenceResolverInterface
{
    /**
     * @param string $string
     * @return bool true if the given string contains at least one occurrence of the reference(s)
     */
    public function hasEmbeddedReferences($string);

    /**
     * Returns the string with eventual refs resolved
     *
     * @param string $string
     * @return string
     */
    public function resolveEmbeddedReferences($string);
}
