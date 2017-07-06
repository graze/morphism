<?php

namespace Graze\Morphism\Parse;

class ColumnDefinitionTest extends \Graze\Morphism\Test\Parse\TestCase
{
    /** @dataProvider parseDatatypesProvider */
    public function testParseDatatypes($text, $expected)
    {
        $stream = $this->makeStream($text);
        $collation = new CollationInfo();

        $column = new ColumnDefinition();
        $column->parse($stream);

        $this->assertSame($expected, $column->toString($collation));
    }

    public function parseDatatypesProvider()
    {
        return [
            ["x int",                       "`x` int(11) DEFAULT NULL"],
            ["x int signed",                "`x` int(11) DEFAULT NULL"], 
            ["x int unsigned",              "`x` int(10) unsigned DEFAULT NULL"], 
            ["x int(5)",                    "`x` int(5) DEFAULT NULL"], 
            ["x int not null default 1",    "`x` int(11) NOT NULL DEFAULT '1'"],
            ["x int zerofill",              "`x` int(10) unsigned zerofill DEFAULT NULL"], 
            ["x int zerofill unsigned",     "`x` int(10) unsigned zerofill DEFAULT NULL"], 
            ["x int unsigned zerofill",     "`x` int(10) unsigned zerofill DEFAULT NULL"], 
            ["x int default 1",             "`x` int(11) DEFAULT '1'"], 
            ["x int(4) zerofill default 1", "`x` int(4) unsigned zerofill DEFAULT '0001'"], 
            ["x int auto_increment",        "`x` int(11) NOT NULL AUTO_INCREMENT"], 
            ["x int comment 'blah'",        "`x` int(11) DEFAULT NULL COMMENT 'blah'"], 
            ["x int serial default value",  "`x` int(11) NOT NULL AUTO_INCREMENT"],

            ["x int primary key",           "`x` int(11) NOT NULL"],
            ["x int key",                   "`x` int(11) NOT NULL"],
            ["x int unique",                "`x` int(11) DEFAULT NULL"],
            ["x int unique key",            "`x` int(11) DEFAULT NULL"],

            ["x bit",                       "`x` bit(1) DEFAULT NULL"],
            ["x bit(4)",                    "`x` bit(4) DEFAULT NULL"],
            ["x bit(4) default 4",          "`x` bit(4) DEFAULT b'100'"],
            ["x bit(4) default b'0101'",    "`x` bit(4) DEFAULT b'101'"],
            ["x bit(8) default x'e4'",      "`x` bit(8) DEFAULT b'11100100'"],

            ["x tinyint",                   "`x` tinyint(4) DEFAULT NULL"],
            ["x tinyint unsigned",          "`x` tinyint(3) unsigned DEFAULT NULL"],
            ["x smallint",                  "`x` smallint(6) DEFAULT NULL"],
            ["x smallint unsigned",         "`x` smallint(5) unsigned DEFAULT NULL"],
            ["x mediumint",                 "`x` mediumint(9) DEFAULT NULL"],
            ["x mediumint unsigned",        "`x` mediumint(8) unsigned DEFAULT NULL"],
            ["x bigint",                    "`x` bigint(20) DEFAULT NULL"],
            ["x bigint unsigned",           "`x` bigint(20) unsigned DEFAULT NULL"],

            ["x double",                    "`x` double DEFAULT NULL"],
            ["x double unsigned",           "`x` double unsigned DEFAULT NULL"],
            ["x double(12,4)",              "`x` double(12,4) DEFAULT NULL"],

            ["x float",                     "`x` float DEFAULT NULL"],
            ["x float unsigned",            "`x` float unsigned DEFAULT NULL"],
            ["x float(8,3)",                "`x` float(8,3) DEFAULT NULL"],

            ["x decimal",                   "`x` decimal(10,0) DEFAULT NULL"],
            ["x decimal unsigned",          "`x` decimal(10,0) unsigned DEFAULT NULL"],
            ["x decimal(8)",                "`x` decimal(8,0) DEFAULT NULL"],
            ["x decimal(8,3)",              "`x` decimal(8,3) DEFAULT NULL"],

            ["x date",                      "`x` date DEFAULT NULL"],
            ["x date default 0",            "`x` date DEFAULT '0000-00-00'"],
            ["x date default '1970-08-12'", "`x` date DEFAULT '1970-08-12'"],

            ["x time",                      "`x` time DEFAULT NULL"],
            ["x time default 0",            "`x` time DEFAULT '00:00:00'"],
            ["x time default '23:59:59'",   "`x` time DEFAULT '23:59:59'"],

            ["x datetime",                               "`x` datetime DEFAULT NULL"],
            ["x datetime default 0",                     "`x` datetime DEFAULT '0000-00-00 00:00:00'"],
            ["x datetime default '1970-08-12 23:58:57'", "`x` datetime DEFAULT '1970-08-12 23:58:57'"],
            ["x datetime default current_timestamp",     "`x` datetime DEFAULT CURRENT_TIMESTAMP"],
            ["x datetime on update current_timestamp",   "`x` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP"],
            ["x datetime default current_timestamp on update current_timestamp",
                "`x` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"],

            ["x timestamp",                               "`x` timestamp NOT NULL"],
            ["x timestamp default 0",                     "`x` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'"],
            ["x timestamp default '1970-08-12 19:18:17'", "`x` timestamp NOT NULL DEFAULT '1970-08-12 19:18:17'"],
            ["x timestamp default current_timestamp",     "`x` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP"],
            ["x timestamp default localtime",             "`x` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP"],
            ["x timestamp default localtimestamp",        "`x` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP"],
            ["x timestamp default now()",                 "`x` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP"],
            ["x timestamp on update current_timestamp",   "`x` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP"],
            ["x timestamp on update localtime",           "`x` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP"],
            ["x timestamp on update localtimestamp",      "`x` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP"],
            ["x timestamp on update now()",               "`x` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP"],
            ["x timestamp default current_timestamp on update current_timestamp",
                "`x` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"],
            ["x timestamp on update current_timestamp default current_timestamp",
                "`x` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"],

            ["x year",             "`x` year(4) DEFAULT NULL"],
            ["x year(4)",          "`x` year(4) DEFAULT NULL"],
            ["x year default 0",   "`x` year(4) DEFAULT '0000'"],
            ["x year default '0'", "`x` year(4) DEFAULT '2000'"],
            ["x year default 69",  "`x` year(4) DEFAULT '2069'"],
            ["x year default 70",  "`x` year(4) DEFAULT '1970'"],
            ["x year default 99",  "`x` year(4) DEFAULT '1999'"],

            ["x char",           "`x` char(1) DEFAULT NULL"],
            ["x char(4)",        "`x` char(4) DEFAULT NULL"],

            ["x varchar(255)",   "`x` varchar(255) DEFAULT NULL"],

            ["x binary",         "`x` binary(1) DEFAULT NULL"],
            ["x binary(255)",    "`x` binary(255) DEFAULT NULL"],

            ["x varbinary(255)", "`x` varbinary(255) DEFAULT NULL"],

            ["x tinyblob",            "`x` tinyblob"],
            ["x tinyblob NULL",       "`x` tinyblob"],
            ["x tinyblob NOT NULL",   "`x` tinyblob NOT NULL"],

            ["x blob",                "`x` blob"],
            ["x blob NULL",           "`x` blob"],
            ["x blob NOT NULL",       "`x` blob NOT NULL"],

            ["x mediumblob",          "`x` mediumblob"],
            ["x mediumblob NULL",     "`x` mediumblob"],
            ["x mediumblob NOT NULL", "`x` mediumblob NOT NULL"],

            ["x longblob",            "`x` longblob"],
            ["x longblob NULL",       "`x` longblob"],
            ["x longblob NOT NULL",   "`x` longblob NOT NULL"],

            ["x tinytext",            "`x` tinytext"],
            ["x tinytext NULL",       "`x` tinytext"],
            ["x tinytext NOT NULL",   "`x` tinytext NOT NULL"],

            ["x text",                "`x` text"],
            ["x text NULL",           "`x` text"],
            ["x text NOT NULL",       "`x` text NOT NULL"],

            ["x mediumtext",          "`x` mediumtext"],
            ["x mediumtext NULL",     "`x` mediumtext"],
            ["x mediumtext NOT NULL", "`x` mediumtext NOT NULL"],

            ["x longtext",            "`x` longtext"],
            ["x longtext NULL",       "`x` longtext"],
            ["x longtext NOT NULL",   "`x` longtext NOT NULL"],

            ["x enum('a', 'b', 'c')",             "`x` enum('a','b','c') DEFAULT NULL"],
            ["x enum('a', 'b', 'c') DEFAULT 'b'", "`x` enum('a','b','c') DEFAULT 'b'"],
            ["x enum('a', 'b', 'c') NOT NULL",    "`x` enum('a','b','c') NOT NULL"],

            ["x set('a', 'b', 'c')",               "`x` set('a','b','c') DEFAULT NULL"],
            ["x set('a', 'b', 'c') DEFAULT 'a,c'", "`x` set('a','b','c') DEFAULT 'a,c'"],
            ["x set('a', 'b', 'c') NOT NULL",      "`x` set('a','b','c') NOT NULL"],

            ["x serial",                 "`x` bigint(20) unsigned NOT NULL AUTO_INCREMENT"],

            ["x character",              "`x` char(1) DEFAULT NULL"],
            ["x character(10)",          "`x` char(10) DEFAULT NULL"],
            ["x character varying(100)", "`x` varchar(100) DEFAULT NULL"],

            ["x double precision",          "`x` double DEFAULT NULL"],
            ["x double precision unsigned", "`x` double unsigned DEFAULT NULL"],
            ["x double precision(12,4)",    "`x` double(12,4) DEFAULT NULL"],

            ["x long varbinary", "`x` mediumblob"],
            ["x long varchar",   "`x` mediumtext"],

            ["x bool",                "`x` tinyint(1) DEFAULT NULL"],
            ["x boolean",             "`x` tinyint(1) DEFAULT NULL"],
            ["x bool NOT NULL",       "`x` tinyint(1) NOT NULL"],
            ["x boolean NOT NULL",    "`x` tinyint(1) NOT NULL"],

            ["x int1",                "`x` tinyint(4) DEFAULT NULL"],
            ["x int1(3)",             "`x` tinyint(3) DEFAULT NULL"],
            ["x int1 NOT NULL",       "`x` tinyint(4) NOT NULL"],

            ["x int2",                "`x` smallint(6) DEFAULT NULL"],
            ["x int2(3)",             "`x` smallint(3) DEFAULT NULL"],
            ["x int2 NOT NULL",       "`x` smallint(6) NOT NULL"],

            ["x int3",                "`x` mediumint(9) DEFAULT NULL"],
            ["x int3(3)",             "`x` mediumint(3) DEFAULT NULL"],
            ["x int3 NOT NULL",       "`x` mediumint(9) NOT NULL"],

            ["x middleint",           "`x` mediumint(9) DEFAULT NULL"],
            ["x middleint(3)",        "`x` mediumint(3) DEFAULT NULL"],
            ["x middleint NOT NULL",  "`x` mediumint(9) NOT NULL"],

            ["x int4",                "`x` int(11) DEFAULT NULL"],
            ["x int4(3)",             "`x` int(3) DEFAULT NULL"],
            ["x int4 NOT NULL",       "`x` int(11) NOT NULL"],

            ["x integer",             "`x` int(11) DEFAULT NULL"],
            ["x integer(3)",          "`x` int(3) DEFAULT NULL"],
            ["x integer NOT NULL",    "`x` int(11) NOT NULL"],

            ["x int8",                "`x` bigint(20) DEFAULT NULL"],
            ["x int8(3)",             "`x` bigint(3) DEFAULT NULL"],
            ["x int8 NOT NULL",       "`x` bigint(20) NOT NULL"],

            ["x dec",                 "`x` decimal(10,0) DEFAULT NULL"],
            ["x dec(8)",              "`x` decimal(8,0) DEFAULT NULL"],
            ["x dec(8,3)",            "`x` decimal(8,3) DEFAULT NULL"],
            ["x dec NOT NULL",        "`x` decimal(10,0) NOT NULL"],

            ["x numeric",             "`x` decimal(10,0) DEFAULT NULL"],
            ["x numeric(8)",          "`x` decimal(8,0) DEFAULT NULL"],
            ["x numeric(8,3)",        "`x` decimal(8,3) DEFAULT NULL"],
            ["x numeric NOT NULL",    "`x` decimal(10,0) NOT NULL"],

            ["x fixed",               "`x` decimal(10,0) DEFAULT NULL"],
            ["x fixed(8)",            "`x` decimal(8,0) DEFAULT NULL"],
            ["x fixed(8,3)",          "`x` decimal(8,3) DEFAULT NULL"],
            ["x fixed NOT NULL",      "`x` decimal(10,0) NOT NULL"],

            ["x real",                "`x` double DEFAULT NULL"],
            ["x real(8,3)",           "`x` double(8,3) DEFAULT NULL"],

        ];
    }

    /** @dataProvider parseIndexesProvider */
    public function testParseIndexes($in, $expectCount)
    {
        $stream = $this->makeStream($in);
        $collation = new CollationInfo();

        $column = new ColumnDefinition();
        $column->parse($stream);

        $count = array_count_values(array_map(function($e) {return $e->type;}, $column->indexes));
        ksort($count);
        ksort($expectCount);

        $this->assertSame($expectCount, $count);
    }

    public function parseIndexesProvider()
    {
        return [
            ["x int",                       []],
            ["x serial",                    ['UNIQUE KEY' => 1]],
            ["x int serial default value",  ['UNIQUE KEY' => 1]],
            ["x int unique",                ['UNIQUE KEY'  => 1]],
            ["x int unique key",            ['UNIQUE KEY'  => 1]],
            ["x int primary key",           ['PRIMARY KEY' => 1]],
            ["x int key",                   ['PRIMARY KEY' => 1]],
        ];
    }

    // TODO - collations
}
