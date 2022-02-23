<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class ExpressionResolver extends AbstractResolver implements ExpressionFunctionProviderInterface
{
    /**
     * Constant defining the prefix for all reference identifier strings in definitions
     */
    protected $referencePrefixes = array('eval:');

    protected $referenceResolver;

    public function __construct(ReferenceResolverInterface $referenceResolver)
    {
        parent::__construct();

        $this->referenceResolver = $referenceResolver;
    }

    /**
     * @param string $identifier format: 'eval:...'
     * @return mixed
     * @throws \Exception When trying to retrieve an unset reference
     */
    public function getReferenceValue($identifier)
    {
        $identifier = trim($this->getReferenceIdentifier($identifier));

        $expressionLanguage = new ExpressionLanguage(null, array($this));

        $resolver = $this->referenceResolver;

        $expressionLanguage->register(
            'resolve',
            function ($str) {
                /// @todo we could implement this via eg a static class var which holds a pointer to $this->referenceResolver
                //return sprintf('(is_string(%1$s) ? FakerResolver::resolveExpressionLanguageReference(%1$s) : %1$s)', $str);
                return "throw new \Exception('The \'resolve\' expression language operator can not be compiled, only evaluated'";
            },
            function ($arguments, $str) use ($resolver) {
                if (!is_string($str)) {
                    return $str;
                }

                return $resolver->resolveReference($str);
            }
        );

        return $expressionLanguage->evaluate($identifier);
    }

    public function getFunctions()
    {
        return [
            ExpressionFunction::fromPhp('array_merge'),
        ];
    }
}
