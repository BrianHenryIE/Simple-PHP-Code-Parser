<?php

declare(strict_types=1);

namespace BrianHenryIE\SimplePhpParser;

use BrianHenryIE\SimplePhpParser\Parsers\PhpCodeParser;
use voku\cache\Cache;

/**
 * @internal
 */
final class ParserTest extends \PHPUnit\Framework\TestCase
{
    public function testSimpleOneClass(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy.php');
        $phpClasses = $phpCode->getClasses();

        static::assertTrue(\count($phpClasses) > 0);

        static::assertSame(Dummy::class, $phpClasses[Dummy::class]->name);

        static::assertSame('withoutPhpDocParam', $phpClasses[Dummy::class]->methods['withoutPhpDocParam']->name);

        static::assertSame('bool', $phpClasses[Dummy::class]->methods['withoutPhpDocParam']->parameters['useRandInt']->type);
    }

    public function testSimpleOneClassV2(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy3.php');
        $phpClasses = $phpCode->getClasses();

        static::assertSame(Dummy3::class, $phpClasses[Dummy3::class]->name);

        $lall4 = $phpClasses[Dummy3::class]->properties['lall4'];
        static::assertSame('lall4', $lall4->name);
        static::assertSame('int<-2147483648, 2147483647>', $lall4->typeFromPhpDoc);

        $lall11 = $phpClasses[Dummy3::class]->methods['lall11'];
        static::assertSame('lall11', $lall11->name);
        static::assertSame('BrianHenryIE\SimplePhpParser\DummyInterface', $lall11->returnType);
        static::assertSame('\BrianHenryIE\SimplePhpParser\Dummy3', $lall11->returnTypeFromPhpDocMaybeWithComment);

        $withComplexReturnArray = $phpClasses[Dummy3::class]->methods['withComplexReturnArray'];
        static::assertSame('withComplexReturnArray', $withComplexReturnArray->name);
        static::assertSame('This is a test-text [...] öäü !"§?.', $withComplexReturnArray->summary . $withComplexReturnArray->description);

        $parsedParamTag = $withComplexReturnArray->parameters['parsedParamTag'];
        static::assertSame('\phpDocumentor\Reflection\DocBlock\Tags\BaseTag', $parsedParamTag->type);
        static::assertSame('\phpDocumentor\Reflection\DocBlock\Tags\BaseTag $parsedParamTag <p>this is a test-text [...] öäü !"§?</p>', $parsedParamTag->typeFromPhpDocMaybeWithComment);
    }

    public function testSimpleOneClassV3(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy7.php');
        $phpClasses = $phpCode->getClasses();

        static::assertSame(Dummy7::class, $phpClasses[Dummy7::class]->name);

        $withComplexReturnArray = $phpClasses[Dummy7::class]->methods['getFieldArray'];

        static::assertSame('getFieldArray', $withComplexReturnArray->name);

        $parsedParamTag = $withComplexReturnArray->parameters['RowOffset'];

        static::assertSame('int', $parsedParamTag->typeFromPhpDoc);
        static::assertSame('int $RowOffset', $parsedParamTag->typeFromPhpDocMaybeWithComment);
    }

    public function testSimpleOneClassV4(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy9.php');
        $phpClasses = $phpCode->getClasses();

        self::assertFalse($phpClasses[Dummy9::class]->is_abstract);
        self::assertTrue($phpClasses[Dummy9::class]->is_final);
        self::assertTrue($phpClasses[Dummy9::class]->is_cloneable);
        self::assertTrue($phpClasses[Dummy9::class]->is_instantiable);
        self::assertFalse($phpClasses[Dummy9::class]->is_iterable);

        $getFieldArray = $phpClasses[Dummy9::class]->methods['getFieldArray'];
        static::assertSame('getFieldArray', $getFieldArray->name);

        static::assertSame('BrianHenryIE\SimplePhpParser\Dummy6', $phpClasses[Dummy9::class]->parentClass);
    }

