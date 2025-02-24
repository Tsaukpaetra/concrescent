<?php
namespace CM3_Lib\RespectValidation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

final class ByIDArrayItemException extends ValidationException
{
    public const NOT_PRESENT = 'not_present';
    public const INVALID = 'invalid';

    /**
     * {@inheritDoc}
     */
    protected $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::NOT_PRESENT => '{{name}} must be present',
            self::INVALID => '{{name}} must be valid',
        ],
        self::MODE_NEGATIVE => [
            self::NOT_PRESENT => '{{name}} must not be present',
            self::INVALID => '{{name}} must not be valid',
        ],
    ];

    /**
     * {@inheritDoc}
     */
    protected function chooseTemplate(): string
    {
        return $this->getParam('hasReference') ? self::INVALID : self::NOT_PRESENT;
    }
}