<?php

namespace Graze\Morphism\Parse;

use Graze\Morphism\Test\Parse\TestCase;

class CreateDatabaseTest extends TestCase
{
    /**
     * @param string $text
     * @dataProvider parseNameProvider
     */
    public function testParseName($text, $expectedName)
    {
        $stream = $this->makeStream($text);
        $database = new CreateDatabase(new CollationInfo());
        $database->parse($stream);

        $this->assertEquals($expectedName, $database->name);
    }

    /**
     * @return array
     */
    public function parseNameProvider()
    {
        return [
            [ 'create database foo',               'foo' ],
            [ 'create database if not exists bar', 'bar' ],
        ];
    }

    /**
     * @param string $text
     * @param string $expectedCharset
     * @dataProvider parseCharsetProvider
     */
    public function testParseCharset($text, $expectedCharset)
    {
        $stream = $this->makeStream('create database foo ' . $text);
        $database = new CreateDatabase(new CollationInfo());
        $database->parse($stream);

        if ($database->getCollation()->isSpecified()) {
            $charset = $database->getCollation()->getCharset();
        } else {
            $charset = null;
        }
        $this->assertEquals($expectedCharset, $charset);
    }

    /**
     * @return array
     */
    public function parseCharsetProvider()
    {
        return [
            [ 'charset = default', null ],
            [ 'character set = default', null ],

            [ 'charset = utf8', 'utf8' ],
        ];
    }

    /**
     * @param string $text
     * @param string $expectedCollation
     * @dataProvider parseCollationProvider
     */
    public function testParseCollation($text, $expectedCollation)
    {
        $stream = $this->makeStream('create database foo ' . $text);
        $database = new CreateDatabase(new CollationInfo());
        $database->parse($stream);

        if ($database->getCollation()->isSpecified()) {
            $collation = $database->getCollation()->getCollation();
        } else {
            $collation = null;
        }
        $this->assertEquals($expectedCollation, $collation);
    }

    /**
     * @return array
     */
    public function parseCollationProvider()
    {
        return [
            [ 'collate = default', null ],
            [ 'collate = utf8_general_ci', 'utf8_general_ci' ],
        ];
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testBadParse()
    {
        $stream = $this->makeStream('foo');
        $database = new CreateDatabase(new CollationInfo());
        $database->parse($stream);
    }

    public function badParseProvider()
    {
        return [
            // Not a 'create database' statement
            [ 'foo' ],

            // No database name
            [ 'create database' ],

            // invalid character set syntax
            [ 'create database foo default charset utf8' ],

            // invalid character set
            [ 'create database foo default charset = bar' ],
        ];
    }
}
