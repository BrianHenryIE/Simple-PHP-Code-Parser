<?php

declare(strict_types=1);

namespace BrianHenryIE\SimplePhpParser\Model;

use BrianHenryIE\SimplePhpParser\Parsers\Helper\Utils;
use BrianHenryIE\SimplePhpParser\Parsers\PhpCodeParser;
use PhpParser\Comment\Doc;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use ReflectionParameter;

class PHPParameter extends BasePHPElement
{
    /**
     * @var mixed|null
     */
    public $defaultValue;

    public ?string $phpDocRaw = null;

    public ?string $type = null;

    public ?string $typeFromDefaultValue = null;

    public ?string $typeFromPhpDoc = null;

    public ?string $typeFromPhpDocSimple = null;

    public ?string $typeFromPhpDocExtended = null;

    public ?string $typeFromPhpDocMaybeWithComment = null;

    public ?bool $is_vararg = null;

    public ?bool $is_passed_by_ref = null;

    public ?bool $is_inheritdoc = null;

    /**
     * @param Param        $parameter
     * @param FunctionLike $node
     * @param mixed|null   $classStr
     *
     * @return $this
     */
    public function readObjectFromPhpNode($parameter, $node = null, $classStr = null): self
    {
        $parameterVar = $parameter->var;
        if ($parameterVar instanceof \PhpParser\Node\Expr\Error) {
            $this->parseError[] = ($this->line ?? '?') . ':' . ($this->pos ?? '') . ' | may be at this position an expression is required';

            $this->name = \md5(\uniqid('error', true));

            return $this;
        }

        $this->name = \is_string($parameterVar->name) ? $parameterVar->name : '';

        if ($node) {
            $this->prepareNode($node);

            $docComment = $node->getDocComment();
            if ($docComment) {
                $docCommentText = $docComment->getText();

                if (\stripos($docCommentText, '@inheritdoc') !== false) {
                    $this->is_inheritdoc = true;
                }

                $this->readPhpDoc($docComment, $this->name);
            }
        }

        if ($parameter->type !== null) {
            if (!$this->type) {
                if (\method_exists($parameter->type, 'getParts')) {
                    $parts = $parameter->type->getParts();
                    if (!empty($parts)) {
                        $this->type = '\\' . \implode('\\', $parts);
                    }
                } elseif (\property_exists($parameter->type, 'name')) {
                    $this->type = $parameter->type->name;
                }
            }

            if ($parameter->type instanceof \PhpParser\Node\NullableType) {
                if ($this->type && $this->type !== 'null' && \strpos($this->type, 'null|') !== 0) {
                    $this->type = 'null|' . $this->type;
                } elseif (!$this->type) {
                    $this->type = 'null|mixed';
                }
            }
        }

        if ($parameter->default) {
            $defaultValue = Utils::getPhpParserValueFromNode($parameter->default, $classStr, $this->parserContainer);
            if ($defaultValue !== Utils::GET_PHP_PARSER_VALUE_FROM_NODE_HELPER) {
                $this->defaultValue = $defaultValue;

                $this->typeFromDefaultValue = Utils::normalizePhpType(\gettype($this->defaultValue));
            }
        }

        $this->is_vararg = $parameter->variadic;

        $this->is_passed_by_ref = $parameter->byRef;

        return $this;
    }

    /**
     * @param ReflectionParameter $parameter
     *
     * @return $this
     */
    public function readObjectFromReflection($parameter): self
    {
        $this->name = $parameter->getName();

        if ($parameter->isDefaultValueAvailable()) {
            try {
                $this->defaultValue = $parameter->getDefaultValue();
            } catch (\ReflectionException $e) {
                // nothing
            }
            if ($this->defaultValue !== null) {
                $this->typeFromDefaultValue = Utils::normalizePhpType(\gettype($this->defaultValue));
            }
        }

        $method = $parameter->getDeclaringFunction();

        $docComment = $method->getDocComment();
        if ($docComment) {
            if (\stripos($docComment, '@inheritdoc') !== false) {
                $this->is_inheritdoc = true;
            }

            $this->readPhpDoc($docComment, $this->name);
        }

        try {
            $type = $parameter->getType();
        } catch (\ReflectionException $e) {
            $type = null;
        }
        if ($type !== null) {
            if (\method_exists($type, 'getName')) {
                $this->type = Utils::normalizePhpType($type->getName(), true);
            } else {
                $this->type = Utils::normalizePhpType($type . '', true);
            }
            if ($this->type && PhpCodeParser::$classExistsAutoload && \class_exists($this->type)) {
                $this->type = '\\' . \ltrim($this->type, '\\');
            }

            try {
                $constNameTmp = $parameter->getDefaultValueConstantName();
                if ($constNameTmp && \defined($constNameTmp)) {
                    $defaultTmp = \constant($constNameTmp);
                    if ($defaultTmp === null) {
                        if ($this->type && $this->type !== 'null' && \strpos($this->type, 'null|') !== 0) {
                            $this->type = 'null|' . $this->type;
                        } elseif (!$this->type) {
                            $this->type = 'null|mixed';
                        }
                    }
                }
            } catch (\ReflectionException $e) {
                if ($type->allowsNull()) {
                    if ($this->type && $this->type !== 'null' && \strpos($this->type, 'null|') !== 0) {
                        $this->type = 'null|' . $this->type;
                    } elseif (!$this->type) {
                        $this->type = 'null|mixed';
                    }
                }
            }
        }

        $this->is_vararg = $parameter->isVariadic();

        $this->is_passed_by_ref = $parameter->isPassedByReference();

        return $this;
    }

