<?php

/*
 * This file is part of Respect/Validation.
 *
 * (c) Alexandre Gomes Gaigalas <alexandre@gaigalas.net>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

declare(strict_types=1);

namespace CM3_Lib\RespectValidation\Rules;

use CM3_Lib\RespectValidation\Rules\AbstractAgeWithOffset;

/**
 * Validates a minimum age for a given date.
 *
 * @author Emmerson Siqueira <emmersonsiqueira@gmail.com>
 * @author Henrique Moody <henriquemoody@gmail.com>
 * @author Jean Pimentel <jeanfap@gmail.com>
 * @author Kennedy Tedesco <kennedyt.tw@gmail.com>
 */
final class MinAgeWithOffset extends AbstractAgeWithOffset
{
    /**
     * {@inheritDoc}
     */
    protected function compare(int $baseDate, int $givenDate): bool
    {
        return $baseDate >= $givenDate;
    }
}
