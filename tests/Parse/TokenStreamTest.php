<?php

namespace Graze\Morphism\Parse;

use Graze\Morphism\Test\Parse\TestCase;
use RuntimeException;

class TokenStreamTest extends TestCase
{
    public function testNewFromText()
    {
        $stream = TokenStream::newFromText('', '');
        $this->assertThat($stream, $this->isInstanceOf(__NAMESPACE__ . '\TokenStream'));
    }

    public function testNewFromFile()
    {
        $stream = TokenStream::newFromFile("/dev/null");
        $this->assertThat($stream, $this->isInstanceOf(__NAMESPACE__ . '\TokenStream'));
    }

    /** @expectedException \Exception */
    public function testNewFromFileNotFound()
    {
        TokenStream::newFromFile(dirname(__FILE__) . "/file_not_found");
    }

    /**
     * @param string $expectedType
     * @param mixed $expectedValue
     * @param string $token
     */
    public function assertTokenEq($expectedType, $expectedValue, $token)
    {
        $this->assertTrue(
            $token->eq($expectedType, $expectedValue),
            "expected {$expectedType}[{$expectedValue}], but got " . $token->toDebugString()
        );
    }

    /**
     * @dataProvider nextTokenProvider
     * @param string $text
     * @param string $expectedType
     * @param mixed $expectedNextTokenValue
     */
    public function testNextToken($text, $expectedType, $expectedNextTokenValue)
    {
        $stream = $this->makeStream($text);
        $token = $stream->nextToken();
        $this->assertTokenEq($expectedType, $expectedNextTokenValue, $token);
    }

    /**
     * @return array
     */
    public function nextTokenProvider()
    {
        $sq = "'";
        $dq = '"';
        $bq = '`';
        $bs = "\\";

        return [
            [ '',          Token::EOF,    ''          ],

            // numbers
            [ '1',         Token::NUMBER, '1'         ],
            [ '123',       Token::NUMBER, '123'       ],
            [ '123.45',    Token::NUMBER, '123.45'    ],
            [ '.45',       Token::NUMBER, '.45'       ],
            [ '123.',      Token::NUMBER, '123.'      ],
            [ '-123',      Token::NUMBER, '-123'      ],
            [ '+123',      Token::NUMBER, '+123'      ],
            [ '1E23',      Token::NUMBER, '1E23'      ],
            [ '1e23',      Token::NUMBER, '1e23'      ],
            [ '1e+23',     Token::NUMBER, '1e+23'     ],
            [ '1e-23',     Token::NUMBER, '1e-23'     ],
            [ '+1.23e-17', Token::NUMBER, '+1.23e-17' ],

            // whitespace
            [ " 1",  Token::NUMBER, 1],
            [ "\t1", Token::NUMBER, 1],
            [ "\n1", Token::NUMBER, 1],

            // comments
            [ "/*comment*/1",   Token::NUMBER, '1'],
            [ "/**/1",          Token::NUMBER, '1'],
            [ "-- comment\n1",  Token::NUMBER, '1'],
            [ "--\n1",          Token::NUMBER, '1'],
            [ "#comment\n1",    Token::NUMBER, '1'],

            // conditional comments
            [ "/*! 12345*/",      Token::NUMBER, '12345'],
            [ "/*!12345 12345*/", Token::NUMBER, '12345'],

            // double quoted strings
            [ "{$dq}{$dq}",                     Token::STRING, ''],
            [ "{$dq}hello world{$dq}",          Token::STRING, 'hello world'],
            [ "{$dq}hello{$dq}{$dq}world{$dq}", Token::STRING, "hello{$dq}world"],     // "" => "
            [ "{$dq}hello{$bs}{$bs}world{$dq}", Token::STRING, "hello{$bs}world"],     // \\ => \
            [ "{$dq}hello{$bs}{$dq}world{$dq}", Token::STRING, "hello{$dq}world"],     // \" => "

            // single quoted strings
            [ "{$sq}{$sq}",                     Token::STRING, ''],
            [ "{$sq}hello{$sq}",                Token::STRING, 'hello'],
            [ "{$sq}hello{$sq}{$sq}world{$sq}", Token::STRING, "hello{$sq}world"],     // '' => '
            [ "{$sq}hello{$bs}{$bs}world{$sq}", Token::STRING, "hello{$bs}world"],     // \\ => \
            [ "{$sq}hello{$bs}{$sq}world{$sq}", Token::STRING, "hello{$sq}world"],     // \' => '

            // backquoted identifiers
            [ "{$bq}{$bq}",                     Token::IDENTIFIER, ''],
            [ "{$bq}hello{$bq}",                Token::IDENTIFIER, 'hello'],
            [ "{$bq}hello{$bq}{$bq}world{$bq}", Token::IDENTIFIER, "hello{$bq}world"],      // `` => `
            [ "{$bq}hello{$bs}{$bs}world{$bq}", Token::IDENTIFIER, "hello{$bs}${bs}world"], // \\ => \\
            [ "{$bq}hello{$bs}nworld{$bq}",     Token::IDENTIFIER, "hello{$bs}nworld"],     // \n => \n

            // hex literals
            [ "x''",                    Token::HEX, "" ],
            [ "x'00'",                  Token::HEX, "00" ],
            [ "x'0123456789abcdef'",    Token::HEX, "0123456789abcdef" ],
            [ "x'0123456789ABCDEF'",    Token::HEX, "0123456789ABCDEF" ],
            [ "0x0123456789abcdef",     Token::HEX, "0123456789abcdef" ],
            [ "0x0123456789ABCDEF",     Token::HEX, "0123456789ABCDEF" ],

            // binary literals
            [ "b''",            Token::BIN, "" ],
            [ "b'0'",           Token::BIN, "0" ],
            [ "b'00011011'",    Token::BIN, "00011011" ],

            // unquoted identifiers
       //   [ '1_',       Token::IDENTIFIER, '1_' ],     // TODO - make this pass
            [ '_',        Token::IDENTIFIER, '_' ],
            [ '$',        Token::IDENTIFIER, '$' ],
            [ 'a',        Token::IDENTIFIER, 'a' ],
            [ 'abc',      Token::IDENTIFIER, 'abc' ],
            [ 'abc123',   Token::IDENTIFIER, 'abc123' ],
            [ '_abc',     Token::IDENTIFIER, '_abc' ],
            [ '_123',     Token::IDENTIFIER, '_123' ],
            [ '$_123abc', Token::IDENTIFIER, '$_123abc' ],

            // symbols
            [ "<=_", Token::SYMBOL, "<=" ],
            [ ">=_", Token::SYMBOL, ">=" ],
            [ "<>_", Token::SYMBOL, "<>" ],
            [ "!=_", Token::SYMBOL, "!=" ],
            [ ":=_", Token::SYMBOL, ":=" ],
            [ "&&_", Token::SYMBOL, "&&" ],
            [ "||_", Token::SYMBOL, "||" ],
            [ "@@_", Token::SYMBOL, "@@" ],
            [ "@_",  Token::SYMBOL, "@" ],
            [ "+_",  Token::SYMBOL, "+"  ],
            [ "-_",  Token::SYMBOL, "-"  ],
            [ "*_",  Token::SYMBOL, "*"  ],
            [ "/_",  Token::SYMBOL, "/"  ],
            [ "%_",  Token::SYMBOL, "%"  ],
        ];
    }

