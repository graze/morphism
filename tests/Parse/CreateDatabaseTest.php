<?php

namespace Graze\Morphism\Parse;

use Graze\Morphism\Test\Parse\TestCase;
use Mockery;
use RuntimeException;

class CreateDatabaseTest extends TestCase
{
    /**
     * @param string $text
     * @param string $expectedName
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
     * @expectedException RuntimeException
     */
    public function testBadParse()
    {
        $stream = $this->makeStream('foo');
        $database = new CreateDatabase(new CollationInfo());
        $database->parse($stream);
    }

    /**
     * @return array
     */
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

    public function testAddTable()
    {
        $tableName = 'foo';

        $createTable = Mockery::mock(CreateTable::class);
        $createTable->shouldReceive('getName')->andReturn($tableName);

        $database = new CreateDatabase(Mockery::mock(CollationInfo::class));
        $database->addTable($createTable);

        $this->assertArrayHasKey($tableName, $database->tables);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testBadAddTable()
    {
        $createTable = new CreateTable(new CollationInfo());
        $database = new CreateDatabase(new CollationInfo());
        $database->addTable($createTable);
    }

    /**
     * @param CreateDatabase $database
     * @param string $sql
     * @dataProvider testGetDDLProvider
     */
    public function testGetDDL(CreateDatabase $database, $sql)
    {
        $ddl = $database->getDDL();

        $this->assertInternalType('array', $ddl);
        $this->assertEquals(1, count($ddl));
        $this->assertEquals($sql, $ddl[0]);
    }

    /**
     * @return array
     */
    public function testGetDDLProvider()
    {
        $testCases = [];

        // Basic database creation with out any specific collation
        $database = new CreateDatabase(new CollationInfo());
        $database->name = 'alpha';
        $testCases[] = [
            $database,
            'CREATE DATABASE IF NOT EXISTS `alpha`'
        ];

        // Explicit charset specified
        $database = new CreateDatabase(new CollationInfo('utf8'));
        $database->name = 'beta';
        $testCases[] = [
            $database,
            'CREATE DATABASE IF NOT EXISTS `beta` DEFAULT CHARACTER SET utf8'
        ];

        // Explicit collation and charset specified
        $database = new CreateDatabase(new CollationInfo('utf8', 'utf8_unicode_ci'));
        $database->name = 'gamma';
        $testCases[] = [
            $database,
            'CREATE DATABASE IF NOT EXISTS `gamma` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci'
        ];

        return $testCases;
    }

    /**
     * @expectedException RuntimeException
     */
    public function testBadGetDDL()
    {
        $database = new CreateDatabase(new CollationInfo());
        $database->getDDL();
    }

    /**
     * @param CreateDatabase $db1
     * @param CreateDatabase $db2
     * @param array $flags
     * @param array $expected
     * @dataProvider diffProvider
     */
    public function testDiff(CreateDatabase $db1, CreateDatabase $db2, array $flags, array $expected)
    {
        $diff = $db1->diff($db2, $flags);

        $this->assertEquals($expected, $diff);
    }

    /**
     * @return array
     */
    public function diffProvider()
    {
        // [
        //    [ create database 1, create database 2, diff flags, array of statements to execute ],
        //    ...
        // ]
        $testCases = [];

        /** @var CollationInfo|Mockery\MockInterface $collationInfo */
        $collationInfo = Mockery::mock(CollationInfo::class);
        $collationInfo->shouldReceive('isSpecified');

        // Completely empty objects
        $testCases[] = [
            new CreateDatabase($collationInfo),
            new CreateDatabase($collationInfo),
            [],
            []
        ];

        // Named databases
        // Morphism does not support renaming databases
        $db1 = new CreateDatabase($collationInfo);
        $db1->name = 'foo';

        $db2 = new CreateDatabase($collationInfo);
        $db2->name = 'bar';

        $testCases[] = [
            $db1,
            $db2,
            [],
            []
        ];

        // Table added
        $db1 = new CreateDatabase($collationInfo);

        /** @var CreateTable|Mockery\MockInterface $tableA */
        $tableA = Mockery::mock(CreateTable::class);
        $tableA->shouldReceive('getName')->andReturn('t');
        $tableA->shouldReceive('getDDL')->andReturn(["CREATE TABLE `t` (\n  `a` int(11) DEFAULT NULL\n) ENGINE=E"]);

        $db2 = new CreateDatabase($collationInfo);
        $db2->addTable($tableA);

        $testCases[] = [
            $db1,
            $db2,
            [],
            ["CREATE TABLE `t` (\n  `a` int(11) DEFAULT NULL\n) ENGINE=E"]
        ];

        // Table added
        $db1 = new CreateDatabase($collationInfo);

        $db2 = new CreateDatabase($collationInfo);
        $db2->addTable($tableA);

        $testCases[] = [
            $db1,
            $db2,
            [],
            ["CREATE TABLE `t` (\n  `a` int(11) DEFAULT NULL\n) ENGINE=E"]
        ];

        // Table added (but ignored)
        $db1 = new CreateDatabase($collationInfo);

        $db2 = new CreateDatabase($collationInfo);
        $db2->addTable($tableA);

        $testCases[] = [
            $db1,
            $db2,
            ['createTable' => false],
            []
        ];

        // Table removed
        $db1 = new CreateDatabase($collationInfo);
        $db1->addTable($tableA);

        $db2 = new CreateDatabase($collationInfo);

        $testCases[] = [
            $db1,
            $db2,
            [],
            ["DROP TABLE IF EXISTS `t`"]
        ];

        // Table removed (but ignored)
        $db1 = new CreateDatabase($collationInfo);
        $db1->addTable($tableA);

        $db2 = new CreateDatabase($collationInfo);

        $testCases[] = [
            $db1,
            $db2,
            ['dropTable' => false],
            []
        ];

        // Engine changed
        /** @var CreateTable|Mockery\MockInterface $tableWithEngineBar */
        $tableWithEngineBar = Mockery::mock(CreateTable::class);
        $tableWithEngineBar->shouldReceive('getName')->andReturn('t');
        $tableWithEngineBar->shouldReceive('getDDL')->andReturn(["CREATE TABLE `t` (\n  `a` int(11) DEFAULT NULL\n) ENGINE=BAR"]);

        /** @var CreateTable|Mockery\MockInterface $tableWithEngineFoo */
        $tableWithEngineFoo = Mockery::mock(CreateTable::class);
        $tableWithEngineFoo->shouldReceive('getName')->andReturn('t');
        $tableWithEngineFoo->shouldReceive('getDDL')->andReturn(["CREATE TABLE `t` (\n  `a` int(11) DEFAULT NULL\n) ENGINE=FOO"]);
        $tableWithEngineFoo
            ->shouldReceive('diff')
            ->with(
                $tableWithEngineBar,
                ['alterEngine' => true]
            )
            ->andReturn(["ALTER TABLE `t`\nENGINE=BAR"]);

        $db1 = new CreateDatabase($collationInfo);
        $db1->addTable($tableWithEngineFoo);

        $db2 = new CreateDatabase($collationInfo);
        $db2->addTable($tableWithEngineBar);

        $testCases[] = [
            $db1,
            $db2,
            [],
            ["ALTER TABLE `t`\nENGINE=BAR"]
        ];

        // Engine changed (but ignored)
        $db1 = new CreateDatabase($collationInfo);
        $db1->addTable($tableWithEngineFoo);

        $tableWithEngineFoo
            ->shouldReceive('diff')
            ->with(
                $tableWithEngineBar,
                ['alterEngine' => false]
            )
            ->andReturn([]);

        $db2 = new CreateDatabase($collationInfo);
        $db2->addTable($tableWithEngineBar);

        $testCases[] = [
            $db1,
            $db2,
            ['alterEngine' => false],
            []
        ];

        return $testCases;
    }
}
