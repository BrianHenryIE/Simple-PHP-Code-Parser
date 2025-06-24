<?php

declare(strict_types=1);

namespace BrianHenryIE\SimplePhpParser\Parsers;

use BrianHenryIE\SimplePhpParser\Model\PHPInterface;
use BrianHenryIE\SimplePhpParser\Parsers\Helper\ParserContainer;
use BrianHenryIE\SimplePhpParser\Parsers\Helper\ParserErrorHandler;
use BrianHenryIE\SimplePhpParser\Parsers\Helper\Utils;
use BrianHenryIE\SimplePhpParser\Parsers\PhpCodeParser;
use BrianHenryIE\SimplePhpParser\Parsers\Visitors\ASTVisitor;
use BrianHenryIE\SimplePhpParser\Parsers\Visitors\ParentConnector;
use FilesystemIterator;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use voku\cache\Cache;

class PhpCodeParser
{
    /**
     * @internal
     */
    private const CACHE_KEY_HELPER = 'simple-php-code-parser-v4-';

    /**
     * Autoloading during `class_exists()` is fine when you're parsing code for the existing project, but when
     * parsing php text for a different project, it can lead to unexpected behavior. E.g. the text contains a class
     * that _can_ be autoloaded because it shares a name with a class in the current project, but version of the class
     * is different.
     *
     * @see class_exists()
     */
    public static bool $classExistsAutoload = true;

    /**
     * @param string   $code
     * @param string[] $autoloaderProjectPaths
     *
     * @return \BrianHenryIE\SimplePhpParser\Parsers\Helper\ParserContainer
     */
    public static function getFromString(
        string $code,
        array $autoloaderProjectPaths = []
    ): ParserContainer {
        return self::getPhpFiles(
            $code,
            $autoloaderProjectPaths
        );
    }

    /**
     * @param string   $className
     * @param string[] $autoloaderProjectPaths
     *
     * @phpstan-param class-string $className
     *
     * @return \BrianHenryIE\SimplePhpParser\Parsers\Helper\ParserContainer
     */
    public static function getFromClassName(
        string $className,
        array $autoloaderProjectPaths = []
    ): ParserContainer {
        $reflectionClass = Utils::createClassReflectionInstance($className);

        return self::getPhpFiles(
            (string) $reflectionClass->getFileName(),
            $autoloaderProjectPaths
        );
    }

