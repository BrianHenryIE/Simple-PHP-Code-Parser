<?php

/**
 * PHP 8.1 syntax. This file is parsed by tests on all PHP versions but only included/autoloaded on PHP >= 8.1.
 */

declare(strict_types=1);

namespace BrianHenryIE\SimplePhpParser;

enum DummyEnum: string implements DummyEnumInterface
{
    case Hearts = 'hearts';
    case Spades = 'spades';

    public const REGEX = '/^(hearts|spades)$/';

    public function label(): string
    {
        return \ucfirst($this->value);
    }
}
