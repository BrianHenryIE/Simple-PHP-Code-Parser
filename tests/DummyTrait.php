<?php

declare(strict_types=1);

namespace BrianHenryIE\SimplePhpParser;

/**
 * @internal
 */
trait DummyTrait
{
    /**
     * @var null|float|int<0,10>
     */
    public $lall_trait;

    /**
     * @return float
     */
    public function getLallTrait(): float
    {
        return $this->lall_trait;
    }
}
