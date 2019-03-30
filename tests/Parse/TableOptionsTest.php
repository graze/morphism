<?php

namespace Graze\Morphism\Parse;

use Graze\Morphism\Test\Parse\TestCase;
use RuntimeException;

class TableOptionsTest extends TestCase
{
    /**
     * @dataProvider providerSetDefaultEngine
     * @param string $engine
     * @param string $expected
     */
    public function testSetDefaultEngine($engine, $expected)
    {
        $stream = $this->makeStream('');
        $options = new TableOptions(new CollationInfo());
        $options->setDefaultEngine($engine);
        $options->parse($stream);

        $this->assertSame($expected, $options->toString());
    }

    /**
     * @return array
     */
    public function providerSetDefaultEngine()
    {
        return [
            ['innodb', 'ENGINE=InnoDB'],
            ['myisam', 'ENGINE=MyISAM'],
        ];
    }

    /**
     * @dataProvider parseProvider
     * @param string $text
     * @param string $expected
     */
    public function testParse($text, $expected)
    {
        $stream = $this->makeStream($text);

        $options = new TableOptions(new CollationInfo());
        $options->setDefaultEngine('InnoDB');
        $options->parse($stream);

        $this->assertSame($expected, $options->toString());
    }

    /**
     * @return array
     */
    public function parseProvider()
    {
        return [
            ["auto_increment=123",          "ENGINE=InnoDB"],

            ["CHARSET default",             "ENGINE=InnoDB"],
            ["CHARSET utf8",                "ENGINE=InnoDB DEFAULT CHARSET=utf8"],
            ["CHARSET=utf8",                "ENGINE=InnoDB DEFAULT CHARSET=utf8"],
            ["CHARACTER SET=utf8",          "ENGINE=InnoDB DEFAULT CHARSET=utf8"],
            ["DEFAULT CHARSET=utf8",        "ENGINE=InnoDB DEFAULT CHARSET=utf8"],
            ["DEFAULT CHARACTER SET=utf8",  "ENGINE=InnoDB DEFAULT CHARSET=utf8"],

            ["COLLATE default",             "ENGINE=InnoDB"],
            ["COLLATE utf8_bin",            "ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin"],
            ["DEFAULT COLLATE=utf8_bin",    "ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin"],

            ["Min_rows=0",                  "ENGINE=InnoDB"],
            ["Min_rows=123",                "ENGINE=InnoDB MIN_ROWS=123"],

            ["Max_Rows=0",                  "ENGINE=InnoDB"],
            ["Max_Rows=123",                "ENGINE=InnoDB MAX_ROWS=123"],

            ["avg_row_length=0",            "ENGINE=InnoDB"],
            ["avg_row_length=123",          "ENGINE=InnoDB AVG_ROW_LENGTH=123"],

            ["PACK_KEYS=DEFAULT",           "ENGINE=InnoDB"],
            ["PACK_KEYS=0",                 "ENGINE=InnoDB PACK_KEYS=0"],
            ["PACK_KEYS=1",                 "ENGINE=InnoDB PACK_KEYS=1"],

            ["CHECKSUM=0",                  "ENGINE=InnoDB"],
            ["CHECKSUM=1",                  "ENGINE=InnoDB CHECKSUM=1"],

            ["delay_key_write=0",           "ENGINE=InnoDB"],
            ["delay_key_write=1",           "ENGINE=InnoDB DELAY_KEY_WRITE=1"],

            ["row_format=default",          "ENGINE=InnoDB"],
            ["row_format=dynamic",          "ENGINE=InnoDB ROW_FORMAT=DYNAMIC"],
            ["row_format=fixed",            "ENGINE=InnoDB ROW_FORMAT=FIXED"],
            ["row_format=compressed",       "ENGINE=InnoDB ROW_FORMAT=COMPRESSED"],
            ["row_format=redundant",        "ENGINE=InnoDB ROW_FORMAT=REDUNDANT"],
            ["row_format=compact",          "ENGINE=InnoDB ROW_FORMAT=COMPACT"],

            ["key_block_size=0",            "ENGINE=InnoDB"],
            ["key_block_size=123",          "ENGINE=InnoDB KEY_BLOCK_SIZE=123"],

            ["comment=''",                  "ENGINE=InnoDB"],
            ["comment='hello world'",       "ENGINE=InnoDB COMMENT='hello world'"],
            ["comment 'hello world'",       "ENGINE=InnoDB COMMENT='hello world'"],

            ["connection=''",               "ENGINE=InnoDB"],
            ["connection='hello world'",    "ENGINE=InnoDB CONNECTION='hello world'"],
            ["connection 'hello world'",    "ENGINE=InnoDB CONNECTION='hello world'"],

            ["DATA DIRECTORY='blah'",       "ENGINE=InnoDB"],
            ["INDEX DIRECTORY='blah'",      "ENGINE=InnoDB"],
            ["PASSWORD='blah'",             "ENGINE=InnoDB"],

            ["engine=innodb",               "ENGINE=InnoDB"],
            ["engine=myisam",               "ENGINE=MyISAM"],
            ["engine=foo",                  "ENGINE=FOO"],
        ];
    }

