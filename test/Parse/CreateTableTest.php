<?php

namespace Graze\Morphism\Parse;

class CreateTableTest extends \Graze\Morphism\Test\Parse\TestCase
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

    /** @dataProvider providerParse */
    public function testParse($text, $expected)
    {
        $stream = $this->makeStream($text);
        $collation = new CollationInfo();
        $table = new CreateTable($collation);
        $table->setDefaultEngine('InnoDB');

        $threw = null;
        try {
            $table->parse($stream);
        }
        catch(\Exception $e) {
            $threw = $e;
        }
        if (preg_match('/^exception/i', $expected)) {
            if (!preg_match('/^exception\\s+(\\S+)\s+"(.*)"/i', $expected, $pregMatch)) {
                throw new Exception("garbled exception specification: $expected");
            }
            list(, $expectedExceptionType, $expectedMessageRegex) = $pregMatch;
            if (is_null($threw)) {
                $this->fail("expected an $expectedExceptionType exception, but none was thrown");
            }
            else {
                $this->assertInstanceOf($expectedExceptionType, $threw, "wrong exception type thrown");
                $this->assertRegExp("/$expectedMessageRegex/", $e->getMessage(), "wrong exception message");
            }
        }
        elseif (is_null($threw)) {
            $this->assertSame(
                trim(preg_replace('/\s+/', ' ', $expected)),
                trim(preg_replace('/\s+/', ' ', $table->toString()))
            );
        }
        else {
            $this->fail("Unexpected exception " . get_class($threw) . ": " . $threw->getMessage());
        }
    }

    public function providerParse()
    {
        $tests = [];

        foreach([
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
            if ($sql === FALSE) {
                $this->fail("could not open $path");
            }
            foreach(preg_split('/^-- test .*$/m', $sql) as $pair) {
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
}
