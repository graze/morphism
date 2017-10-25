<?php

namespace Graze\Morphism\Parse;

use Graze\Morphism\Test\Parse\TestCase;

class TokenTest extends TestCase
{
    public function testIsEof()
    {
        $eof = new Token(Token::EOF);
        $this->assertTrue($eof->isEof());

        $notEof = new Token(Token::NUMBER, '123');
        $this->assertFalse($notEof->isEof());
    }

    public function testEq()
    {
        $token = new Token(Token::STRING, 'test');

        $this->assertTrue($token->eq(Token::STRING, 'test'));
        $this->assertTrue($token->eq(Token::STRING, 'Test'));
        $this->assertTrue($token->eq(Token::STRING, 'TEST'));

        $this->assertFalse($token->eq(Token::STRING, 'testtest'));
        $this->assertFalse($token->eq(Token::NUMBER, 'test'));
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
            $escaped->eq(
                Token::STRING,
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
            $unescaped->eq(
                Token::STRING,
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

        foreach ([
            ""              => "",
            "{$sq}${sq}"    => "{$sq}",
            "abc{$sq}${sq}" => "abc{$sq}",
            "{$sq}${sq}abc" => "{$sq}abc",
        ] as $arg => $result) {
            $token = Token::fromString($arg, $sq);
            $this->assertTrue($token->eq(Token::STRING, $result));
        }

        $token = Token::fromString("{$sq}${sq}", $sq);
        $this->assertTrue($token->eq(Token::STRING, "{$sq}"));
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
            $token->eq(
                Token::IDENTIFIER,
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

    /**
     * @dataProvider providerEscapeIdentifier
     * @param bool   $quoteNames
     * @param string $arg
     * @param string $expected
     */
    public function testEscapeIdentifier($quoteNames, $arg, $expected)
    {
        Token::setQuoteNames($quoteNames);
        $this->assertSame($expected, Token::escapeIdentifier($arg));
    }

    /**
     * @return array
     */
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

    /**
     * @dataProvider providerAsXyz
     * @param string $method
     * @param string $type
     * @param string $arg
     * @param mixed  $expected
     */
    public function testAsXyz($method, $type, $arg, $expected)
    {
        $token = new Token($type, $arg);
        $this->assertSame($expected, $token->$method());
    }

    /**
     * @return array
     */
    public function providerAsXyz()
    {
        return [
            ['asString',   Token::STRING, 'abc',                 'abc'    ],
            ['asString',   Token::STRING, '',                    ''       ],

            ['asString',   Token::NUMBER, '123',                 '123'    ],
            ['asString',   Token::NUMBER, '0123',                '123'    ],
            ['asString',   Token::NUMBER, '-0123',               '-123'   ],
            ['asString',   Token::NUMBER, '1.2300',              '1.2300' ],
            ['asString',   Token::NUMBER, '1.234567890123',      '1.234567890123' ],
            ['asString',   Token::NUMBER, '-1.23',               '-1.23'  ],
            ['asString',   Token::NUMBER, '+1.23',               '1.23'   ],
            ['asString',   Token::NUMBER, '.23',                 '0.23'   ],
            ['asString',   Token::NUMBER, '',                    '0'      ],

        // TODO - work is needed on Token::asString to make these tests parse:
        //  ['asString',   Token::NUMBER, '1.234e1',             '12.34'  ],
        //  ['asString',   Token::NUMBER, '1.234000e15',         '1.234e15' ],
        //  ['asString',   Token::NUMBER, '0.999999e15',         '999999000000000' ],
        //  ['asString',   Token::NUMBER, '-1.234000e15',        '-1.234e15' ],
        //  ['asString',   Token::NUMBER, '-0.999999e15',        '-999999000000000'],
        //  ['asString',   Token::NUMBER, '1.0001e-15',          '0.0000000000000010001' ],
        //  ['asString',   Token::NUMBER, '0.999e-16',           '9.99e-16' ],
        //  ['asString',   Token::NUMBER, '-1.0001e-15',         '-0.0000000000000010001' ],
        //  ['asString',   Token::NUMBER, '-0.999e-16',          '-9.99e-16' ],

            ['asString',   Token::HEX,    '68656c6c6f21',        'hello!' ],
            ['asString',   Token::BIN,    '0111111000100011',    '~#'     ],

            ['asNumber',   Token::STRING, '123',                 123   ],
            ['asNumber',   Token::NUMBER, '456',                 456   ],
            ['asNumber',   Token::HEX,    'fffe',                65534 ],
            ['asNumber',   Token::BIN,    '10100101',            165   ],

            ['asDate',     Token::STRING, '0',                   '0000-00-00'],
            ['asDate',     Token::STRING, '0000-00-00',          '0000-00-00'],
            ['asDate',     Token::STRING, '1970-08-12',          '1970-08-12'],
            ['asDate',     Token::STRING, '2001-12-31',          '2001-12-31'],

            ['asTime',     Token::STRING, '0',                   '00:00:00'  ],
            ['asTime',     Token::STRING, '00:00:00',            '00:00:00'  ],
            ['asTime',     Token::STRING, '17:45:59',            '17:45:59'  ],

            ['asDateTime', Token::STRING, '0',                   '0000-00-00 00:00:00'],
            ['asDateTime', Token::STRING, '0000-00-00',          '0000-00-00 00:00:00'],
            ['asDateTime', Token::STRING, '1970-08-12',          '1970-08-12 00:00:00'],
            ['asDateTime', Token::STRING, '2001-12-31',          '2001-12-31 00:00:00'],
            ['asDateTime', Token::STRING, '0000-00-00 00:00:00', '0000-00-00 00:00:00'],
            ['asDateTime', Token::STRING, '1970-08-12 13:34:45', '1970-08-12 13:34:45'],
            ['asDateTime', Token::STRING, '2001-12-31 23:59:59', '2001-12-31 23:59:59'],
        ];
    }

    /**
     * @dataProvider providerAsXyzError
     * @expectedException \Exception
     * @param string $method
     * @param string $type
     * @param string $arg
     */
    public function testAsXyzError($method, $type, $arg)
    {
        (new Token($type, $arg))->$method();
    }

    /**
     * @return array
     */
    public function providerAsXyzError()
    {
        return [
            ['asDate',     Token::STRING, 'abc'       ],
            ['asDate',     Token::STRING, '1970'      ],
            ['asDate',     Token::STRING, '19700812'  ],
            ['asDate',     Token::STRING, '1970/08/12'],

            ['asTime',     Token::STRING, 'abc'       ],
            ['asTime',     Token::STRING, '000000'    ],
            ['asTime',     Token::STRING, '1745'      ],
            ['asTime',     Token::STRING, '174559'    ],

            ['asDateTime', Token::STRING, 'abc'           ],
            ['asDateTime', Token::STRING, '19700812'      ],
            ['asDateTime', Token::STRING, '1970/08/12'    ],
            ['asDateTime', Token::STRING, '197008120000'  ],
            ['asDateTime', Token::STRING, '19700812000000'],

            ['asString',   Token::SYMBOL, 'abc'       ],

            ['asNumber',   Token::SYMBOL, 'abc'       ],
        ];
    }

    /**
     * @param Token $token
     * @param string $expected
     * @dataProvider provideToDebugString
     */
    public function testToDebugString(Token $token, $expected)
    {
        $this->assertEquals($expected, $token->toDebugString());
    }

    /**
     * @return array
     */
    public function provideToDebugString()
    {
        $text = 'foo';

        return [
            // Token, Expected output
            [new Token(Token::BIN, $text), 'bin[foo]'],
            [new Token(Token::COMMENT, $text), 'comment[foo]'],
            [new Token(Token::CONDITIONAL_END, $text), 'conditional-end[foo]'],
            [new Token(Token::CONDITIONAL_START, $text), 'conditional-start[foo]'],
            [new Token(Token::EOF, $text), 'EOF[foo]'],
            [new Token(Token::HEX, $text), 'hex[foo]'],
            [new Token(Token::IDENTIFIER, $text), 'identifier[foo]'],
            [new Token(Token::NUMBER, $text), 'number[foo]'],
            [new Token(Token::SYMBOL, $text), 'symbol[foo]'],
            [new Token(Token::WHITESPACE, $text), 'whitespace[foo]'],
        ];
    }
}
