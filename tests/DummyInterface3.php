<?php

declare(strict_types=1);

namespace BrianHenryIE\SimplePhpParser;

/**
 * @internal
 */
interface DummyInterface3
{
    /**
     * @param null|\phpDocumentor\Reflection\DocBlock\Tags\BaseTag $parsedParamTag
     *
     * @return array
     *
     * @psalm-return array{parsedParamTagStr: string, variableName: null[]|string}
     */
    public function withComplexReturnArrayInheritDocWithDifferentVariableNames($parsedParamTag);
}
