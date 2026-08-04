<?php

/**
 * PHP 8.1 syntax. This file is parsed by tests on all PHP versions but only included/autoloaded on PHP >= 8.1.
 */

declare(strict_types=1);

namespace BrianHenryIE\SimplePhpParser;

enum DummyEnumPure
{
    case Up;
    case Down;
}
