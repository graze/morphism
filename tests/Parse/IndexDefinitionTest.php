<?php

namespace Graze\Morphism\Parse;

use Graze\Morphism\Test\Parse\TestCase;

class IndexDefinitionTest extends TestCase
{
    /**
     * @dataProvider parseProvider
     * @param string $type
     * @param string $text
     * @param string|null $constraint
     * @param string $expected
     */
    public function testParse($type, $text, $constraint, $expected)
    {
        $stream = $this->makeStream($text);

        $index = new IndexDefinition();
        $index->parse($stream, $type, $constraint);
        if (is_null($index->name)) {
            $index->name = 'k1';
        }

        $this->assertSame($expected, $index->toString());
    }

    /**
     * @return array
     */
    public function parseProvider()
    {
        return [
            ["PRIMARY KEY", "(a)",       null, "PRIMARY KEY (`a`)"],

            ["UNIQUE KEY", "(a)",        null, "UNIQUE KEY `k1` (`a`)"],
            ["UNIQUE KEY", "k (a)",      null, "UNIQUE KEY `k` (`a`)"],

            ["FULLTEXT KEY", "(a)",      null, "FULLTEXT KEY `k1` (`a`)"],
            ["FULLTEXT KEY", "k (a)",    null, "FULLTEXT KEY `k` (`a`)"],

            ["KEY", "(a)",               null, "KEY `k1` (`a`)"],
            ["KEY", "(a ASC)",           null, "KEY `k1` (`a`)"],
            ["KEY", "(a DESC)",          null, "KEY `k1` (`a`)"],
            ["KEY", "k (a)",             null, "KEY `k` (`a`)"],
            ["KEY", "(a,b,c)",           null, "KEY `k1` (`a`,`b`,`c`)"],
            ["KEY", "(a,b,c)",           null, "KEY `k1` (`a`,`b`,`c`)"],
            ["KEY", "(a(10))",           null, "KEY `k1` (`a`(10))"],
            ["KEY", "(a(10),b(12))",     null, "KEY `k1` (`a`(10),`b`(12))"],
            ["KEY", "USING BTREE (a)",   null, "KEY `k1` (`a`) USING BTREE"],
            ["KEY", "k USING BTREE (a)", null, "KEY `k` (`a`) USING BTREE"],
            ["KEY", "(a) USING BTREE",   null, "KEY `k1` (`a`) USING BTREE"],
            ["KEY", "(a) USING HASH",    null, "KEY `k1` (`a`) USING HASH"],
            ["KEY", "(a) KEY_BLOCK_SIZE 10", null, "KEY `k1` (`a`) KEY_BLOCK_SIZE=10"],
            ["KEY", "(a) KEY_BLOCK_SIZE=10", null, "KEY `k1` (`a`) KEY_BLOCK_SIZE=10"],
            ["KEY", "(a) KEY_BLOCK_SIZE=0",  null, "KEY `k1` (`a`)"],
            ["KEY", "(a) COMMENT 'foo'",     null, "KEY `k1` (`a`) COMMENT 'foo'"],
            ["KEY", "(a) COMMENT ''",        null, "KEY `k1` (`a`)"],
            ["KEY", "(a) COMMENT 'foo' USING BTREE KEY_BLOCK_SIZE=10", null,
                "KEY `k1` (`a`) USING BTREE KEY_BLOCK_SIZE=10 COMMENT 'foo'"],

            ["FOREIGN KEY",  "(a) REFERENCES t(ta)", "c1",
                "CONSTRAINT `c1` FOREIGN KEY (`a`) REFERENCES `t` (`ta`)"],

            ["FOREIGN KEY",  "(a) REFERENCES t(ta)", "c1",
                "CONSTRAINT `c1` FOREIGN KEY (`a`) REFERENCES `t` (`ta`)"],

            ["FOREIGN KEY",  "(a) REFERENCES t(ta) ON DELETE RESTRICT", "c1",
                "CONSTRAINT `c1` FOREIGN KEY (`a`) REFERENCES `t` (`ta`)"],

            ["FOREIGN KEY",  "(a) REFERENCES t(ta) ON DELETE CASCADE", "c1",
                "CONSTRAINT `c1` FOREIGN KEY (`a`) REFERENCES `t` (`ta`) ON DELETE CASCADE"],

            ["FOREIGN KEY",  "(a) REFERENCES t(ta) ON DELETE NO ACTION", "c1",
                "CONSTRAINT `c1` FOREIGN KEY (`a`) REFERENCES `t` (`ta`) ON DELETE NO ACTION"],

            ["FOREIGN KEY",  "(a) REFERENCES t(ta) ON UPDATE RESTRICT", "c1",
                "CONSTRAINT `c1` FOREIGN KEY (`a`) REFERENCES `t` (`ta`)"],

            ["FOREIGN KEY",  "(a) REFERENCES t(ta) ON UPDATE CASCADE", "c1",
                "CONSTRAINT `c1` FOREIGN KEY (`a`) REFERENCES `t` (`ta`) ON UPDATE CASCADE"],

            ["FOREIGN KEY",  "(a) REFERENCES t(ta) ON UPDATE NO ACTION", "c1",
                "CONSTRAINT `c1` FOREIGN KEY (`a`) REFERENCES `t` (`ta`) ON UPDATE NO ACTION"],

            ["FOREIGN KEY",  "(a) REFERENCES t(ta) ON UPDATE NO ACTION ON DELETE NO ACTION", "c1",
                "CONSTRAINT `c1` FOREIGN KEY (`a`) REFERENCES `t` (`ta`) ON DELETE NO ACTION ON UPDATE NO ACTION"],

        ];
    }

    /**
     * @dataProvider providerCovers
     * @param string $text
     * @param array $expected
     */
    public function testCovers($text, array $expected)
    {
        $stream = $this->makeStream($text);

        $index = new IndexDefinition();
        $index->parse($stream, 'KEY');
        $covers = $index->getCovers();

        sort($expected);
        sort($covers);

        $this->assertSame($covers, $expected);
    }

    /**
     * @return array
     */
    public function providerCovers()
    {
        return [
            [ '(x)',         [['x']] ],
            [ '(x,y)',       [['x'], ['x','y']] ],
            [ '(x,y,z)',     [['x'], ['x','y'], ['x','y','z']] ],
            [ '(x,y(10),z)', [['x'], ['x','y(10)'], ['x','y(10)','z']] ],
        ];
    }

    /**
     * @dataProvider providerColumns()
     * @param string $text
     * @param array $expected
     */
    public function testColumns($text, array $expected)
    {
        $stream = $this->makeStream($text);

        $index = new IndexDefinition();
        $index->parse($stream, 'KEY');
        $columns = $index->getColumns();

        $this->assertSame($columns, $expected);
    }

    /**
     * @return array
     */
    public function providerColumns()
    {
        return [
            [ '(x)',         ['x'] ],
            [ '(x,y)',       ['x','y'] ],
            [ '(x,y,z)',     ['x','y','z'] ],
            [ '(x,y(10),z)', ['x','y(10)','z'] ],
        ];
    }
}
