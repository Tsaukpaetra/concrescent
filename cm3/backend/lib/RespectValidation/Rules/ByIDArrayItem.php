<?php

namespace CM3_Lib\RespectValidation\Rules;

use Respect\Validation\Rules\AbstractRelated;
use Respect\Validation\Exceptions\ComponentException;
use Respect\Validation\Validatable;

use function array_key_exists;
use function is_array;
use function is_scalar;

final class ByIDArrayItem extends AbstractRelated
{
    
    /**
     * @param mixed $reference
     */
    public function __construct($reference, ?Validatable $rule = null, bool $mandatory = true, protected string $IDKey = 'ID')
    {
        if (!is_scalar($reference) || $reference === '') {
            throw new ComponentException('Invalid array key name');
        }

        parent::__construct($reference, $rule, $mandatory);
    }

    /**
     * {@inheritDoc}
     */
    public function getReferenceValue($input)
    {
        //Search the input for an object with the given key
        return array_column($input, null, $this->IDKey)[$this->getReference()];
    }

    /**
     * {@inheritDoc}
     */
    public function hasReference($input): bool
    {
        return is_array($input) && array_key_exists($this->getReference(), array_column($input, $this->IDKey, $this->IDKey));
    }
}