    /**
     * @dataProvider badParseProvider
     * @param string $text
     * @expectedException RuntimeException
     */
    public function testBadParse($text)
    {
        $stream = $this->makeStream($text);

        $options = new TableOptions(new CollationInfo());
        $options->setDefaultEngine('InnoDB');
        $options->parse($stream);
    }

    /**
     * @return array
     */
    public function badParseProvider()
    {
        return [
            // Invalid option for DEFAULT keyword
            ["default foo"],
            // These three INSERT_METHOD variants are valid but not supported
            ["insert_method=no"],
            ["insert_method=first"],
            ["insert_method=last"],
            // This INSERT_METHOD variant is invalid
            ["insert_method=foo"],
            // These options are unsupported
            ["stats_sample_pages"],
            ["stats_auto_recalc"],
            ["stats_persistent"],
            ["tablespace"],
            ["union"],
            ["partition"],
            // Any other options are unsupported
            ["foo"],
            // Missing value
            ["engine="],
            // Invalid value for option that expects a single value
            ["engine=("],
            // Invalid value for an option that expects a set of values
            ["row_format=("],
        ];
    }

    /**
     * @param string $firstTableText
     * @param string $secondTableText
     * @param string $expected
     * @dataProvider diffProvider
     */
    public function testDiff($firstTableText, $secondTableText, $expected)
    {
        $firstStream = $this->makeStream($firstTableText);

        $firstTableOptions = new TableOptions(new CollationInfo());
        $firstTableOptions->setDefaultEngine('InnoDB');
        $firstTableOptions->parse($firstStream);

        $secondStream = $this->makeStream($secondTableText);

        $secondTableOptions = new TableOptions(new CollationInfo());
        $secondTableOptions->setDefaultEngine('InnoDB');
        $secondTableOptions->parse($secondStream);

        $diff = $firstTableOptions->diff($secondTableOptions);

        $this->assertSame($expected, $diff);
    }

