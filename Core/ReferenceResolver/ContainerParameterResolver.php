<?php
/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

use eZ\Publish\API\Repository\Values\Content\Content;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use eZ\Publish\Core\FieldType;

class ContainerParameterResolver extends AbstractResolver
{
    protected $referencePrefixes = ['%'];

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    /**
     * @param string $stringIdentifier
     * @return mixed
     */
    public function getReferenceValue($stringIdentifier)
    {
        return $this->container->getParameter(trim($stringIdentifier, '%'));
    }

    private function getExpressionLanguage()
    {
        {
            $language = new ExpressionLanguage();
            $language->addFunction(
                new ExpressionFunction(
                    'relatedContentIds',
                    function() {
                        throw new \Exception('ref function does not support compilation');
                    },
                    function($foo, Content $content, $fieldDefinitionIdentifier) {
                        $fieldValue = $content->getFieldValue($fieldDefinitionIdentifier);
                        if ($fieldValue instanceof FieldType\Relation\Value) {
                            return [$fieldValue->destinationContentId];
                        } elseif ($fieldValue instanceof FieldType\RelationList\Value) {
                            return $fieldValue->destinationContentIds;
                        } else {
                            throw new \Exception("Expected a Relation or RelationList field value");
                        }
                    }
                )
            );
            $language->addFunction(
                new ExpressionFunction(
                    'collection_item',
                    function() {
                        throw new \Exception('ref function does not support compilation');
                    },
                    function($foo, $identifier) {
                        return $this->referenceResolver->getReferenceValue('reference:collection_item_' . $identifier);
                    }
                )
            );

            return $language;
        }
    }
}
