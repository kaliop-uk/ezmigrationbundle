<?php

namespace Kaliop\eZMigrationBundle\API\Traits;

use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;

trait FlexibleKeyMatcher
{
    /**
     * Given a key, return the correct array to be used in a Match call
     *
     * @param string $key
     * @return array
     * @throws \Exception if the key can not be positively identified (eg. a string which could be an identifier or a remote_id)
     */
    abstract protected function getConditionsFromKey($key);

    public function matchByKey($key)
    {
        return $this->matchOne($this->getConditionsFromKey($key));
    }
}