    public function testUnionTypes(): void
    {
        if (PHP_VERSION_ID < 80000) {
            static::markTestSkipped('only for PHP >= 8.0');
        }

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy10.php');
        $phpClasses = $phpCode->getClasses();

        self::assertSame('int', $phpClasses[Dummy10::class]->constants['FOO3']->type);
        self::assertSame('private', $phpClasses[Dummy10::class]->constants['FOO3']->visibility);
        self::assertSame(3, $phpClasses[Dummy10::class]->constants['FOO3']->value);

        self::assertSame('int', $phpClasses[Dummy10::class]->constants['FOO4']->type);
        self::assertSame('public', $phpClasses[Dummy10::class]->constants['FOO4']->visibility);
        self::assertSame(-1, $phpClasses[Dummy10::class]->constants['FOO4']->value);

        self::assertFalse($phpClasses[Dummy10::class]->is_abstract);
        self::assertTrue($phpClasses[Dummy10::class]->is_final);
        self::assertTrue($phpClasses[Dummy10::class]->is_cloneable);
        self::assertTrue($phpClasses[Dummy10::class]->is_instantiable);
        self::assertFalse($phpClasses[Dummy10::class]->is_iterable);

        static::assertSame('null|int', $phpClasses[Dummy10::class]->properties['lall1']->getType());
        static::assertSame('null|int', $phpClasses[Dummy10::class]->properties['lall2']->getType());
        static::assertSame('null|int', $phpClasses[Dummy10::class]->properties['lall3']->getType());

        $getFieldArray = $phpClasses[Dummy10::class]->methods['getFieldArray'];
        static::assertSame('getFieldArray', $getFieldArray->name);
        static::assertSame('int|string', $getFieldArray->parameters['RowOffset']->type);
    }

    public function testConstructorPropertyPromotion(): void
    {
        if (PHP_VERSION_ID >= 80100) {
            $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy11.php');
            $phpClasses = $phpCode->getClasses();

            $getFieldArray = $phpClasses[Dummy11::class]->methods['__construct'];
            static::assertSame('__construct', $getFieldArray->name);
            static::assertSame('\DateTimeImmutable', $getFieldArray->parameters['date']->type);

            $isReadonly = $phpClasses[Dummy11::class]->properties['title']->is_readonly;
            static::assertSame(true, $isReadonly);
        } else {
            static::markTestSkipped('only for PHP >= 8.1');
        }
    }

    public function testReadonlyClass(): void
    {
        if (PHP_VERSION_ID >= 80200) {
            $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy13.php');
            $phpClasses = $phpCode->getClasses();

            $isReadonly = $phpClasses[Dummy13::class]->is_readonly;
            static::assertSame(true, $isReadonly);

            static::assertSame('callable(int): string', $phpClasses[Dummy13::class]->properties['lall']->typeFromPhpDocExtended);
            $isReadonly = $phpClasses[Dummy13::class]->properties['lall']->is_readonly;
            static::assertSame(true, $isReadonly);

            static::assertSame('callable(): int<0, 1>', $phpClasses[Dummy13::class]->methods['callableTest']->returnTypeFromPhpDocExtended);
        } else {
            static::markTestSkipped('only for PHP >= 8.2');
        }
    }

    public function testInheritDocFromInterface(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy12.php');
        $phpClasses = $phpCode->getClasses();

        $getFieldArray = $phpClasses[Dummy12::class]->methods['withComplexReturnArray'];
        static::assertSame('withComplexReturnArray', $getFieldArray->name);
        static::assertSame('\phpDocumentor\Reflection\DocBlock\Tags\BaseTag', $getFieldArray->parameters['parsedParamTag']->type);
        static::assertSame('\phpDocumentor\Reflection\DocBlock\Tags\BaseTag', $getFieldArray->parameters['parsedParamTag']->typeFromPhpDocSimple);
        static::assertSame('array', $getFieldArray->returnTypeFromPhpDocSimple);
        static::assertSame('array{parsedParamTagStr: string, variableName: (null[]|string)}', $getFieldArray->returnTypeFromPhpDocExtended);
    }

