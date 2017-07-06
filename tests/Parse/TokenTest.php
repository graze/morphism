<?php

namespace Graze\Morphism\Parse;

class TokenTest extends \Graze\Morphism\Test\Parse\TestCase
{
    public function testIsEof()
    {
        $eof = new Token('EOF');
        $this->assertTrue($eof->isEof());

        $notEof = new Token('number', '123');
        $this->assertFalse($notEof->isEof());
    }

    public function testEq()
    {
        $token = new Token('string', 'test');

        $this->assertTrue($token->eq('string', 'test'));
        $this->assertTrue($token->eq('string', 'Test'));
        $this->assertTrue($token->eq('string', 'TEST'));

        $this->assertFalse($token->eq('string', 'testtest'));
        $this->assertFalse($token->eq('number', 'test'));
    }

    public function testMakeStringEscapes()
    {
        $sq = "'";
        $bs = "\\";

        $escaped = Token::fromString(
            "bcd{$bs}{$bs}" .
            "cde{$bs}0" .
            "def{$bs}b" .
            "efg{$bs}n" .
            "fgh{$bs}r" .
            "ghi{$bs}t" .
            "hij{$bs}z" .
            "xyz",
            $sq
        );
        $this->assertTrue(
            $escaped->eq('string', 
                "bcd{$bs}" .
                "cde" . chr(0) .
                "def" . chr(8) .
                "efg" . chr(10) .
                "fgh" . chr(13) .
                "ghi" . chr(9) .
                "hij" . chr(26) .
                "xyz"
            ),
            "all escape sequences are correctly processed"
        );
    }

    public function testMakeStringNonEscapes()
    {
        $sq = "'";
        $dq = '"';
        $bs = "\\";

        $unescaped = Token::fromString(
            "ijk{$bs}a" .
            "jkl{$bs}f" .
            "klm{$bs}${sq}" .
            "lmn{$bs}${dq}" .
            "mno{$bs}?" .
            "nop{$bs}176" .
            "opq{$bs}x7e" .
            "xyz",
            $sq
        );
        $this->assertTrue(
            $unescaped->eq('string', 
                "ijk" . "a" .
                "jkl" . "f" .
                "klm" . "${sq}" .
                "lmn" . "${dq}" .
                "mno" . "?" .
                "nop" . "176" .
                "opq" . "x7e" .
                "xyz"
            )
        );
    }

    public function testMakeStringQuotes()
    {
        $sq = "'";
        $dq = '"';

        foreach([
            ""              => "",
            "{$sq}${sq}"    => "{$sq}",
            "abc{$sq}${sq}" => "abc{$sq}",
            "{$sq}${sq}abc" => "{$sq}abc",
        ] as $arg => $result) {
            $token = Token::fromString($arg, $sq);
            $this->assertTrue($token->eq('string', $result));
        }

        $token = Token::fromString("{$sq}${sq}", $sq);
        $this->assertTrue($token->eq('string', "{$sq}"));
    }

    public function testMakeIdentifier()
    {
        $bs = "\\";

        $token = Token::fromIdentifier(
            "abc``def" .
            "{$bs}0" .
            "{$bs}b" .
            "{$bs}n" .
            "{$bs}r" .
            "{$bs}t" .
            "{$bs}z" .
            ""
        );

        $this->assertTrue(
            $token->eq('identifier', 
                'abc`def' .
                "{$bs}0" .
                "{$bs}b" .
                "{$bs}n" .
                "{$bs}r" .
                "{$bs}t" .
                "{$bs}z" .
                ""
            )
        );
    }

    public function testEscapeStringNull()
    {
        $this->assertSame('NULL', Token::escapeString(null));
    }

    public function testEscapeString()
    {
        $bs = "\\";
        $sq = "'";
        $nul = chr(0);
        $lf = chr(10);
        $cr = chr(13);

        $this->assertSame(
            "{$sq}" .
            "abc''" .
            "def{$bs}0" .
            "ghi{$bs}n" . 
            "jkl{$bs}r" .
            "mno{$bs}{$bs}" .
            "pqr" .
            "{$sq}",
            Token::escapeString(
                "abc{$sq}" .
                "def{$nul}" .
                "ghi{$lf}" .
                "jkl{$cr}" .
                "mno{$bs}" .
                "pqr"
            )
        );
    }

    /** @dataProvider providerEscapeIdentifier */
    public function testEscapeIdentifier($quoteNames, $arg, $expected)
    {
        Token::setQuoteNames($quoteNames);
        $this->assertSame($expected, Token::escapeIdentifier($arg));
    }

