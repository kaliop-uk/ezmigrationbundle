<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Repository;
use eZ\Publish\Core\MVC\ConfigResolverInterface;
use Kaliop\eZMigrationBundle\API\Collection\AbstractCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\ReferenceResolverBagInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\Core\RepositoryUserSetterTrait;

/**
 * The core manager class that all migration action managers inherit from.
 */
abstract class RepositoryExecutor extends AbstractExecutor
{
    use RepositoryUserSetterTrait;
    use IgnorableStepExecutorTrait;
    use NonScalarReferenceSetterTrait;

    protected $scalarReferences = array('count');

    /**
     * Constant defining the default language code (used if not specified by the migration or on the command line)
     */
    const DEFAULT_LANGUAGE_CODE = 'eng-GB';

    /**
     * The default Admin user Id, used when no Admin user is specified
     */
    const ADMIN_USER_ID = 14;

    /** Used if not specified by the migration */
    const USER_CONTENT_TYPE = 'user';
    /** Used if not specified by the migration */
    const USERGROUP_CONTENT_TYPE = 'user_group';

    /**
     * The eZ Publish 5 API repository.
     *
     * @var \eZ\Publish\API\Repository\Repository
     */
    protected $repository;

    protected $configResolver;

    /** @var ReferenceResolverBagInterface $referenceResolver */
    protected $referenceResolver;

    // to redefine in subclasses if they don't support all methods, or if they support more...
    protected $supportedActions = array(
        'create', 'update', 'delete'
    );

