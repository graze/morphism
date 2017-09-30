<?php

namespace Graze\Morphism\Parse;

use Exception;
use Graze\Morphism\Test\Parse\TestCase;

class CreateTableTest extends TestCase
{
    public function testConstructor()
    {
        $collation = new CollationInfo();
        $table = new CreateTable($collation);
        $this->assertThat($table, $this->isInstanceOf(__NAMESPACE__ . '\CreateTable'));
    }

    public function testSetDefaultEngine()
    {
        $table = new CreateTable(new CollationInfo());
        $table->setDefaultEngine('InnoDB');
        // we're really just checking no exception got thrown
        $this->assertTrue(true);
    }

    /**
     * @dataProvider providerParse
     * @param string $text
     * @param string $expected
     * @throws Exception
     */
    public function testParse($text, $expected)
    {
        $stream = $this->makeStream($text);
        $collation = new CollationInfo();
        $table = new CreateTable($collation);
        $table->setDefaultEngine('InnoDB');

        $threw = null;
        try {
            $table->parse($stream);
        } catch (Exception $e) {
            $threw = $e;
        }
        if (preg_match('/^exception/i', $expected)) {
            if (!preg_match('/^exception\\s+(\\S+)\s+"(.*)"/i', $expected, $pregMatch)) {
                throw new Exception("garbled exception specification: $expected");
            }
            list(, $expectedExceptionType, $expectedMessageRegex) = $pregMatch;
            if (is_null($threw)) {
                $this->fail("expected an $expectedExceptionType exception, but none was thrown");
            } else {
                $this->assertInstanceOf($expectedExceptionType, $threw, "wrong exception type thrown");
                $this->assertRegExp("/$expectedMessageRegex/", $e->getMessage(), "wrong exception message");
            }
        } elseif (is_null($threw)) {
            $ddl = $table->getDDL();
            $actual = $ddl[0] . ';';
            $this->assertCount(1, $ddl);
            $this->assertSame(
                trim(preg_replace('/\s+/', ' ', $expected)),
                trim(preg_replace('/\s+/', ' ', $actual))
            );
        } else {
            $this->fail("Unexpected exception " . get_class($threw) . ": " . $threw->getMessage());
        }
    }

    /**
     * @return array
     */
    public function providerParse()
    {
        $tests = [];

        foreach ([
            'simpleCreateTable.sql',
            'default.sql',
            'primaryKey.sql',
            'nonUniqueIndex.sql',
            'fullTextIndex.sql',
            'uniqueIndex.sql',
            'foreignKey.sql',
            'indexes.sql',
            'timestamp.sql',
        ] as $file) {
            $path = __DIR__ . '/sql/' . $file;
            $sql = @file_get_contents(__DIR__ . '/sql/' . $file);
            if ($sql === false) {
                $this->fail("could not open $path");
            }
            foreach (preg_split('/^-- test .*$/m', $sql) as $pair) {
                if (trim($pair) != '') {
                    list($text, $expected) = preg_split('/(?<=;)/', $pair);
                    $tests[] = [
                        trim($text),
                        trim($expected)
                    ];
                }
            }
        }

        return $tests;
    }

    /**
     * @param string $firstTableText
     * @param string $secondTableText
     * @param string $expected
     * @dataProvider diffProvider
     */
    public function testDiff($firstTableText, $secondTableText, $expected)
    {
        $collation = new CollationInfo();

        $firstStream = $this->makeStream($firstTableText);
        $firstTable = new CreateTable($collation);
        $firstTable->setDefaultEngine('InnoDB');
        $firstTable->parse($firstStream);

        $secondStream = $this->makeStream($secondTableText);
        $secondTable = new CreateTable($collation);
        $secondTable->setDefaultEngine('InnoDB');
        $secondTable->parse($secondStream);

        $diff = $firstTable->diff($secondTable);

        $this->assertEquals($expected == "" ? [] : [$expected], $diff);
    }

    /**
     * @return array
     */
    public function diffProvider()
    {
        $tests = [];

        foreach ([
                    'columns.sql',
                    'indexes.sql',
                    'simpleDiff.sql'
                 ] as $file) {
            $path = __DIR__ . '/sql/diff/' . $file;
            $sql = @file_get_contents($path);
            if ($sql === false) {
                $this->fail("could not open $path");
            }
            foreach (preg_split('/^-- test .*$/m', $sql) as $pair) {
                if (trim($pair) != '') {
                    list($firstText, $secondText, $expected) = preg_split('/(?<=;)/', $pair);
                    $tests[] = [
                        trim($firstText),
                        trim($secondText),
                        trim($expected)
                    ];
                }
            }
        }

        return $tests;
    }

    /**
     * Test that the "alterEngine" flag works.
     * @dataProvider alterEngineProvider
     * @param array $flags
     * @param string $expected
     */
    public function testAlterEngineDiff(array $flags, $expected)
    {
        $sql = 'create table t (a int)';
        $collation = new CollationInfo();

        $firstStream = $this->makeStream($sql);
        $firstTable = new CreateTable($collation);
        $firstTable->setDefaultEngine('InnoDb');
        $firstTable->parse($firstStream);

        $secondStream = $this->makeStream($sql);
        $secondTable = new CreateTable($collation);
        $secondTable->setDefaultEngine('MyISAM');
        $secondTable->parse($secondStream);

        $diff = $firstTable->diff($secondTable, $flags);

        $this->assertEquals($expected, $diff);
    }

    /**
     * @return array
     */
    public function alterEngineProvider()
    {
        return [
            // [ alter engine flag, expected diff ]
            [ [ 'alterEngine' => true ],  ["ALTER TABLE `t`\nENGINE=MyISAM"] ],
            [ [ 'alterEngine' => false ], []                                 ],
            [ [],                         ["ALTER TABLE `t`\nENGINE=MyISAM"] ],
        ];
    }
}
