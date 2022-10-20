<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

/**
 * Used to turn regexp-based matchers (which do match whole strings) into embedded-matchers (used to match parts of strings).
 * Implements EmbeddedReferenceResolverInterface - but in php Traits can not implement interfaces...
 */
trait EmbeddedRegexpReferenceResolverTrait
{
    protected $beginToken = '[';
    protected $endToken = ']';

    /**
     * @param string $string
     * @return bool true if the given $stringIdentifier contains at least one occurrence of the reference(s)
     */
    public function hasEmbeddedReferences($string)
    {
        $regexp = $this->getEmbeddedRegexp();
        return (bool) preg_match_all($regexp, $string, $matches);
    }

    /**
     * Returns the $string with eventual refs resolved
     *
     * @param string $string
     * @return string
     * @todo q: if reference is an array, should we recurse on it ?
     */
    public function resolveEmbeddedReferences($string)
    {
        $regexp = $this->getEmbeddedRegexp();
        $count = preg_match_all($regexp, $string, $matches);
        // $matches[0][] will have the matched full string eg.: [reference:example_reference]
        if ($count) {
            foreach ($matches[0] as $referenceIdentifier) {
                $reference = $this->getReferenceValue(substr($referenceIdentifier, 1, -1));
                if (!is_array($reference)) {
                    $string = str_replace($referenceIdentifier, $reference, $string);
                }
            }
        }

        return $string;
    }

    /**
     * NB: here we assume that all regexp resolvers give us a regexp with a very specific format, notably using '/' as
     * delimiter......
     * @return string
     * @todo make the start and end tokens flexible (it probably wont work well if we use eg. '}}' as end token)
     */
    protected function getEmbeddedRegexp()
    {
        // we need to alter the regexp we usr for std ref resolving, as it will be used to match parts of text, not the whole string
        $regexp = substr($this->getRegexp(), 1, -1);
        return '/' . preg_quote($this->beginToken). preg_replace(array('/^\^/'), array(''), $regexp) . '[^' . $this->endToken . ']+' . preg_quote($this->endToken) . '/';
    }

    /**
     * @param mixed $stringOrArray
     * @return array|string
     * @todo decide what to do with this method...
     */
    /*protected function resolveEmbeddedReferencesRecursively($stringOrArray)
    {
        if (is_array($stringOrArray)) {
            foreach ($stringOrArray as $condition => $values) {
                $stringOrArray[$condition] = $this->resolveEmbeddedReferencesRecursively($values);
            }
            return $stringOrArray;
        } else {
            return $this->resolveEmbeddedReferences($stringOrArray);
        }
    }*/

    /**
     * Has to be implemented in the class which uses this trait
     * @return string
     */
    abstract public function getRegexp();

    /**
     * Has to be implemented in the class which uses this trait
     * @param string $stringIdentifier
     * @return mixed
     */
    abstract public function getReferenceValue($stringIdentifier);
}