    /**
     * @param string $text
     * @dataProvider provideBadLogicNextToken
     * @expectedException LogicException
     */
    public function testBadLogicNextToken($text)
    {
        $stream = $this->makeStream($text);
        $token = $stream->nextToken();
    }

    /**
     * @return array
     */
    public function provideBadLogicNextToken()
    {
        return [
            // All of these are explicitly not valid and will result in a "Lexer is confused by ..." message.
            ['?'],
            ['['],
            [']'],
            ['\\'],
            ['{'],
            ['}'],
            // This item covers the fall through value for any characters not explicitly listed
            [chr(0)],
        ];
    }

    /**
     * @param string $text
     * @dataProvider provideBadRuntimeNextToken
     * @expectedException RuntimeException
     */
    public function testBadRuntimeNextToken($text)
    {
        $stream = $this->makeStream($text);
        $token = $stream->nextToken();
    }

    /**
     * @return array
     */
    public function provideBadRuntimeNextToken()
    {
        return [
            // Unterminated quoted identifier
            ['`foo'],
            // Unexpected end of comment
            ['*/'],
        ];
    }

    public function testRewind()
    {
        $stream = $this->makeStream("create table t (x int, y int)");
        $stream->nextToken();
        $mark = $stream->getMark();
        $token11 = $stream->nextToken();
        $token12 = $stream->nextToken();
        $stream->rewind($mark);
        $token21 = $stream->nextToken();
        $token22 = $stream->nextToken();

        $this->assertTokenEq($token21->type, $token21->text, $token11);
        $this->assertTokenEq($token22->type, $token22->text, $token12);
    }

