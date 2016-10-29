<?php
/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\Core\FieldType\Relation\Value;
use eZ\Publish\Core\QueryType\QueryTypeRegistry;
use eZ\Publish\Core\FieldType;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class QueryTypeMatcher extends AbstractMatcher
{
    /**
     * @var QueryTypeRegistry
     */
    private $queryTypeRegistry;

    /**
     * @var ExpressionLanguage
     */
    private $expressionLanguage;

    public function __construct(Repository $repository, QueryTypeRegistry $queryTypeRegistry, ExpressionLanguage $expressionLanguage)
    {
        parent::__construct($repository);
        $this->queryTypeRegistry = $queryTypeRegistry;
        $this->expressionLanguage = $expressionLanguage;
    }

    public function match(array $conditions)
    {
        if (!isset($conditions['query_type'])) {
            return [];
        }

        $queryType = $this->queryTypeRegistry->getQueryType($conditions['query_type']);

        $parameters = [];
        if (isset($conditions['parameters'])) {
            foreach ($conditions['parameters'] as $parameterName => $parameterValue) {
                if (substr($parameterValue, 0, 2) === '@=') {
                    $parameters[$parameterName] = $this->expressionLanguage->evaluate(
                        substr($parameterValue, 2)
                    );
                } else {
                    $parameters[$parameterName] = $parameterValue;
                }
            }
        }

        return [$queryType->getQuery($parameters)];
    }
}