    public function testSimpleOneClassWithTrait(): void
    {
        if (PHP_VERSION_ID < 80000) {
            static::markTestSkipped('only for PHP >= 8.0');
        }

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy8.php');
        $phpClasses = $phpCode->getClasses();

        static::assertSame('\Foooooooo', $phpClasses[Dummy8::class]->properties['foooooooo']->defaultValue);

        static::assertSame(Dummy8::class, $phpClasses[Dummy8::class]->name);

        $method = $phpClasses[Dummy8::class]->methods['getLallTrait'];

        static::assertSame('getLallTrait', $method->name);

        static::assertSame(
            'array{stdClass: \stdClass, numbers: (int|float)}',
            $phpClasses[Dummy8::class]->methods['foo_mixed']->returnTypeFromPhpDocExtended
        );

        static::assertSame(
            'array{stdClass: \stdClass, numbers: (int|float)}',
            $phpClasses[Dummy8::class]->methods['foo_mixed']->parameters['lall']->typeFromPhpDocExtended
        );

        static::assertSame(
            'float|int',
            $phpClasses[Dummy8::class]->methods['test_multi_param_type']->parameters['param1']->type
        );

        static::assertSame(
            'int|float',
            $phpClasses[Dummy8::class]->methods['test_multi_param_type']->parameters['param1']->typeFromPhpDoc
        );

//        static::assertSame(
//            'array{stdClass: \stdClass, numbers: int|float $lall',
//            $phpClasses[Dummy8::class]->methods['foo_broken']->parameters['lall']->phpDocRaw
//        );

        static::assertSame(
            'array{stdClass: \stdClass, numbers: int|float <foo/>',
            $phpClasses[Dummy8::class]->methods['foo_broken']->returnPhpDocRaw
        );

        static::assertNull($phpClasses[Dummy8::class]->methods['foo_broken']->returnTypeFromPhpDocExtended);

        static::assertNull($phpClasses[Dummy8::class]->methods['foo_broken']->parameters['lall']->typeFromPhpDocExtended);

        static::assertSame(
            'callable(string): string',
            $phpClasses[Dummy8::class]->methods['withCallback']->parameters['callback']->typeFromPhpDocExtended
        );

        static::assertSame(
            'callable(): numeric',
            $phpClasses[Dummy8::class]->methods['withCallbackMulti']->parameters['callback2']->typeFromPhpDocExtended
        );

        static::assertSame(
            'string',
            $phpClasses[Dummy8::class]->methods['withCallbackMulti']->returnTypeFromPhpDoc
        );
    }

    public function testSimpleOneTrait(): void
    {
        $cache = new Cache(null, null, false);
        $cache->removeAll();

        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/DummyTrait.php');
        $phpTraits = $phpCode->getTraits();

        static::assertSame(DummyTrait::class, $phpTraits[DummyTrait::class]->name);

        self::assertFalse($phpTraits[DummyTrait::class]->is_abstract);
        self::assertFalse($phpTraits[DummyTrait::class]->is_final);
        self::assertFalse($phpTraits[DummyTrait::class]->is_cloneable);
        self::assertFalse($phpTraits[DummyTrait::class]->is_instantiable);
        self::assertFalse($phpTraits[DummyTrait::class]->is_iterable);

        $method = $phpTraits[DummyTrait::class]->methods['getLallTrait'];

        static::assertSame('getLallTrait', $method->name);

        static::assertSame(
            [
                'getLallTrait' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [],
                    'returnTypes'     => [
                        'type'                           => 'float',
                        'typeFromPhpDocMaybeWithComment' => 'float',
                        'typeFromPhpDoc'                 => 'float',
                        'typeFromPhpDocSimple'           => 'float',
                        'typeFromPhpDocExtended'         => 'float',
                    ],
                    'paramsPhpDocRaw' => [],
                    'returnPhpDocRaw' => 'float',
                    'line'            => 20,
                    'file'            => 'Simple-PHP-Code-Parser/tests/DummyTrait.php',
                    'error'           => '',
                    'is_deprecated'   => false,
                    'is_static'       => false,
                    'is_meta'         => false,
                    'is_internal'     => false,
                    'is_removed'      => false,
                ],
            ],
            self::removeLocalPathForTheTest($phpTraits[DummyTrait::class]->getMethodsInfo())
        );