    /**
     * @return array
     */
    public function diffProvider()
    {
        return [
            // [ 1st table option string, 2nd table option string, expected result ]

            // AVG_ROW_LENGTH
            ["",                    "avg_row_length=100",   "AVG_ROW_LENGTH=100"],
            ["avg_row_length=200",  "",                     "AVG_ROW_LENGTH=0"],
            ["avg_row_length=100",  "avg_row_length=200",   "AVG_ROW_LENGTH=200"],

            // CHARSET
            ["",                "charset latin1",   "DEFAULT CHARSET=latin1"],
            ["charset latin1",  "",                 ""],
            ["charset default", "charset utf8",     "DEFAULT CHARSET=utf8"],

            // COLLATE
            ["",                        "collate=utf8_unicode_ci",      "DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci"],
            ["collate=utf8_unicode_ci", "",                             ""],
            ["collate=utf8_unicode_ci", "collate=latin1_german1_ci",    "DEFAULT CHARSET=latin1 COLLATE=latin1_german1_ci"],

            // CHECKSUM
            ["",            "checksum=1",   "CHECKSUM=1"],
            ["checksum=1",  "",             "CHECKSUM=0"],
            ["checksum=1",  "checksum=0",   "CHECKSUM=0"],

            // COMMENT
            ["comment 'hello'", "",                     "COMMENT=''"],
            ["",                "comment 'hello'",      "COMMENT='hello'"],
            ["comment 'hello'", "comment 'goodbye'",    "COMMENT='goodbye'"],

            // CONNECTION
            ["connection 'foo'",    "",                 "CONNECTION=''"   ],
            ["",                    "connection 'foo'", "CONNECTION='foo'"],
            ["connection 'foo'",    "connection 'bar'", "CONNECTION='bar'"],

            // DELAY_KEY_WRITE
            ["",                    "delay_key_write=1",    "DELAY_KEY_WRITE=1"],
            ["delay_key_write=1",   "",                     "DELAY_KEY_WRITE=0"],
            ["delay_key_write=1",   "delay_key_write=0",    "DELAY_KEY_WRITE=0"],

            // ENGINE
            ["",                "engine=memory",    "ENGINE=MEMORY"],
            ["engine=InnoDB",   "",                 ""],
            ["engine=InnoDB",   "engine=MyISAM",    "ENGINE=MyISAM"],

            // KEY_BLOCK_SIZE
            ["",                    "key_block_size=4",     "KEY_BLOCK_SIZE=4"],
            ["key_block_size=8",    "",                     "KEY_BLOCK_SIZE=0"],
            ["key_block_size=4",    "key_block_size=16",    "KEY_BLOCK_SIZE=16"],

            // MAX_ROWS
            ["",                "max_rows=100", "MAX_ROWS=100"],
            ["max_rows=200",    "",             "MAX_ROWS=0"],
            ["max_rows=100",    "max_rows=200", "MAX_ROWS=200"],

            // MIN_ROWS
            ["",                "min_rows=100", "MIN_ROWS=100"],
            ["min_rows=200",    "",             "MIN_ROWS=0"],
            ["min_rows=100",    "min_rows=200", "MIN_ROWS=200"],

            // PACK_KEYS
            ["",            "pack_keys=1",          "PACK_KEYS=1"],
            ["pack_keys=0", "",                     "PACK_KEYS=DEFAULT"],
            ["pack_keys=1", "pack_keys=default",    "PACK_KEYS=DEFAULT"],

            /*
             * Everything below here is currently unsupported by the
             * diff() function and so has an expected value of an empty string.
             * These should be moved up as and when support is added.
             */

            // AUTO_INCREMENT
            ["",                    "auto_increment 1",     ""],
            ["",                    "auto_increment 5",     ""],
            ["auto_increment 1",    "",                     ""],
            ["auto_increment 3",    "auto_increment 5",     ""],

            // ROW_FORMAT
            ["",                    "row_format=compressed",    ""],
            ["row_format=fixed",    "",                         ""],
            ["row_format=compact",  "row_format=redundant",     ""]
        ];
    }

    /**
     * Test that disabling "alterEngine" works.
     */
    public function testNoAlterEngineDiff()
    {
        $firstTableOptions = new TableOptions(new CollationInfo());
        $firstTableOptions->parse($this->makeStream("engine=MyISAM"));

        $secondTableOptions = new TableOptions(new CollationInfo());
        $secondTableOptions->parse($this->makeStream("engine=MEMORY"));

        $diff = $firstTableOptions->diff($secondTableOptions, ['alterEngine' => false]);

        $this->assertEmpty($diff);
    }
}
