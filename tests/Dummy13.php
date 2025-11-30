<?php

declare(strict_types=1);

namespace BrianHenryIE\SimplePhpParser;

/**
 * @internal
 */
final readonly class Dummy13 implements \BrianHenryIE\SimplePhpParser\DummyInterface
{
    /**
     * @var callable(int): string
     */
    public int $lall;

    public function __construct(int $lall)
    {
        $this->lall = $lall;
    }

    /**
     * @return callable(): int<0,1>
     */
    public function callableTest(): callable
    {
        return static function () {
            return 1;
        };
    }

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
