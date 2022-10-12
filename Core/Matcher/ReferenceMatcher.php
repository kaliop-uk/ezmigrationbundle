<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Operator;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;

/**
 * We abuse a bit the 'matcher' framework to set up a 'constraint' matcher which is used to tell whether a reference
 * matches a given condition
 */
class ReferenceMatcher extends AbstractMatcher
{
    //const MATCH_REFERENCE = 'reference';

    protected $allowedConditions = array(
        self::MATCH_AND, self::MATCH_OR, self::MATCH_NOT,
        //self::MATCH_REFERENCE
    );

    // since this is used for recursive calls as well, we have to unlock it for now
    /// @todo allow this to be set in a more flexible way
    protected $maxConditions = 0;

    protected $validator;
    /** @var ReferenceResolverInterface $referenceResolver */
    protected $referenceResolver;

    /// @todo add more operators
    static protected $operatorsMap = array(
        'eq' => '\Symfony\Component\Validator\Constraints\EqualTo',
        'gt' => '\Symfony\Component\Validator\Constraints\GreaterThan',
        'gte' => '\Symfony\Component\Validator\Constraints\GreaterThanOrEqual',
        'lt' => '\Symfony\Component\Validator\Constraints\LessThan',
        'lte' => '\Symfony\Component\Validator\Constraints\LessThanOrEqual',
        'ne' => '\Symfony\Component\Validator\Constraints\NotEqualTo',

        'count' => '\Symfony\Component\Validator\Constraints\Count',
        'length' => '\Symfony\Component\Validator\Constraints\Length',
        'regex' => '\Symfony\Component\Validator\Constraints\Regex',
        'satisfies' => '\Symfony\Component\Validator\Constraints\Expression',
        //'in' => Operator::IN,
        //'between' => Operator::BETWEEN, => use count/length with min & max sub-members
        //'like' => Operator::LIKE, => use regex
        //'contains' => Operator::CONTAINS,
        'isnull' => '\Symfony\Component\Validator\Constraints\IsNull',
        'notnull' => '\Symfony\Component\Validator\Constraints\NotNull',

        Operator::EQ => '\Symfony\Component\Validator\Constraints\EqualTo',
        Operator::GT => '\Symfony\Component\Validator\Constraints\GreaterThan',
        Operator::GTE => '\Symfony\Component\Validator\Constraints\GreaterThanOrEqual',
        Operator::LT => '\Symfony\Component\Validator\Constraints\LessThan',
        Operator::LTE => '\Symfony\Component\Validator\Constraints\LessThanOrEqual',
        '!=' => '\Symfony\Component\Validator\Constraints\NotEqualTo',
        '<>' => '\Symfony\Component\Validator\Constraints\NotEqualTo',
    );

    public function __construct(ReferenceResolverInterface $referenceResolver, ValidatorInterface $validator)
    {
        $this->referenceResolver = $referenceResolver;
        $this->validator = $validator;
    }

    // q: what if we receive an array of conditions? it seems that it might be validated here, even though only the 1st
    //    condition would be taken into account...
    protected function validateConditions(array $conditions)
    {
        foreach ($conditions as $key => $val) {
            if ($this->referenceResolver->isReference($key)) {
                return true;
            }
        }

        return parent::validateConditions($conditions);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return array 1 element with the value true/false
     * @throws InvalidMatchConditionsException
     */
    public function match(array $conditions)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            switch ($key) {
                case self::MATCH_AND:
                    foreach ($values as $subCriterion) {
                        $value = $this->match($subCriterion);
                        if (!reset($value)) {
                            return $value;
                        }
                    }
                    return array(true);

                case self::MATCH_OR:
                    foreach ($values as $subCriterion) {
                        $value = $this->match($subCriterion);
                        if (reset($value)) {
                            return $value;
                        }
                    }
                    return array(false);

                case self::MATCH_NOT:
                    $val = $this->match($values);
                    return array(!reset($val));

                default:
                    // we assume that all are refs because of the call to validate()
                    $currentValue = $this->referenceResolver->resolveReference($key);
                    $targetValue = reset($values);
                    // q: what about resolving refs in teh target value, too ?
                    $constraint = key($values);
                    $errorList = $this->validator->validate($currentValue, $this->getConstraint($constraint, $targetValue));
                    if (0 === count($errorList)) {
                        return array(true);
                    }
                    return array(false);
            }
        }
    }

    /**
     * @param string $constraint
     * @param $targetValue
     * @return mixed
     * @throws InvalidMatchConditionsException for unsupported keys
     */
    protected function getConstraint($constraint, $targetValue)
    {
        if (!isset(self::$operatorsMap[$constraint])) {
            throw new InvalidMatchConditionsException("Matching condition '$constraint' is not supported. Supported conditions are: " .
                implode(', ', array_keys(self::$operatorsMap))
            );
        }

        $class = self::$operatorsMap[$constraint];
        return new $class($targetValue);
    }
}
