<?php
///Ripped and adapted from vendor\respect\validation\library\Rules\AbstractAge.php
namespace CM3_Lib\RespectValidation\Rules;

use Respect\Validation\Helpers\CanValidateDateTime;

use Respect\Validation\Rules\AbstractRule;
use function date;
use function date_parse_from_format;
use function is_scalar;
use function strtotime;
use function vsprintf;

abstract class AbstractAgeWithOffset extends AbstractRule

{
    use CanValidateDateTime;

    /**
     * @var int
     */
    private $age;

    /**
     * @var string|null
     */
    private $format;

    /**
     * @var int
     */
    private $baseDate;

    /**
     * Should compare the current base date with the given one.
     *
     * The dates are represented as integers in the format "Ymd".
     */
    abstract protected function compare(int $baseDate, int $givenDate): bool;

    /**
     * Initializes the rule.
     */
    public function __construct(int $age, ?string $format = null, ?string $offset = null)
    {
        $this->age = $age;
        $this->format = $format;
        $timestamp = null;
        if(!empty($offset)){
            //attempt to set the offset
            //First, as a generic timestamp
            $timestamp = strtotime($offset);
            if ($timestamp === false) {
                //No? Then try to use the given format...
                $parsed = date_create_from_format($format, $offset);
                $timestamp = $parsed === false ? null : $parsed->getTimestamp();
            }
        }
        $this->baseDate = (int) date('Ymd', $timestamp) - $this->age * 10000;
    }

    /**
     * {@inheritDoc}
     */
    public function validate($input): bool
    {
        if (!is_scalar($input)) {
            return false;
        }

        if ($this->format === null) {
            return $this->isValidWithoutFormat((string) $input);
        }

        return $this->isValidWithFormat($this->format, (string) $input);
    }

    private function isValidWithoutFormat(string $dateTime): bool
    {
        $timestamp = strtotime($dateTime);
        if ($timestamp === false) {
            return false;
        }

        return $this->compare($this->baseDate, (int) date('Ymd', $timestamp));
    }

    private function isValidWithFormat(string $format, string $dateTime): bool
    {
        if (!$this->isDateTime($format, $dateTime)) {
            return false;
        }

        return $this->compare(
            $this->baseDate,
            (int) vsprintf('%d%02d%02d', date_parse_from_format($format, $dateTime))
        );
    }
}