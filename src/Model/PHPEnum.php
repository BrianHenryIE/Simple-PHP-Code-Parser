<?php

declare(strict_types=1);

namespace BrianHenryIE\SimplePhpParser\Model;

use BrianHenryIE\SimplePhpParser\Parsers\PhpCodeParser;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;

class PHPEnum extends BasePHPClass
{
    /**
     * @phpstan-var class-string
     */
    public string $name;

    /**
     * The backing type, 'string' or 'int'; null for pure enums.
     */
    public ?string $backingType = null;

    /**
     * @var string[]
     *
     * @phpstan-var class-string[]
     */
    public array $interfaces = [];

    /**
     * @var array<string, PHPEnumCase>
     */
    public array $cases = [];

    /**
     * @param Enum_ $node
     * @param null  $dummy
     *
     * @return $this
     */
    public function readObjectFromPhpNode($node, $dummy = null): self
    {
        $this->prepareNode($node);

        $this->name = static::getFQN($node);

        // Enums are implicitly final and cannot be abstract or anonymous.
        $this->is_final = true;
        $this->is_abstract = false;
        $this->is_anonymous = false;

        if ($node->scalarType !== null) {
            $this->backingType = $node->scalarType->name;
        }

        $enumExists = false;
        try {
            if (
                \PHP_VERSION_ID >= 80100
                &&
                PhpCodeParser::$classExistsAutoload
                &&
                \function_exists('enum_exists')
                &&
                \enum_exists($this->name)
            ) {
                $enumExists = true;
            }
        } catch (\Exception $e) {
            // nothing
        }
        if ($enumExists) {
            $this->readObjectFromReflection(new \ReflectionEnum($this->name));
        }

        $this->collectTags($node);

        foreach ($node->getMethods() as $method) {
            $methodNameTmp = $method->name->name;

            if (isset($this->methods[$methodNameTmp])) {
                $this->methods[$methodNameTmp] = $this->methods[$methodNameTmp]->readObjectFromPhpNode($method, $this->name);
            } else {
                $this->methods[$methodNameTmp] = (new PHPMethod($this->parserContainer))->readObjectFromPhpNode($method, $this->name);
            }

            if (!$this->methods[$methodNameTmp]->file) {
                $this->methods[$methodNameTmp]->file = $this->file;
            }
        }

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof EnumCase) {
                $case = (new PHPEnumCase($this->parserContainer))->readObjectFromPhpNode($stmt);
                if (!$case->file) {
                    $case->file = $this->file;
                }
                $this->cases[$case->name] = $case;
            }
        }

        if (!empty($node->implements)) {
            foreach ($node->implements as $interfaceObject) {
                $interfaceFQN = \implode('\\', $interfaceObject->getParts());
                /** @noinspection PhpSillyAssignmentInspection - hack for phpstan */
                /** @var class-string $interfaceFQN */
                $interfaceFQN = $interfaceFQN;
                $this->interfaces[$interfaceFQN] = $interfaceFQN;
            }
        }

        return $this;
    }

    /**
     * @param \ReflectionEnum $enum
     *
     * @return $this
     */
    public function readObjectFromReflection($enum): self
    {
        $this->name = $enum->getName();

        if (!$this->line) {
            $lineTmp = $enum->getStartLine();
            if ($lineTmp !== false) {
                $this->line = $lineTmp;
            }
        }

        $file = $enum->getFileName();
        if ($file) {
            $this->file = $file;
        }

        $this->is_final = true;
        $this->is_abstract = false;
        $this->is_anonymous = false;

        $backingType = $enum->getBackingType();
        if ($backingType !== null) {
            $this->backingType = (string) $backingType;
        }

        foreach ($enum->getInterfaceNames() as $interfaceName) {
            /** @noinspection PhpSillyAssignmentInspection - hack for phpstan */
            /** @var class-string $interfaceName */
            $interfaceName = $interfaceName;
            $this->interfaces[$interfaceName] = $interfaceName;
        }

        foreach ($enum->getCases() as $case) {
            $casePhp = (new PHPEnumCase($this->parserContainer))->readObjectFromReflection($case);
            $this->cases[$casePhp->name] = $casePhp;
        }

        foreach ($enum->getMethods() as $method) {
            $methodNameTmp = $method->getName();

            $this->methods[$methodNameTmp] = (new PHPMethod($this->parserContainer))->readObjectFromReflection($method);

            if (!$this->methods[$methodNameTmp]->file) {
                $this->methods[$methodNameTmp]->file = $this->file;
            }
        }

        foreach ($enum->getReflectionConstants() as $constant) {
            $constantNameTmp = $constant->getName();

            // Enum cases are reported by getReflectionConstants(); they are already in $this->cases.
            if (isset($this->cases[$constantNameTmp])) {
                continue;
            }

            $this->constants[$constantNameTmp] = (new PHPConst($this->parserContainer))->readObjectFromReflection($constant);

            if (!$this->constants[$constantNameTmp]->file) {
                $this->constants[$constantNameTmp]->file = $this->file;
            }
        }

        return $this;
    }
}
