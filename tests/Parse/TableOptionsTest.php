<?php

namespace Graze\Morphism\Parse;

class TableOptionsTest extends \Graze\Morphism\Test\Parse\TestCase
{
    /** @dataProvider providerSetDefaultEngine */
    public function testSetDefaultEngine($engine, $expected)
    {
        $stream = $this->makeStream('');
        $options = new TableOptions(new CollationInfo());
        $options->setDefaultEngine($engine);
        $options->parse($stream);

        $this->assertSame($expected, $options->toString());
    }

    public function providerSetDefaultEngine()
    {
        return [
            ['innodb', 'ENGINE=InnoDB'],
            ['myisam', 'ENGINE=MyISAM'],
        ];
    }

    /** @dataProvider parseProvider */
    public function testParse($text, $expected)
    {
        $stream = $this->makeStream($text);

        $options = new TableOptions(new CollationInfo());
        $options->setDefaultEngine('InnoDB');
        $options->parse($stream);

        $this->assertSame($expected, $options->toString());
    }

    public function parseProvider()
    {
        return [
            ["auto_increment=123",          "ENGINE=InnoDB"],

            ["CHARSET utf8",                "ENGINE=InnoDB DEFAULT CHARSET=utf8"],
            ["CHARSET=utf8",                "ENGINE=InnoDB DEFAULT CHARSET=utf8"],
            ["CHARACTER SET=utf8",          "ENGINE=InnoDB DEFAULT CHARSET=utf8"],
            ["DEFAULT CHARSET=utf8",        "ENGINE=InnoDB DEFAULT CHARSET=utf8"],
            ["DEFAULT CHARACTER SET=utf8",  "ENGINE=InnoDB DEFAULT CHARSET=utf8"],

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
        ];
    }


    // TODO - test diff()
}