    /**
     * @param string   $pathOrCode
     * @param string[] $autoloaderProjectPaths
     * @param string[] $pathExcludeRegex
     * @param string[] $fileExtensions
     *
     * @return \BrianHenryIE\SimplePhpParser\Parsers\Helper\ParserContainer
     */
    public static function getPhpFiles(
        string $pathOrCode,
        array $autoloaderProjectPaths = [],
        array $pathExcludeRegex = [],
        array $fileExtensions = []
    ): ParserContainer {
        foreach ($autoloaderProjectPaths as $projectPath) {
            if (\file_exists($projectPath) && \is_file($projectPath)) {
                require_once $projectPath;
            } elseif (\file_exists($projectPath . '/vendor/autoload.php')) {
                require_once $projectPath . '/vendor/autoload.php';
            } elseif (\file_exists($projectPath . '/../vendor/autoload.php')) {
                require_once $projectPath . '/../vendor/autoload.php';
            }
        }
        \restore_error_handler();

        $phpCodes = self::getCode(
            $pathOrCode,
            $pathExcludeRegex,
            $fileExtensions
        );

        $parserContainer = new ParserContainer();
        $visitor = new ASTVisitor($parserContainer);

        $processResults = [];
        $phpCodesChunks = \array_chunk($phpCodes, Utils::getCpuCores(), true);

        foreach ($phpCodesChunks as $phpCodesChunk) {
            foreach ($phpCodesChunk as $codeAndFileName) {
                $processResults[] = self::process(
                    $codeAndFileName['content'],
                    $codeAndFileName['fileName'],
                    $parserContainer,
                    $visitor
                );
            }
        }

        foreach ($processResults as $response) {
            if ($response instanceof ParserContainer) {
                $parserContainer->setTraits($response->getTraits());
                $parserContainer->setClasses($response->getClasses());
                $parserContainer->setInterfaces($response->getInterfaces());
                $parserContainer->setConstants($response->getConstants());
                $parserContainer->setFunctions($response->getFunctions());
            } elseif ($response instanceof ParserErrorHandler) {
                $parserContainer->setParseError($response);
            }
        }

        $interfaces = $parserContainer->getInterfaces();
        foreach ($interfaces as &$interface) {
            $interface->parentInterfaces = $visitor->combineParentInterfaces($interface);
        }
        unset($interface);

        $pathTmp = null;
        if (\is_file($pathOrCode)) {
            $pathTmp = \realpath(\pathinfo($pathOrCode, \PATHINFO_DIRNAME));
        } elseif (\is_dir($pathOrCode)) {
            $pathTmp = \realpath($pathOrCode);
        }

        $classesTmp = &$parserContainer->getClassesByReference();
        foreach ($classesTmp as &$classTmp) {
            $classTmp->interfaces = Utils::flattenArray(
                $visitor->combineImplementedInterfaces($classTmp),
                false
            );

            self::mergeInheritdocData(
                $classTmp,
                $classesTmp,
                $interfaces,
                $parserContainer
            );
        }
        unset($classTmp);

        // remove properties / methods / classes from outside of the current file-path-scope
        if ($pathTmp) {
            $classesTmp2 = &$parserContainer->getClassesByReference();
            foreach ($classesTmp2 as $classKey => $classTmp2) {
                foreach ($classTmp2->constants as $constantKey => $constant) {
                    if ($constant->file && \strpos($constant->file, $pathTmp) === false) {
                        unset($classTmp2->constants[$constantKey]);
                    }
                }

                foreach ($classTmp2->properties as $propertyKey => $property) {
                    if ($property->file && \strpos($property->file, $pathTmp) === false) {
                        unset($classTmp2->properties[$propertyKey]);
                    }
                }

                foreach ($classTmp2->methods as $methodKey => $method) {
                    if ($method->file && \strpos($method->file, $pathTmp) === false) {
                        unset($classTmp2->methods[$methodKey]);
                    }
                }

                if ($classTmp2->file && \strpos($classTmp2->file, $pathTmp) === false) {
                    unset($classesTmp2[$classKey]);
                }
            }
        }

        return $parserContainer;
    }

    /**
     * @param string                                               $phpCode
     * @param string|null                                          $fileName
     * @param \BrianHenryIE\SimplePhpParser\Parsers\Helper\ParserContainer $parserContainer
     * @param \BrianHenryIE\SimplePhpParser\Parsers\Visitors\ASTVisitor    $visitor
     *
     * @return \BrianHenryIE\SimplePhpParser\Parsers\Helper\ParserContainer|\BrianHenryIE\SimplePhpParser\Parsers\Helper\ParserErrorHandler
     */
    public static function process(
        string $phpCode,
        ?string $fileName,
        ParserContainer $parserContainer,
        ASTVisitor $visitor
    ) {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        $errorHandler = new ParserErrorHandler();

        $nameResolver = new NameResolver(
            $errorHandler,
            [
                'preserveOriginalNames' => true,
            ]
        );

        /** @var \PhpParser\Node[]|null $parsedCode */
        $parsedCode = $parser->parse($phpCode, $errorHandler);

        if ($parsedCode === null) {
            return $errorHandler;
        }

        $visitor->fileName = $fileName;

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new ParentConnector());
        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($visitor);
        $traverser->traverse($parsedCode);