        static::assertSame(
            [
                'lall_trait' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => 'null|float|int<0, 10>',
                    'typeFromPhpDoc'                 => 'null|float|int<0, 10>',
                    'typeFromPhpDocSimple'           => 'null|float|int',
                    'typeFromPhpDocExtended'         => 'null|float|int<0, 10>',
                    'typeFromDefaultValue'           => null,
                ],
            ],
            self::removeLocalPathForTheTest($phpTraits[DummyTrait::class]->getPropertiesInfo())
        );
    }

    public function testSimpleDirectory(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(
            __DIR__ . '/',
            [],
            ['/Dummy5|Dummy1[0|1|3]|Dummy8/']
        );

        $phpClasses = $phpCode->getClasses();

        static::assertSame(Dummy::class, $phpClasses[Dummy::class]->name);

        static::assertSame(Dummy2::class, $phpClasses[Dummy2::class]->name);
        static::assertSame(DummyInterface::class, $phpClasses[Dummy2::class]->interfaces[0]);

        $phpInterfaces = $phpCode->getInterfaces();

        self::assertTrue($phpInterfaces[DummyInterface::class]->is_abstract);
        self::assertFalse($phpInterfaces[DummyInterface::class]->is_final);
        self::assertFalse($phpInterfaces[DummyInterface::class]->is_cloneable);
        self::assertFalse($phpInterfaces[DummyInterface::class]->is_instantiable);
        self::assertFalse($phpInterfaces[DummyInterface::class]->is_iterable);

        static::assertSame('array{parsedParamTagStr: string, variableName: (null[]|string)}', $phpInterfaces[DummyInterface::class]->methods['withComplexReturnArray']->returnTypeFromPhpDocExtended);
    }

    public function testSimpleStringInputClasses(): void
    {
        $code = '<?php
        namespace BrianHenryIE\SimplePhpParser;
        class SimpleClass {}
        $obja = new class() {};
        $objb = new class {};
        class AnotherClass {}';

        $phpCode = PhpCodeParser::getFromString($code);
        $phpClasses = $phpCode->getClasses();

        static::assertCount(4, $phpClasses);
    }

    public function testFromClassName(): void
    {
        $phpCode = PhpCodeParser::getFromClassName(Dummy::class);
        $phpClasses = $phpCode->getClasses();

        static::assertSame('array<int, int>', $phpClasses[Dummy::class]->methods['withReturnType']->returnTypeFromPhpDocExtended);
    }

    public function testSimpleStringInputConstants(): void
    {
        $code = '<?php
        define("FOO", 123);
        ';

        $phpCode = PhpCodeParser::getFromString($code);
        $phpConstants = $phpCode->getConstants();

        static::assertCount(1, $phpConstants);
        static::assertSame(123, $phpConstants['FOO']->value);

        // ---

        $code = '<?php
        namespace foo\bar;
        define("FOO_BAR", "Lall");
        ';

        $phpCode = PhpCodeParser::getFromString($code);
        $phpConstants = $phpCode->getConstants();

        static::assertCount(1, $phpConstants);
        static::assertSame('Lall', $phpConstants['\foo\bar\FOO_BAR']->value);
    }

    public function testSimpleBrokenPhpDocStringInput(): void
    {
        $code = '
        <?php
        /** 
         * @property int[] $foo 
         */
        abstract class Foo { 
            /**
             * @psalm-return
             */
            public function foo() { return []; }
        }
        ';

        $phpCode = PhpCodeParser::getFromString($code);
        $phpClasses = $phpCode->getClasses();

        static::assertSame('Foo', $phpClasses['Foo']->name);
        static::assertSame('int[]', $phpClasses['Foo']->properties['foo']->typeFromPhpDoc);
    }

    public function testSpecialPhpDocComment(): void
    {
        $code = '
        <?php
        /**
        * Add a route like `$router->route(\'/blog/\', function(){...});` where function returns html.
        *
        * @export(Router.route)
        *
        * @return mixed[][][]|false
        */
        function route($pattern, $callback){}
        ';

        $phpCode = PhpCodeParser::getFromString($code);
        $phpFunctions = $phpCode->getFunctions();

        static::assertSame('route', $phpFunctions['route']->name);
        static::assertSame(['export' => '@export (Router.route)', 'return' => '@return array[][]|false'], $phpFunctions['route']->tagNames);
    }

    public function testGetFunctionsInfo(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(
            __DIR__ . '/Dummy.php'
        );
        $phpFunctionsInfo = $phpCode->getFunctionsInfo();

        $result = $phpFunctionsInfo;

        $result = self::removeLocalPathForTheTest($result);

        // DEBUG
        //\var_export($result);

        static::assertSame([
            'BrianHenryIE\\SimplePhpParser\\foo' => [
                'fullDescription' => '',
                'paramsTypes'     => [
                    'foo' => [
                        'type'                           => 'int',
                        'typeFromPhpDocMaybeWithComment' => null,
                        'typeFromPhpDoc'                 => null,
                        'typeFromPhpDocSimple'           => null,
                        'typeFromPhpDocExtended'         => null,
                        'typeFromDefaultValue'           => 'int',
                    ],
                ],
                'returnTypes' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => '\\Dummy',
                    'typeFromPhpDoc'                 => 'Dummy',
                    'typeFromPhpDocSimple'           => '\\Dummy',
                    'typeFromPhpDocExtended'         => 'Dummy',
                ],
                'paramsPhpDocRaw' => [
                    'foo' => null,
                ],
                'returnPhpDocRaw' => '\Dummy',
                'line'            => 10,
                'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                'error'           => '',
                'is_deprecated'   => false,
                'is_meta'         => false,
                'is_internal'     => false,
                'is_removed'      => false,
            ],
        ], $result);
    }

    public function testGetMethodsInfoFromExtendedClass(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(
            __DIR__ . '/Dummy4.php'
        );
        $phpClasses = $phpCode->getClasses();

        $result = $phpClasses[Dummy4::class]->getMethodsInfo();

        $result = self::removeLocalPathForTheTest($result);

        // DEBUG
        //\var_export($result);

        static::assertSame(
            [
                'withoutReturnType' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                    ],
                    'returnTypes' => [
                        'type'                           => null,
                        'typeFromPhpDocMaybeWithComment' => 'false|int',
                        'typeFromPhpDoc'                 => 'false|int',
                        'typeFromPhpDocSimple'           => 'false|int',
                        'typeFromPhpDocExtended'         => 'false|int',
                    ],
                    'paramsPhpDocRaw' => [
                    ],
                    'returnPhpDocRaw' => 'false|int',
                    'line'            => 59,
                    'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'           => '',
                    'is_deprecated'   => false,
                    'is_static'       => false,
                    'is_meta'         => false,
                    'is_internal'     => false,
                    'is_removed'      => false,
                    ],
                'withReturnType' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                    ],
                    'returnTypes' => [
                        'type'                           => 'array',
                        'typeFromPhpDocMaybeWithComment' => 'array<int,int>',
                        'typeFromPhpDoc'                 => 'array<int,int>',
                        'typeFromPhpDocSimple'           => 'int[]',
                        'typeFromPhpDocExtended'         => 'array<int, int>',
                    ],
                    'paramsPhpDocRaw' => [
                    ],
                    'returnPhpDocRaw' => 'array<int,int>',
                    'line'            => 51,
                    'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'           => '',
                    'is_deprecated'   => false,
                    'is_static'       => false,
                    'is_meta'         => false,
                    'is_internal'     => false,
                    'is_removed'      => false,
                    ],
                'getLallTrait' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                    ],
                    'returnTypes' => [
                        'type'                           => 'float',
                        'typeFromPhpDocMaybeWithComment' => 'float',
                        'typeFromPhpDoc'                 => 'float',
                        'typeFromPhpDocSimple'           => 'float',
                        'typeFromPhpDocExtended'         => 'float',
                    ],
                    'paramsPhpDocRaw' => [
                    ],
                    'returnPhpDocRaw' => 'float',
                    'line'            => 20,
                    'file'            => 'Simple-PHP-Code-Parser/tests/DummyTrait.php',
                    'error'           => '',
                    'is_deprecated'   => false,
                    'is_static'       => false,
                    'is_meta'         => false,
                    'is_internal'     => false,
                    'is_removed'      => false,
                    ],
                'withEmptyParamTypePhpDoc' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => null,
                            'typeFromDefaultValue'           => null,
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'array',
                            'typeFromPhpDoc'                 => 'array',
                            'typeFromPhpDocSimple'           => 'array',
                            'typeFromPhpDocExtended'         => 'array{parsedParamTagStr: string, variableName: (null[]|string)}',
                        ],
                        'paramsPhpDocRaw' => [
                            'parsedParamTag' => '$parsedParamTag',
                        ],
                        'returnPhpDocRaw' => 'array',
                        'line'            => 119,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => 'parsedParamTag:? | Unexpected token "$parsedParamTag", expected type at offset 0 on line 1',
                        'is_deprecated'   => false,
                        'is_static'       => true,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withComplexReturnArray' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                           => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocMaybeWithComment' => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag $parsedParamTag',
                            'typeFromPhpDoc'                 => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocSimple'           => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocExtended'         => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromDefaultValue'           => null,
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'array',
                            'typeFromPhpDoc'                 => 'array',
                            'typeFromPhpDocSimple'           => 'array',
                            'typeFromPhpDocExtended'         => 'array{parsedParamTagStr: string, variableName: (null[]|string)}',
                        ],
                        'paramsPhpDocRaw' => [
                            'parsedParamTag' => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag $parsedParamTag',
                        ],
                        'returnPhpDocRaw' => 'array',
                        'line'            => 104,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => true,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withPsalmPhpDocOnlyParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => '?list<int>',
                            'typeFromDefaultValue'           => 'array',
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => null,
                        ],
                        'paramsPhpDocRaw' => [
                            'useRandInt' => '?list<int> $useRandInt',
                        ],
                        'returnPhpDocRaw' => null,
                        'line'            => 90,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => false,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'int[]|null $useRandInt',
                            'typeFromPhpDoc'                 => 'int[]|null',
                            'typeFromPhpDocSimple'           => 'int[]|null',
                            'typeFromPhpDocExtended'         => 'int[]|null',
                            'typeFromDefaultValue'           => 'array',
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => null,
                        ],
                        'paramsPhpDocRaw' => [
                            'useRandInt' => 'int[]|null $useRandInt',
                        ],
                        'returnPhpDocRaw' => null,
                        'line'            => 80,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => false,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withoutPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => 'bool',
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => null,
                            'typeFromDefaultValue'           => 'bool',
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'int[]|string[]|null <p>foo</p>',
                            'typeFromPhpDoc'                 => 'int[]|string[]|null',
                            'typeFromPhpDocSimple'           => 'int[]|string[]|null',
                            'typeFromPhpDocExtended'         => '?list<(int|string)>',
                        ],
                        'paramsPhpDocRaw' => [
                            'useRandInt' => null,
                        ],
                        'returnPhpDocRaw' => 'int[]|string[]|null <p>foo</p>',
                        'line'            => 69,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => false,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withConstFromClass' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'p1' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p1',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocExtended'         => 'mixed',
                            'typeFromDefaultValue'           => 'array',
                        ],
                        'p2' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p2',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocExtended'         => 'mixed',
                            'typeFromDefaultValue'           => 'string',
                        ],
                        'p3' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p3',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocExtended'         => 'mixed',
                            'typeFromDefaultValue'           => 'int',
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'array',
                            'typeFromPhpDoc'                 => 'array',
                            'typeFromPhpDocSimple'           => 'array',
                            'typeFromPhpDocExtended'         => 'array',
                        ],
                        'paramsPhpDocRaw' => [
                            'p1' => 'mixed $p1',
                            'p2' => 'mixed $p2',
                            'p3' => 'mixed $p3',
                        ],
                        'returnPhpDocRaw' => 'array',
                        'line'            => 134,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => false,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
            ],
            $result
        );
    }

    public function testGetMethodsInfo(): void
    {
        $phpCode = PhpCodeParser::getPhpFiles(__DIR__ . '/Dummy.php');
        $phpClasses = $phpCode->getClasses();

        // DEBUG
        //\print_r($phpClasses);

        $props = $phpClasses[Dummy::class]->getPropertiesInfo();

        $props = self::removeLocalPathForTheTest($props);

        // DEBUG
        //\var_export($props);

        static::assertSame(
            [
                'lall1' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => 'null|int[]',
                    'typeFromPhpDoc'                 => 'null|int[]',
                    'typeFromPhpDocSimple'           => 'null|int[]',
                    'typeFromPhpDocExtended'         => 'null|array<int, int>',
                    'typeFromDefaultValue'           => 'array',
                ],
                'lall2' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => 'float',
                    'typeFromPhpDoc'                 => 'float',
                    'typeFromPhpDocSimple'           => 'float',
                    'typeFromPhpDocExtended'         => 'float',
                    'typeFromDefaultValue'           => 'float',
                ],
                'lall3' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => 'null|float',
                    'typeFromPhpDoc'                 => 'null|float',
                    'typeFromPhpDocSimple'           => 'null|float',
                    'typeFromPhpDocExtended'         => 'null|float',
                    'typeFromDefaultValue'           => null,
                ],
                'foo' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => 'int $foo',
                    'typeFromPhpDoc'                 => 'int',
                    'typeFromPhpDocSimple'           => 'int',
                    'typeFromPhpDocExtended'         => 'int',
                    'typeFromDefaultValue'           => null,
                ],
                'bar' => [
                    'type'                           => null,
                    'typeFromPhpDocMaybeWithComment' => 'string $bar',
                    'typeFromPhpDoc'                 => 'string',
                    'typeFromPhpDocSimple'           => 'string',
                    'typeFromPhpDocExtended'         => 'string',
                    'typeFromDefaultValue'           => null,
                ],
            ],
            $props
        );

        $result = $phpClasses[Dummy::class]->getMethodsInfo();

        $result = self::removeLocalPathForTheTest($result);

        // DEBUG
        //var_export($result);

        static::assertSame(
            [
                'withoutReturnType' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                    ],
                    'returnTypes' => [
                        'type'                           => null,
                        'typeFromPhpDocMaybeWithComment' => 'false|int',
                        'typeFromPhpDoc'                 => 'false|int',
                        'typeFromPhpDocSimple'           => 'false|int',
                        'typeFromPhpDocExtended'         => 'false|int',
                    ],
                    'paramsPhpDocRaw' => [
                    ],
                    'returnPhpDocRaw' => 'false|int',
                    'line'            => 59,
                    'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'           => '',
                    'is_deprecated'   => false,
                    'is_static'       => false,
                    'is_meta'         => false,
                    'is_internal'     => false,
                    'is_removed'      => false,
                    ],
                'withReturnType' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                    ],
                    'returnTypes' => [
                        'type'                           => 'array',
                        'typeFromPhpDocMaybeWithComment' => 'array<int,int>',
                        'typeFromPhpDoc'                 => 'array<int,int>',
                        'typeFromPhpDocSimple'           => 'int[]',
                        'typeFromPhpDocExtended'         => 'array<int, int>',
                    ],
                    'paramsPhpDocRaw' => [
                    ],
                    'returnPhpDocRaw' => 'array<int,int>',
                    'line'            => 51,
                    'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                    'error'           => '',
                    'is_deprecated'   => false,
                    'is_static'       => false,
                    'is_meta'         => false,
                    'is_internal'     => false,
                    'is_removed'      => false,
                    ],
                'withEmptyParamTypePhpDoc' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => null,
                            'typeFromDefaultValue'           => null,
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'array',
                            'typeFromPhpDoc'                 => 'array',
                            'typeFromPhpDocSimple'           => 'array',
                            'typeFromPhpDocExtended'         => 'array{parsedParamTagStr: string, variableName: (null[]|string)}',
                        ],
                        'paramsPhpDocRaw' => [
                            'parsedParamTag' => '$parsedParamTag',
                        ],
                        'returnPhpDocRaw' => 'array',
                        'line'            => 119,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error' => 'parsedParamTag:? | Unexpected token "$parsedParamTag", expected type at offset 0 on line 1
