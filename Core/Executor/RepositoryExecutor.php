<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\LanguageAwareInterface;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

use \eZ\Publish\API\Repository\Repository;

/**
 * The core manager class that all migration action managers inherit from.
 */
abstract class RepositoryExecutor extends AbstractExecutor implements LanguageAwareInterface
{
    /**
     * Constant defining the default language code
     */
    const DEFAULT_LANGUAGE_CODE = 'eng-GB';

    /**
     * Constant defining the default Admin user ID.
     *
     * @todo inject via config parameter
     */
    const ADMIN_USER_ID = 14;

    /** @todo inject via config parameter */
    const USER_CONTENT_TYPE = 'user';

    /**
     * @var array $dsl The parsed DSL instruction array
     */
    protected $dsl;

    /** @var array $context The context (configuration) for the execution of the current step */
    protected $context;

    /**
     * The eZ Publish 5 API repository.
     *
     * @var \eZ\Publish\API\Repository\Repository
     */
    protected $repository;

    /**
     * Language code for current step.
     *
     * @var string
     */
    private $languageCode;

    /**
     * @var string
     */
    private $defaultLanguageCode;

    /**
     * The bundle object representing the bundle the currently processed migration is in.
     *
     * @var BundleInterface
     */
    protected $bundle;

    /** @var ReferenceResolverInterface $referenceResolver */
    protected $referenceResolver;

    // to redefine in subclasses if they don't support all methods, or if they support more...
    protected $supportedActions = array(
        'create', 'update', 'delete'
    );

    public function setRepository(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function setReferenceResolver(ReferenceResolverInterface $referenceResolver)
    {
        $this->referenceResolver = $referenceResolver;
    }

    public function execute(MigrationStep $step)
    {
        // base checks
        parent::execute($step);

        if (!isset($step->dsl['mode'])) {
            throw new \Exception("Invalid step definition: missing 'mode'");
        }

        $action = $step->dsl['mode'];

        if (!in_array($action, $this->supportedActions)) {
            throw new \Exception("Invalid step definition: value '$action' is not allowed for 'mode'");
        }

        $this->dsl = $step->dsl;
        $this->context = $step->context;
        if (isset($this->dsl['lang'])) {
            $this->setLanguageCode($this->dsl['lang']);
        }

        if (method_exists($this, $action)) {

            $previousUserId = $this->loginUser(self::ADMIN_USER_ID);
            try {
                $output = $this->$action();
            } catch (\Exception $e) {
                $this->loginUser($previousUserId);
                throw $e;
            }

            // reset the environment as much as possible as we had found it before the migration
            $this->loginUser($previousUserId);

            return $output;
        } else {
            throw new \Exception("Invalid step definition: value '$action' is not a method of " . get_class($this));
        }
    }

    /**
     * Method that each executor (subclass) has to implement.
     *
     * It is used to set references based on the DSL instructions executed in the current step, for later steps to reuse.
     *
     * @throws \InvalidArgumentException when trying to set a reference to an unsupported attribute.
     * @param $object
     * @return boolean
     */
    abstract protected function setReferences($object);

    /**
     * Courtesy function for subclasses
     * @param $key
     * @return mixed
     */
    protected function resolveReferences($key) {
        if ($this->referenceResolver->isReference($key)) {
            return $this->referenceResolver->getReferenceValue($key);
        }
        return $key;
    }

    /**
     * Helper method to log in a user that can make changes to the system.
     * @param int $userId
     * @return int id of the previously logged in user
     */
    protected function loginUser($userId)
    {
        $previousUser = $this->repository->getCurrentUser();

        if ($userId != $previousUser->id) {
            $this->repository->setCurrentUser($this->repository->getUserService()->loadUser($userId));
        }

        return $previousUser->id;
    }

    public function setLanguageCode($languageCode)
    {
        $this->languageCode = $languageCode;
    }

    public function getLanguageCode()
    {
        return $this->languageCode ?: $this->getDefaultLanguageCode();
    }

    public function setDefaultLanguageCode($languageCode)
    {
        $this->defaultLanguageCode = $languageCode;
    }

    public function getDefaultLanguageCode()
    {
        return $this->defaultLanguageCode ?: self::DEFAULT_LANGUAGE_CODE;
    }
}
