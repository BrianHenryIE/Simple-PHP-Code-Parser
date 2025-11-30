<?php

declare(strict_types=1);

namespace BrianHenryIE\SimplePhpParser;

/**
 * @internal
 */
final class Dummy12 implements \BrianHenryIE\SimplePhpParser\DummyInterface
{
    /**
     * {@inheritdoc}
     */
    public function withComplexReturnArray($parsedParamTag)
    {
        return [
            'parsedParamTagStr' => 'foo',
            'variableName'      => [null],
        ];
    }
}