parsedParamTag:119 | Unexpected token "$parsedParamTag", expected type at offset 0 on line 1',
                        'is_deprecated' => false,
                        'is_static'     => true,
                        'is_meta'       => false,
                        'is_internal'   => false,
                        'is_removed'    => false,
                    ],
                'withComplexReturnArray' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'parsedParamTag' => [
                            'type'                           => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocMaybeWithComment' => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag $parsedParamTag',
                            'typeFromPhpDoc'                 => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocSimple'           => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromPhpDocExtended'         => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag',
                            'typeFromDefaultValue'           => null,
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'array',
                            'typeFromPhpDoc'                 => 'array',
                            'typeFromPhpDocSimple'           => 'array',
                            'typeFromPhpDocExtended'         => 'array{parsedParamTagStr: string, variableName: (null[]|string)}',
                        ],
                        'paramsPhpDocRaw' => [
                            'parsedParamTag' => '\\phpDocumentor\\Reflection\\DocBlock\\Tags\\BaseTag $parsedParamTag',
                        ],
                        'returnPhpDocRaw' => 'array',
                        'line'            => 104,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => true,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withPsalmPhpDocOnlyParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => '?list<int>',
                            'typeFromDefaultValue'           => 'array',
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => null,
                        ],
                        'paramsPhpDocRaw' => [
                            'useRandInt' => '?list<int> $useRandInt',
                        ],
                        'returnPhpDocRaw' => null,
                        'line'            => 90,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => false,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'int[]|null $useRandInt',
                            'typeFromPhpDoc'                 => 'int[]|null',
                            'typeFromPhpDocSimple'           => 'int[]|null',
                            'typeFromPhpDocExtended'         => 'int[]|null',
                            'typeFromDefaultValue'           => 'array',
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => null,
                        ],
                        'paramsPhpDocRaw' => [
                            'useRandInt' => 'int[]|null $useRandInt',
                        ],
                        'returnPhpDocRaw' => null,
                        'line'            => 80,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => false,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withoutPhpDocParam' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'useRandInt' => [
                            'type'                           => 'bool',
                            'typeFromPhpDocMaybeWithComment' => null,
                            'typeFromPhpDoc'                 => null,
                            'typeFromPhpDocSimple'           => null,
                            'typeFromPhpDocExtended'         => null,
                            'typeFromDefaultValue'           => 'bool',
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'int[]|string[]|null <p>foo</p>',
                            'typeFromPhpDoc'                 => 'int[]|string[]|null',
                            'typeFromPhpDocSimple'           => 'int[]|string[]|null',
                            'typeFromPhpDocExtended'         => '?list<(int|string)>',
                        ],
                        'paramsPhpDocRaw' => [
                            'useRandInt' => null,
                        ],
                        'returnPhpDocRaw' => 'int[]|string[]|null <p>foo</p>',
                        'line'            => 69,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => false,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
                'withConstFromClass' => [
                    'fullDescription' => '',
                    'paramsTypes'     => [
                        'p1' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p1',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocExtended'         => 'mixed',
                            'typeFromDefaultValue'           => 'array',
                        ],
                        'p2' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p2',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocExtended'         => 'mixed',
                            'typeFromDefaultValue'           => 'string',
                        ],
                        'p3' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'mixed $p3',
                            'typeFromPhpDoc'                 => 'mixed',
                            'typeFromPhpDocSimple'           => 'mixed',
                            'typeFromPhpDocExtended'         => 'mixed',
                            'typeFromDefaultValue'           => 'int',
                        ],
                            ],
                        'returnTypes' => [
                            'type'                           => null,
                            'typeFromPhpDocMaybeWithComment' => 'array',
                            'typeFromPhpDoc'                 => 'array',
                            'typeFromPhpDocSimple'           => 'array',
                            'typeFromPhpDocExtended'         => 'array',
                        ],
                        'paramsPhpDocRaw' => [
                            'p1' => 'mixed $p1',
                            'p2' => 'mixed $p2',
                            'p3' => 'mixed $p3',
                        ],
                        'returnPhpDocRaw' => 'array',
                        'line'            => 134,
                        'file'            => 'Simple-PHP-Code-Parser/tests/Dummy.php',
                        'error'           => '',
                        'is_deprecated'   => false,
                        'is_static'       => false,
                        'is_meta'         => false,
                        'is_internal'     => false,
                        'is_removed'      => false,
                    ],
            ],
            $result
        );
    }

    public function testFunctionWithAmpersendInParameters(): void
    {
        $code = '<?php
        /**
         * Open Internet or Unix domain socket connection
         * @link https://php.net/manual/en/function.fsockopen.php
         * @param string $hostname <p>
         * If you have compiled in OpenSSL support, you may prefix the
         * hostname with either ssl://
         * or tls:// to use an SSL or TLS client connection
         * over TCP/IP to connect to the remote host.
         * </p>
         * @param null|int $port [optional] <p>
         * The port number.
         * </p>
         * @param int &$errno [optional] <p>
         * If provided, holds the system level error number that occurred in the
         * system-level connect() call.
         * </p>
         * <p>
         * If the value returned in errno is
         * 0 and the function returned false, it is an
         * indication that the error occurred before the
         * connect() call. This is most likely due to a
         * problem initializing the socket.
         * </p>
         * @param string &$errstr [optional] <p>
         * The error message as a string.
         * </p>
         * @param null|float $timeout [optional] <p>
         * The connection timeout, in seconds.
         * </p>
         * <p>
         * If you need to set a timeout for reading/writing data over the
         * socket, use stream_set_timeout, as the
         * timeout parameter to
         * fsockopen only applies while connecting the
         * socket.
         * </p>
         * @return resource|false fsockopen returns a file pointer which may be used
         * together with the other file functions (such as
         * fgets, fgetss,
         * fwrite, fclose, and
         * feof). If the call fails, it will return false
         */
        function fsockopen ($hostname, $port = null, &$errno = null, &$errstr = null, $timeout = null) { /** ... */ };';

        $phpCode = PhpCodeParser::getFromString($code);
        $phpFunctions = $phpCode->getFunctions();

        self::assertTrue(isset($phpFunctions['fsockopen']));
        self::assertTrue(isset($phpFunctions['fsockopen']->parameters['errno']));
        self::assertTrue($phpFunctions['fsockopen']->parameters['errno']->is_passed_by_ref);
        self::assertSame('int', $phpFunctions['fsockopen']->parameters['errno']->typeFromPhpDoc);

        // -----------------------------

        $phpFunctionsInfo = $phpCode->getFunctionsInfo();
        self::assertSame('int', $phpFunctionsInfo['fsockopen']['paramsTypes']['errno']['typeFromPhpDoc']);
    }

    /**
     * @param array $result
     *
     * @return array
     */
    public static function removeLocalPathForTheTest(array $result): array
    {
        // hack for CI
        $pathReplace = \realpath(\getcwd() . '/../') . '/';
        if (!\is_array($result)) {
            return $result;
        }

        $helper = [];
        foreach ($result as $key => $value) {
            if (\is_string($key)) {
                $key = (string) \str_replace($pathReplace, '', $key);
            }

            if (\is_array($value)) {
                $helper[$key] = self::removeLocalPathForTheTest($value);
            } else {
                if (\is_string($value)) {
                    $helper[$key] = \str_replace($pathReplace, '', $value);
                } else {
                    $helper[$key] = $value;
                }
            }
        }

        return $helper;
    }
}
