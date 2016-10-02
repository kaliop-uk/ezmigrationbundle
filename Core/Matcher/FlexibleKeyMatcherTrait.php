<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

trait FlexibleKeyMatcherTrait
{
    /**
     * Given a key, return the correct array of conditions to be used in a Match call
     *
     * @param string|int $key
     * @return array
     * @throws \Exception if the key can not be positively identified (eg. a string which could be an identifier or a remote_id)
     */
    abstract protected function getConditionsFromKey($key);

    /**
     * @param string|int|array $key We go overboard with flexibility and accept an array of conditions, besides the single key.
     *                              This allow us to do some funky stuff when users have to specify location ids
     * @return mixed
     */
    public function matchByKey($key)
    {
        return is_array($key) ? $this->matchOne($key) : $this->matchOne($this->getConditionsFromKey($key));
    }
}