    /**
     * @return string|null
     */
    public function getType(): ?string
    {
        if ($this->typeFromPhpDocExtended) {
            return $this->typeFromPhpDocExtended;
        }

        if ($this->type) {
            return $this->type;
        }

        if ($this->typeFromPhpDocSimple) {
            return $this->typeFromPhpDocSimple;
        }

        return null;
    }

    /**
     * @param Doc|string $doc
     */
    private function readPhpDoc($doc, string $parameterName): void
    {
        if ($doc instanceof Doc) {
            $docComment = $doc->getText();
        } else {
            $docComment = $doc;
        }
        if ($docComment === '') {
            return;
        }

        try {
            $phpDoc = Utils::createDocBlockInstance()->create($docComment);
            $parsedParamTags = $phpDoc->getTagsByName('param');

            if (!empty($parsedParamTags)) {
                foreach ($parsedParamTags as $parsedParamTag) {
                    if ($parsedParamTag instanceof \phpDocumentor\Reflection\DocBlock\Tags\Param) {
                        // check only the current "param"-tag
                        if (\strtoupper($parameterName) !== \strtoupper((string) $parsedParamTag->getVariableName())) {
                            continue;
                        }

                        $type = $parsedParamTag->getType();

                        $this->typeFromPhpDoc = Utils::normalizePhpType($type . '');

                        $typeFromPhpDocMaybeWithCommentTmp = \trim((string) $parsedParamTag);
                        if (
                            $typeFromPhpDocMaybeWithCommentTmp
                            &&
                            \strpos($typeFromPhpDocMaybeWithCommentTmp, '$') !== 0
                        ) {
                            $this->typeFromPhpDocMaybeWithComment = $typeFromPhpDocMaybeWithCommentTmp;
                        }

                        $typeTmp = Utils::parseDocTypeObject($type);
                        if ($typeTmp !== '') {
                            $this->typeFromPhpDocSimple = $typeTmp;
                        }
                    }

                    $parsedParamTagParam = (string) $parsedParamTag;
                    $spitedData = Utils::splitTypeAndVariable($parsedParamTag);
                    $variableName = $spitedData['variableName'];

                    // check only the current "param"-tag
                    if ($variableName && \strtoupper($parameterName) === \strtoupper($variableName)) {
                        $this->phpDocRaw = $parsedParamTagParam;
                        $this->typeFromPhpDocExtended = Utils::modernPhpdoc($parsedParamTagParam);
                    }

                    break;
                }
            }

            $parsedParamTags = $phpDoc->getTagsByName('psalm-param')
                               + $phpDoc->getTagsByName('phpstan-param');

            if (!empty($parsedParamTags)) {
                foreach ($parsedParamTags as $parsedParamTag) {
                    if (!$parsedParamTag instanceof \phpDocumentor\Reflection\DocBlock\Tags\Generic) {
                        continue;
                    }

                    $spitedData = Utils::splitTypeAndVariable($parsedParamTag);
                    $parsedParamTagStr = $spitedData['parsedParamTagStr'];
                    $variableName = $spitedData['variableName'];

                    // check only the current "param"-tag
                    if (!$variableName || \strtoupper($parameterName) !== \strtoupper($variableName)) {
                        continue;
                    }

                    $this->typeFromPhpDocExtended = Utils::modernPhpdoc($parsedParamTagStr);
                }
            }
        } catch (\Exception $e) {
            $tmpErrorMessage = $this->name . ':' . ($this->line ?? '?') . ' | ' . \print_r($e->getMessage(), true);
            $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
        }

        try {
            $this->readPhpDocByTokens($docComment, $parameterName);
        } catch (\Exception $e) {
            $tmpErrorMessage = $this->name . ':' . ($this->line ?? '?') . ' | ' . \print_r($e->getMessage(), true);
            $this->parseError[\md5($tmpErrorMessage)] = $tmpErrorMessage;
        }
    }

    /**
     * @throws \PHPStan\PhpDocParser\Parser\ParserException
     */
    private function readPhpDocByTokens(string $docComment, string $parameterName): void
    {
        $tokens = Utils::modernPhpdocTokens($docComment);

        $paramContent = null;
        foreach ($tokens->getTokens() as $token) {
            $content = $token[0];

            if ($content === '@param' || $content === '@psalm-param' || $content === '@phpstan-param') {
                // reset
                $paramContent = '';

                continue;
            }

            // We can stop if we found the param variable e.g. `@param array{foo:int} $param`.
            if ($content === '$' . $parameterName) {
                break;
            }

            if ($paramContent !== null) {
                $paramContent .= $content;
            }
        }

        $paramContent = $paramContent ? \trim($paramContent) : null;
        if ($paramContent) {
            if (!$this->phpDocRaw) {
                $this->phpDocRaw = $paramContent . ' ' . '$' . $parameterName;
            }
            $this->typeFromPhpDocExtended = Utils::modernPhpdoc($paramContent);
        }
    }
}
