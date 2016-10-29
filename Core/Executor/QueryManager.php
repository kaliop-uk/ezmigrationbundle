<?php
/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\Content\Search\SearchResult;
use Kaliop\eZMigrationBundle\API\Event\BeforeStepExecutionEvent;
use Kaliop\eZMigrationBundle\API\Event\StepExecutedEvent;
use Kaliop\eZMigrationBundle\API\ExecutorInterface;
use Kaliop\eZMigrationBundle\API\MatcherInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\Core\Matcher\QueryTypeMatcher;
use Kaliop\eZMigrationBundle\Core\MigrationService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class QueryManager extends RepositoryExecutor
{
    protected $supportedStepTypes = ['query'];
    protected $supportedActions = ['content', 'location', 'contentInfo'];

    /**
     * @var MatcherInterface[]
     */
    protected $matchers;

    /**
     * @var \eZ\Publish\API\Repository\SearchService
     */
    protected $searchService;

    /**
     * @var MigrationService
     */
    private $migrationService;

    /**
     * @var ExecutorInterface[]
     */
    private $executors;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    public function __construct(array $matchers, MigrationService $migrationService, EventDispatcherInterface $eventDispatcher)
    {
        foreach ($matchers as $matcher) {
            if (!$matcher instanceof MatcherInterface) {
                throw new \Exception("Invalid matcher type, expected MatcherInterface");
            }
            $this->matchers[] = $matcher;
        }
        $this->migrationService = $migrationService;
        $this->dispatcher = $eventDispatcher;
    }

    public function addExecutor(ExecutorInterface $executor)
    {
        foreach($executor->supportedTypes() as $type) {
            $this->executors[$type] = $executor;
        }
    }

    /**
     * Runs a location query.
     */
    protected function location($dsl)
    {
        $searchResult = $this->getSearchService()->findLocations($this->getQuery($dsl));
        $this->setReferences(['collection' => $dsl['collection'], 'results' => $searchResult]);
        $this->walk($dsl);
    }

    /**
     * Runs a content query.
     */
    protected function content($dsl)
    {
        $searchResult = $this->getSearchService()->findContent($this->getQuery($dsl));
        $this->setReferences(['collection' => $dsl['collection'], 'results' => $searchResult]);
        $this->walk($dsl);
    }

    /**
     * Runs a contentInfo query.
     */
    protected function contentInfo($dsl)
    {
        $searchResult = $this->getSearchService()->findContentInfo($this->getQuery($dsl));
        $this->setReferences(['collection' => $dsl['collection'], 'results' => $searchResult]);
        $this->walk($dsl);
    }

    /**
     * Iterates over the collection, and executes the sub-dsl.
     */
    protected function walk($dsl)
    {
        foreach ($this->getCollection($dsl['collection']) as $collectionItem) {
            $this->referenceResolver->addReference('collection_item_' . $dsl['collection'], $collectionItem, true);

            foreach ($dsl['walk'] as $walkDSL) {
                $step = new MigrationStep($walkDSL['type'], $walkDSL);
                $executor = $this->executors[$step->type];
                $this->dispatcher->dispatch('ez_migration.before_execution', new BeforeStepExecutionEvent($step, $executor));
                $result = $executor->execute($step);
                $this->dispatcher->dispatch('ez_migration.step_executed', new StepExecutedEvent($step, $result));
            }
        }
    }

    protected function getCollection($collection)
    {
        return $this->referenceResolver->getReferenceValue('reference:collection_' . $collection);
    }

    /**
     * Method that each executor (subclass) has to implement.
     *
     * It is used to set references based on the DSL instructions executed in the current step, for later steps to reuse.
     *
     * @throws \InvalidArgumentException when trying to set a reference to an unsupported attribute.
     * @param $searchResult
     * @return boolean
     */
    protected function setReferences($args)
    {
        extract($args);

        if (!isset($collection)) {
            throw new \Exception('Missing collection argument');
        }

        if (!isset($results) || !$results instanceof SearchResult) {
            throw new \Exception('Invalid result type, SearchResult expected');
        }

        $value = array_map(
            function ($searchHit) {
                return $searchHit->valueObject;
            },
            $results->searchHits
        );

        $this->referenceResolver->addReference('collection_' . $collection, $value, true);
    }

    protected function getQuery($dsl)
    {
        foreach ($this->matchers as $matcher) {
            $result = $matcher->match($dsl['match']);
            if (count($result) == 1) {
                return $result[0];
            }
        }

        throw new \Exception("No query matched");
    }

    protected function getSearchService()
    {
        return $this->repository->getSearchService();
    }
}
