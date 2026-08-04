<?php

declare(strict_types=1);

namespace BrianHenryIE\SimplePhpParser\Model;

use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\EnumCase;

class PHPEnumCase extends BasePHPElement
{
    use PHPDocElement;

    /**
     * The backing value; null for cases of pure enums or non-scalar const expressions.
     *
     * @var int|string|null
     */
    public $value;

    /**
     * @param EnumCase $node
     * @param null     $dummy
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $dummy = null): self
    {
        $this->prepareNode($node);

        $this->name = $node->name->name;

        if ($node->expr instanceof String_ || $node->expr instanceof LNumber) {
            $this->value = $node->expr->value;
        }

        $this->collectTags($node);

        return $this;
    }

    /**
     * @param \ReflectionEnumUnitCase $case
     *
     * @return $this
     */
    public function readObjectFromReflection($case): self
    {
        $this->name = $case->getName();

        $file = $case->getDeclaringClass()->getFileName();
        if ($file) {
            $this->file = $file;
        }

        if (\method_exists($case, 'getBackingValue')) {
            $this->value = $case->getBackingValue();
        }

        return $this;
    }
}