    public function setRepository(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function setConfigResolver(ConfigResolverInterface $configResolver)
    {
        $this->configResolver = $configResolver;
    }

    public function setReferenceResolver(ReferenceResolverBagInterface $referenceResolver)
    {
        $this->referenceResolver = $referenceResolver;
    }

    public function execute(MigrationStep $step)
    {
        // base checks
        parent::execute($step);

        if (!isset($step->dsl['mode'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: missing 'mode'");
        }

        // q: should we convert snake_case to camelCase ?
        $action = $step->dsl['mode'];

        if (!in_array($action, $this->supportedActions)) {
            throw new InvalidStepDefinitionException("Invalid step definition: value '$action' is not allowed for 'mode'");
        }

        if (!method_exists($this, $action)) {
            throw new InvalidStepDefinitionException("Invalid step definition: value '$action' is not a method of " . get_class($this));
        }

        $this->skipStepIfNeeded($step);

        $previousUserId = $this->loginUser($this->getAdminUserIdentifierFromContext($step->context));

        try {
            $output = $this->$action($step);
        } catch (\Exception $e) {
            $this->loginUser($previousUserId);
            throw $e;
        }

        // reset the environment as much as possible as we had found it before the migration
        $this->loginUser($previousUserId);

        return $output;
    }

    /**
     * Method that each executor (subclass) has to implement.
     *
     * It is used to get values for references based on the DSL instructions executed in the current step, for later steps to reuse.
     *
     * @throws \InvalidArgumentException when trying to set a reference to an unsupported attribute.
     * @param mixed $object a single element to extract reference values from
     * @param array $referencesDefinitions the definitions of the references to extract
     * @param MigrationStep $step
     * @return array key: the reference name (taken from $referencesDefinitions[n]['identifier'], value: the ref. value
     */
    abstract protected function getReferencesValues($object, array $referencesDefinitions, $step);

    /**
     * @param MigrationStep $step
     * @return string
     */
    protected function getLanguageCode($step)
    {
        return isset($step->dsl['lang']) ? $step->dsl['lang'] : $this->getLanguageCodeFromContext($step->context);
    }

    /**
     * @param array|null $context
     * @return string
     */
    protected function getLanguageCodeFromContext($context)
    {
        if (is_array($context) && isset($context['defaultLanguageCode'])) {
            return $context['defaultLanguageCode'];
        }

        if ($this->configResolver) {
            $locales = $this->configResolver->getParameter('languages');
            return reset($locales);
        }

        return self::DEFAULT_LANGUAGE_CODE;
    }

    /**
     * @param MigrationStep $step
     * @return string
     */
    protected function getUserContentType($step)
    {
        return isset($step->dsl['user_content_type']) ? $this->referenceResolver->resolveReference($step->dsl['user_content_type']) : $this->getUserContentTypeFromContext($step->context);
    }

    /**
     * @param MigrationStep $step
     * @return string
     */
    protected function getUserGroupContentType($step)
    {
        return isset($step->dsl['usergroup_content_type']) ? $this->referenceResolver->resolveReference($step->dsl['usergroup_content_type']) : $this->getUserGroupContentTypeFromContext($step->context);
    }

    /**
     * @param array $context
     * @return string
     */
    protected function getUserContentTypeFromContext($context)
    {
        return isset($context['userContentType']) ? $context['userContentType'] : self::USER_CONTENT_TYPE;
    }

    /**
     * @param array $context
     * @return string
     */
    protected function getUserGroupContentTypeFromContext($context)
    {
        return isset($context['userGroupContentType']) ? $context['userGroupContentType'] : self::USERGROUP_CONTENT_TYPE;
    }

    /**
     * @param array $context we have to return FALSE if that is set as adminUserLogin, whereas if NULL is set, we return the default admin
     * @return int|string|false
     */
    protected function getAdminUserIdentifierFromContext($context)
    {
        if (isset($context['adminUserLogin'])) {
            return $context['adminUserLogin'];
        }

        return self::ADMIN_USER_ID;
    }

    /**
     * Sets references to certain attributes of the items returned by steps.
     *
     * @param \Object|AbstractCollection $item
     * @param MigrationStep $step
     * @return boolean
     * @throws \InvalidArgumentException When trying to set a reference to an unsupported attribute
     * @todo should we allow to be passed in plain arrays, ArrayIterators and ObjectIterators as well as Collections?
     */
    protected function setReferences($item, $step)
    {
        if (!array_key_exists('references', $step->dsl)) {
            return false;
        }

        $referencesDefs = $this->setScalarReferences($item, $step->dsl['references']);

        // this check is now done immediately after matching
        //$this->insureResultsCountCompatibility($item, $referencesDefs, $step);

        $multivalued = ($this->expectedResultsType($step) == self::$RESULT_TYPE_MULTIPLE);

        if ($item instanceof AbstractCollection  || is_array($item)) {
            $items = $item;
        } else {
            $items = array($item);
        }

        $referencesValues = array();
        foreach ($items as $item) {
            $itemReferencesValues = $this->getReferencesValues($item, $referencesDefs, $step);
            if (!$multivalued) {
                $referencesValues = $itemReferencesValues;
            } else {
                foreach ($itemReferencesValues as $refName => $refValue) {
                    if (!isset($referencesValues[$refName])) {
                        $referencesValues[$refName] = array($refValue);
                    } else {
                        $referencesValues[$refName][] = $refValue;
                    }
                }
            }
        }

        foreach ($referencesDefs as $reference) {
            $overwrite = false;
            if (isset($reference['overwrite'])) {
                $overwrite = $reference['overwrite'];
            }
            // q: is the usage of count() and array() correct here ? esp for the case we want scalar refs ?
            $this->referenceResolver->addReference($reference['identifier'], count($referencesValues) ? $referencesValues[$reference['identifier']] : array(), $overwrite);
        }

        return true;
    }

    /**
     * @param mixed $entity
     * @param array $referencesDefinition
     * @return array the same as $referencesDefinition, with the references already treated having been removed
     * @throws InvalidStepDefinitionException
     */
    protected function setScalarReferences($entity, $referencesDefinition)
    {
        // allow setting *some* refs even when we have 0 or N matches
        foreach ($referencesDefinition as $key => $reference) {
            $reference = $this->parseReferenceDefinition($key, $reference);
            switch($reference['attribute']) {

                case 'count':
                    $value = count($entity);
                    $overwrite = false;
                    if (isset($reference['overwrite'])) {
                        $overwrite = $reference['overwrite'];
                    }
                    $this->referenceResolver->addReference($reference['identifier'], $value, $overwrite);
                    unset($referencesDefinition[$key]);
                    break;

                default:
                    // do nothing
            }
        }

        return $referencesDefinition;
    }

    /**
     * Verifies compatibility between the definition of the references to be set and the data set to extract them from,
     * and returns a single entity
     *
     * @param AbstractCollection|mixed $entity
     * @param array $referencesDefinition
     * @param MigrationStep $step
     * @return AbstractCollection|mixed
     * @deprecated use validateResultsCount instead
     */
    /*protected function insureSingleEntity($entity, $referencesDefinition, $step)
    {
        $this->insureResultsCountCompatibility($entity, $referencesDefinition, $step);

        if ($entity instanceof AbstractCollection) {
            return $entity->reset();
        }

        return $entity;
    }*/

    /**
     * Courtesy code to avoid reimplementing it in every subclass
     * @todo will be moved into the reference resolver classes
     */
    protected function resolveReferencesRecursively($match)
    {
        if (is_array($match)) {
            foreach ($match as $condition => $values) {
                $match[$condition] = $this->resolveReferencesRecursively($values);
            }
            return $match;
        } else {
            return $this->referenceResolver->resolveReference($match);
        }
    }

    /**
     * @param array $referenceDefinition
     * @return bool
     */
    protected function isScalarReference($referenceDefinition)
    {
        return in_array($referenceDefinition['attribute'], $this->scalarReferences);
    }
}
