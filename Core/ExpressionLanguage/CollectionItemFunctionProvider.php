<?php
/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Kaliop\eZMigrationBundle\Core\ExpressionLanguage;

use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * Defines the 'collection_item' function.
 */
class CollectionItemFunctionProvider implements ExpressionFunctionProviderInterface
{
    /**
     * @var ReferenceResolverInterface
     */
    private $referenceResolver;

    public function __construct(ReferenceResolverInterface $referenceResolver)
    {
        $this->referenceResolver = $referenceResolver;
    }

    /**
     * @return ExpressionFunction[] An array of Function instances
     */
    public function getFunctions()
    {
        return [
            new ExpressionFunction(
                'collection_item',
                function() {
                    throw new \Exception('ref function does not support compilation');
                },
                function($foo, $identifier) {
                    return $this->referenceResolver->getReferenceValue('reference:collection_item_' . $identifier);
                }
            ),
        ];
    }
}
