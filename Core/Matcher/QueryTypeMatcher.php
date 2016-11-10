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
     * @var ReferenceResolverInterface
     */
    private $referenceResolver;

    /**
     * QueryTypeMatcher constructor.
     * @param Repository $repository
     * @param \eZ\Publish\Core\QueryType\QueryTypeRegistry $queryTypeRegistry (can't be typehinted before ezpublish-kernel 6.4)
     * @param ExpressionLanguage $expressionLanguage
     */
    public function __construct(Repository $repository, $queryTypeRegistry, ReferenceResolverInterface $referenceResolver)
    {
        parent::__construct($repository);
        $this->queryTypeRegistry = $queryTypeRegistry;
        $this->referenceResolver = $referenceResolver;
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
                if (is_array($parameterValue)) {
                    $parameterValue = array_map(
                        function ($value) {
                            return $this->referenceResolver->resolveReference($value);
                        },
                        $parameterValue
                    );
                } else {
                    $parameterValue = $this->referenceResolver->resolveReference($parameterValue);
                }
                $parameters[$parameterName] = $parameterValue;
            }
        }

        return [$queryType->getQuery($parameters)];
    }
}
