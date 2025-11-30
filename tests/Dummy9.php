<?php

declare(strict_types=1);

namespace BrianHenryIE\SimplePhpParser;

use BrianHenryIE\SimplePhpParser\Dummy6 as DummyFoo;

/**
 * @internal
 */
final class Dummy9 extends DummyFoo
{
    use \BrianHenryIE\SimplePhpParser\DummyTrait;

    /**
     * {@inheritdoc}
     */
    public function getFieldArray($RowOffset, $OrderByField, $OrderByDir): array
    {
        return [
            ['foo' => 1],
            ['foo' => 2]
        ];
    }
}