        return $parserContainer;
    }

    /**
     * @param string   $pathOrCode
     * @param string[] $pathExcludeRegex
     * @param string[] $fileExtensions
     *
     * @return array
     *
     * @psalm-return array<string, array{content: string, fileName: null|string}>
     */
    private static function getCode(
        string $pathOrCode,
        array $pathExcludeRegex = [],
        array $fileExtensions = []
    ): array {
        // init
        $phpCodes = [];
        /** @var SplFileInfo[] $phpFileIterators */
        $phpFileIterators = [];

        // fallback
        if (\count($fileExtensions) === 0) {
            $fileExtensions = ['.php'];
        }

        if (\is_file($pathOrCode)) {
            $phpFileIterators = [new SplFileInfo($pathOrCode)];
        } elseif (\is_dir($pathOrCode)) {
            $phpFileIterators = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($pathOrCode, FilesystemIterator::SKIP_DOTS)
            );
        } else {
            $cacheKey = self::CACHE_KEY_HELPER . \md5($pathOrCode);

            $phpCodes[$cacheKey]['content'] = $pathOrCode;
            $phpCodes[$cacheKey]['fileName'] = null;
            $phpCodes[$cacheKey]['cacheKey'] = $cacheKey;
        }

        $cache = new Cache(null, null, false);

        foreach ($phpFileIterators as $fileOrCode) {
            $path = $fileOrCode->getRealPath();
            if (!$path) {
                continue;
            }

            $fileExtensionFound = false;
            foreach ($fileExtensions as $fileExtension) {
                if (\substr($path, -\strlen($fileExtension)) === $fileExtension) {
                    $fileExtensionFound = true;

                    break;
                }
            }
            if ($fileExtensionFound === false) {
                continue;
            }

            foreach ($pathExcludeRegex as $regex) {
                if (\preg_match($regex, $path)) {
                    continue 2;
                }
            }

            $cacheKey = self::CACHE_KEY_HELPER . \md5($path) . '--' . \filemtime($path);
            if ($cache->getCacheIsReady() === true && $cache->existsItem($cacheKey)) {
                $response = $cache->getItem($cacheKey);
                /** @noinspection PhpSillyAssignmentInspection - helper for phpstan */
                /** @phpstan-var array{content: string, fileName: string, cacheKey: string} $response */
                $response = $response;

                $phpCodes[ $cacheKey ]['content']  = $response['content'];
                $phpCodes[ $cacheKey ]['fileName'] = $response['fileName'];
                $phpCodes[ $cacheKey ]['cacheKey'] = $response['cacheKey'];

                continue;
            }

            $fileContent = file_get_contents($path);

            assert(is_string($fileContent));
            assert(is_string($cacheKey));
            assert($path === null || is_string($path));

            $phpCodes[$cacheKey]['content'] = $fileContent;
            $phpCodes[$cacheKey]['fileName'] = $path;
            $phpCodes[$cacheKey]['cacheKey'] = $cacheKey;

            $cache->setItem($cacheKey, $phpCodes[$cacheKey]);
        }

        return $phpCodes;
    }

    /**
     * @param \BrianHenryIE\SimplePhpParser\Model\PHPClass   $class
     * @param \BrianHenryIE\SimplePhpParser\Model\PHPClass[] $classes
     * @param PHPInterface[]                         $interfaces
     * @param ParserContainer                        $parserContainer
     */
    private static function mergeInheritdocData(
        \BrianHenryIE\SimplePhpParser\Model\PHPClass $class,
        array $classes,
        array $interfaces,
        ParserContainer $parserContainer
    ): void {
        foreach ($class->properties as &$property) {
            if (!$class->parentClass) {
                break;
            }

            if (!$property->is_inheritdoc) {
                continue;
            }

            if (
                !isset($classes[$class->parentClass])
                &&
                PhpCodeParser::$classExistsAutoload && \class_exists($class->parentClass)
            ) {
                $reflectionClassTmp = Utils::createClassReflectionInstance($class->parentClass);
                $classTmp = (new \BrianHenryIE\SimplePhpParser\Model\PHPClass($parserContainer))->readObjectFromReflection($reflectionClassTmp);
                if ($classTmp->name) {
                    $classes[$classTmp->name] = $classTmp;
                }
            }

            if (!isset($classes[$class->parentClass])) {
                continue;
            }

            if (!isset($classes[$class->parentClass]->properties[$property->name])) {
                continue;
            }

            $parentMethod = $classes[$class->parentClass]->properties[$property->name];

            foreach ($property as $key => &$value) {
                if (
                    $value === null
                    &&
                    $parentMethod->{$key} !== null
                    &&
                    \stripos($key, 'type') !== false
                ) {
                    $value = $parentMethod->{$key};
                }
            }
        }
        unset($property, $value); /* @phpstan-ignore-line ? */

        foreach ($class->methods as &$method) {
            if (!$method->is_inheritdoc) {
                continue;
            }

            foreach ($class->interfaces as $interfaceStr) {
                if (
                    !isset($interfaces[$interfaceStr])
                    &&
                    PhpCodeParser::$classExistsAutoload && \interface_exists($interfaceStr, true)
                ) {
                    $reflectionInterfaceTmp = Utils::createClassReflectionInstance($interfaceStr);
                    $interfaceTmp = (new PHPInterface($parserContainer))->readObjectFromReflection($reflectionInterfaceTmp);
                    if ($interfaceTmp->name) {
                        $interfaces[$interfaceTmp->name] = $interfaceTmp;
                    }
                }

                if (!isset($interfaces[$interfaceStr])) {
                    continue;
                }

                if (!isset($interfaces[$interfaceStr]->methods[$method->name])) {
                    continue;
                }

                $interfaceMethod = $interfaces[$interfaceStr]->methods[$method->name];

                foreach ($method as $key => &$value) {
                    if (
                        $value === null
                        &&
                        $interfaceMethod->{$key} !== null
                        &&
                        \stripos($key, 'type') !== false
                    ) {
                        $value = $interfaceMethod->{$key};
                    }

                    if ($key === 'parameters') {
                        $parameterCounter = 0;
                        foreach ($value as &$parameter) {
                            ++$parameterCounter;

                            \assert($parameter instanceof \BrianHenryIE\SimplePhpParser\Model\PHPParameter);

                            $interfaceMethodParameter = null;
                            $parameterCounterInterface = 0;
                            foreach ($interfaceMethod->parameters as $parameterInterface) {
                                ++$parameterCounterInterface;

                                if ($parameterCounterInterface === $parameterCounter) {
                                    $interfaceMethodParameter = $parameterInterface;
                                }
                            }

                            if (!$interfaceMethodParameter) {
                                continue;
                            }

                            foreach ($parameter as $keyInner => &$valueInner) {
                                if (
                                    $valueInner === null
                                    &&
                                    $interfaceMethodParameter->{$keyInner} !== null
                                    &&
                                    \stripos($keyInner, 'type') !== false
                                ) {
                                    $valueInner = $interfaceMethodParameter->{$keyInner};
                                }
                            }
                            unset($valueInner); /* @phpstan-ignore-line ? */
                        }
                        unset($parameter);
                    }
                }
                unset($value); /* @phpstan-ignore-line ? */
            }

            if (!isset($classes[$class->parentClass])) {
                continue;
            }

            if (!isset($classes[$class->parentClass]->methods[$method->name])) {
                continue;
            }

            $parentMethod = $classes[$class->parentClass]->methods[$method->name];

            foreach ($method as $key => &$value) {
                if (
                    $value === null
                    &&
                    $parentMethod->{$key} !== null
                    &&
                    \stripos($key, 'type') !== false
                ) {
                    $value = $parentMethod->{$key};
                }

                if ($key === 'parameters') {
                    $parameterCounter = 0;
                    foreach ($value as &$parameter) {
                        ++$parameterCounter;

                        \assert($parameter instanceof \BrianHenryIE\SimplePhpParser\Model\PHPParameter);

                        $parentMethodParameter = null;
                        $parameterCounterParent = 0;
                        foreach ($parentMethod->parameters as $parameterParent) {
                            ++$parameterCounterParent;

                            if ($parameterCounterParent === $parameterCounter) {
                                $parentMethodParameter = $parameterParent;
                            }
                        }

                        if (!$parentMethodParameter) {
                            continue;
                        }

                        foreach ($parameter as $keyInner => &$valueInner) {
                            if (
                                $valueInner === null
                                &&
                                $parentMethodParameter->{$keyInner} !== null
                                &&
                                \stripos($keyInner, 'type') !== false
                            ) {
                                $valueInner = $parentMethodParameter->{$keyInner};
                            }
                        }
                    }
                }
            }
        }
    }
}