    public function providerEscapeIdentifier()
    {
        return [
            [true,  '',        '``'        ],
            [true,  'a',       '`a`'       ],
            [true,  'abc def', '`abc def`' ],
            [true,  'abc`def', '`abc``def`'],

            [false, '',        ''          ],
            [false, 'a',       'a'         ],
            [false, 'abc def', 'abc def'   ],
            [false, 'abc`def', 'abc`def'   ],
        ];
    }

    /** @dataProvider providerAsXyz */
    public function testAsXyz($method, $type, $arg, $expected)
    {
        $token = new Token($type, $arg);
        $this->assertSame($expected, $token->$method());
    }

    public function providerAsXyz()
    {
        return [
            ['asString',   'string', 'abc',                 'abc'    ],
            ['asString',   'number', '123',                 '123'    ],
            ['asString',   'number', '0123',                '123'    ],
            ['asString',   'number', '-0123',               '-123'   ],
            ['asString',   'number', '1.2300',              '1.2300' ],
            ['asString',   'number', '1.234567890123',      '1.234567890123' ],
            ['asString',   'number', '-1.23',               '-1.23'  ],
            ['asString',   'number', '+1.23',               '1.23'   ],
            ['asString',   'number', '.23',                 '0.23'   ],
        
        // TODO - work is needed on Token::asString to make these tests parse:
        //  ['asString',   'number', '1.234e1',             '12.34'  ],
        //  ['asString',   'number', '1.234000e15',         '1.234e15' ],
        //  ['asString',   'number', '0.999999e15',         '999999000000000' ],
        //  ['asString',   'number', '-1.234000e15',        '-1.234e15' ],
        //  ['asString',   'number', '-0.999999e15',        '-999999000000000'],
        //  ['asString',   'number', '1.0001e-15',          '0.0000000000000010001' ],
        //  ['asString',   'number', '0.999e-16',           '9.99e-16' ],
        //  ['asString',   'number', '-1.0001e-15',         '-0.0000000000000010001' ],
        //  ['asString',   'number', '-0.999e-16',          '-9.99e-16' ],

            ['asString',   'hex',    '68656c6c6f21',        'hello!' ],
            ['asString',   'bin',    '0111111000100011',    '~#'     ],

            ['asNumber',   'string', '123',                 123   ],
            ['asNumber',   'number', '456',                 456   ],
            ['asNumber',   'hex',    'fffe',                65534 ],
            ['asNumber',   'bin',    '10100101',            165   ],

            ['asDate',     'string', '0',                   '0000-00-00'],
            ['asDate',     'string', '0000-00-00',          '0000-00-00'],
            ['asDate',     'string', '1970-08-12',          '1970-08-12'],
            ['asDate',     'string', '2001-12-31',          '2001-12-31'],

            ['asTime',     'string', '0',                   '00:00:00'  ],
            ['asTime',     'string', '00:00:00',            '00:00:00'  ],
            ['asTime',     'string', '17:45:59',            '17:45:59'  ],

            ['asDateTime', 'string', '0',                   '0000-00-00 00:00:00'],
            ['asDateTime', 'string', '0000-00-00',          '0000-00-00 00:00:00'],
            ['asDateTime', 'string', '1970-08-12',          '1970-08-12 00:00:00'],
            ['asDateTime', 'string', '2001-12-31',          '2001-12-31 00:00:00'],
            ['asDateTime', 'string', '0000-00-00 00:00:00', '0000-00-00 00:00:00'],
            ['asDateTime', 'string', '1970-08-12 13:34:45', '1970-08-12 13:34:45'],
            ['asDateTime', 'string', '2001-12-31 23:59:59', '2001-12-31 23:59:59'],
        ];
    }

    /**
     * @dataProvider providerAsXyzError
     * @expectedException \Exception
     */
    public function testAsXyzError($method, $type, $arg)
    {
        (new Token($type, $arg))->$method();
    }

    public function providerAsXyzError()
    {
        return [
            ['asDate',     'string', 'abc'       ],
            ['asDate',     'string', '1970'      ],
            ['asDate',     'string', '19700812'  ],
            ['asDate',     'string', '1970/08/12'],

            ['asTime',     'string', 'abc'       ],
            ['asTime',     'string', '000000'    ],
            ['asTime',     'string', '1745'      ],
            ['asTime',     'string', '174559'    ],

            ['asDateTime', 'string', 'abc'           ],
            ['asDateTime', 'string', '19700812'      ],
            ['asDateTime', 'string', '1970/08/12'    ],
            ['asDateTime', 'string', '197008120000'  ],
            ['asDateTime', 'string', '19700812000000'],
        ];
    }
}