    /**
     * @dataProvider consumeProvider
     * @param string $text
     * @param mixed $spec
     * @param bool $success
     * @param string $type
     * @param string $value
     */
    public function testConsume($text, $spec, $success, $type, $value)
    {
        $stream = $this->makeStream($text);
        $this->assertSame(
            (bool)$success,
            (bool)$stream->consume($spec),
            "consume did not return " . ($success ? 'true' : 'false')
        );
        $token = $stream->nextToken();
        $this->assertTokenEq($type, $value, $token);
    }

    /**
     * @return array
     */
    public function consumeProvider()
    {
        return [
            ['create table t', 'create',          true,  Token::IDENTIFIER, 'table'],
            ['create table t', 'create table',    true,  Token::IDENTIFIER, 't'],
            ['create table t', 'drop',            false, Token::IDENTIFIER, 'create'],
            ['create table t', 'drop table',      false, Token::IDENTIFIER, 'create'],
            ['create table t', 'create database', false, Token::IDENTIFIER, 'create'],
            ['= "test"',       [[Token::SYMBOL, '=']], true,  Token::STRING, 'test'],
            ['();',            [[Token::SYMBOL, '('],
                                [Token::SYMBOL, ')']], true,  Token::SYMBOL, ';'],
        ];
    }

    /**
     * @dataProvider peekProvider
     * @param string $text
     * @param mixed $spec
     * @param bool $success
     * @param string $type
     * @param string $value
     */
    public function testPeek($text, $spec, $success, $type, $value)
    {
        $stream = $this->makeStream($text);
        $this->assertSame(
            (bool)$success,
            (bool)$stream->peek($spec),
            "peek did not return " . ($success ? 'true' : 'false')
        );
        $token = $stream->nextToken();
        $this->assertTokenEq($type, $value, $token);
    }

    /**
     * @return array
     */
    public function peekProvider()
    {
        return [
            ['create table t', 'create',          true,  Token::IDENTIFIER, 'create'],
            ['create table t', 'create table',    true,  Token::IDENTIFIER, 'create'],
            ['create table t', 'drop',            false, Token::IDENTIFIER, 'create'],
            ['create table t', 'drop table',      false, Token::IDENTIFIER, 'create'],
            ['create table t', 'create database', false, Token::IDENTIFIER, 'create'],
            ['= "test"',       [[Token::SYMBOL, '=']], true,  Token::SYMBOL, '='],
            ['();',            [[Token::SYMBOL, '('],
                                [Token::SYMBOL, ')']], true,  Token::SYMBOL, '('],
        ];
    }

    public function testExpectSucc()
    {
        $stream = $this->makeStream('create table t');
        $stream->expect(Token::IDENTIFIER, 'create');
    }

    /** @expectedException \Exception */
    public function testExpectFail()
    {
        $stream = $this->makeStream('create table t');
        $stream->expect(Token::IDENTIFIER, 'drop');
    }

    /**
     * @param string $func
     * @param string $token
     * @param mixed $expected
     * @param bool $throwsException
     * @dataProvider provideExpectedTokenType
     */
    public function testExpectedTokenType($func, $token, $expected, $throwsException)
    {
        $stream = $this->makeStream($token);
        if ($throwsException) {
            $this->setExpectedException(RuntimeException::class);
            $stream->$func();
        } else {
            $result = $stream->$func();
            $this->assertEquals($expected, $result);
        }
    }

    /**
     * @return array
     */
    public function provideExpectedTokenType()
    {
        return [
            // [ function name, token, expected value, should it throw a RuntimeException? ]

            [ 'expectCloseParen',   ')',    ')',    false],
            [ 'expectCloseParen',   'a',    null,   true],

            [ 'expectOpenParen',    '(',    '(',    false],
            [ 'expectOpenParen',    'a',    null,   true],

            [ 'expectName',         'foo',  'foo',  false],
            [ 'expectName',         '1',    null,   true],

            [ 'expectNumber',       '1',    1,      false],
            [ 'expectNumber',       'a',    null,   true],

            // An embedded string
            [ 'expectString',       "'a'",  "a",    false],
            [ 'expectString',       'a',    null,   true],

            [ 'expectStringExtended',       "'a'",  "a",    false],
            [ 'expectStringExtended',       "x'68656c6c6f21'",      'hello!',   false],
            [ 'expectStringExtended',       "X'68656c6c6f21'",      'hello!',   false],
            [ 'expectStringExtended',       '0x68656c6c6f21',      'hello!',   false],
            [ 'expectStringExtended',       "b'0111111000100011'",  '~#',       false],
            [ 'expectStringExtended',       'a',    null,   true],

        ];
    }

    // TODO -
    // following methods are untested:
    //     contextualise
}
