<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;
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

        return $expressionLanguage->evaluate($identifier);
    }

    public function getFunctions()
    {
        $resolver = $this->referenceResolver;

        return array(
            new ExpressionFunction(
                'resolve',
                function ($str) {
                    /// @todo we could implement this via eg a static class var which holds a pointer to $this->referenceResolver
                    //return sprintf('(is_string(%1$s) ? FakerResolver::resolveExpressionLanguageReference(%1$s) : %1$s)', $str);
                    return "throw new MigrationBundleException('The \'resolve\' expression language operator can not be compiled, only evaluated'";
                },
                function ($arguments, $str) use ($resolver) {
                    if (!is_string($str)) {
                        return $str;
                    }

                    return $resolver->resolveReference($str);
                }
            ),

            /// @todo we should allow end users to easily tag sf services to be exposed inside `eval`

            // 'md5' and 'array_merge' are available via step php/call_function. let's not pollute this
            /*new ExpressionFunction(
                'array_merge',
                function () {
                    return sprintf('array_merge(%s)', implode(', ', func_get_args()));
                },
                function () {
                    $args = func_get_args();
                    return call_user_func_array('array_merge', array_splice($args, 1));
                }
            ),
            new ExpressionFunction(
                'md5',
                function ($value) {
                    return sprintf('md5(%s)', $value);
                },
                function ($arguments, $value) {
                    $args = func_get_args();
                    return md5($value);
                }
            ),*/
        );
    }
}
