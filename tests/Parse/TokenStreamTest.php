<?php

namespace Graze\Morphism\Parse;

class TokenStreamTest extends \Graze\Morphism\Test\Parse\TestCase
{
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

    /** @dataProvider nextTokenProvider */
    public function testNextToken($text, $expectedType, $expectedValue)
    {
        $stream = $this->makeStream($text);
        $token = $stream->nextToken();
        $this->assertTokenEq($expectedType, $expectedValue, $token);
    }

    public function assertTokenEq($expectedType, $expectedValue, $token)
    {
        $this->assertTrue(
            $token->eq($expectedType, $expectedValue),
            "expected {$expectedType}[{$expectedValue}], but got " . $token->toDebugString()
        );
    }

    public function nextTokenProvider()
    {
        $sq = "'";
        $dq = '"';
        $bq = '`';
        $bs = "\\";

        return [
            [ '',          'EOF',    ''          ],

            // numbers
            [ '1',         'number', '1'         ],
            [ '123',       'number', '123'       ],
            [ '123.45',    'number', '123.45'    ],
            [ '.45',       'number', '.45'       ],
            [ '123.',      'number', '123.'      ],
            [ '-123',      'number', '-123'      ],
            [ '+123',      'number', '+123'      ],
            [ '1E23',      'number', '1E23'      ],
            [ '1e23',      'number', '1e23'      ],
            [ '1e+23',     'number', '1e+23'     ],
            [ '1e-23',     'number', '1e-23'     ],
            [ '+1.23e-17', 'number', '+1.23e-17' ],

            // whitespace
            [ " 1",  'number', 1],
            [ "\t1", 'number', 1],
            [ "\n1", 'number', 1],

            // comments
            [ "/*comment*/1",   'number', '1'],
            [ "/**/1",          'number', '1'],
            [ "-- comment\n1",  'number', '1'],
            [ "--\n1",          'number', '1'],
            [ "#comment\n1",    'number', '1'],

            // conditional comments
            [ "/*! 12345*/",      'number', '12345'],
            [ "/*!12345 12345*/", 'number', '12345'],

            // double quoted strings
            [ "{$dq}{$dq}",                     'string', ''],
            [ "{$dq}hello world{$dq}",          'string', 'hello world'],
            [ "{$dq}hello{$dq}{$dq}world{$dq}", 'string', "hello{$dq}world"],     // "" => "
            [ "{$dq}hello{$bs}{$bs}world{$dq}", 'string', "hello{$bs}world"],     // \\ => \
            [ "{$dq}hello{$bs}{$dq}world{$dq}", 'string', "hello{$dq}world"],     // \" => "

            // single quoted strings
            [ "{$sq}{$sq}",                     'string', ''],
            [ "{$sq}hello{$sq}",                'string', 'hello'],
            [ "{$sq}hello{$sq}{$sq}world{$sq}", 'string', "hello{$sq}world"],     // '' => '
            [ "{$sq}hello{$bs}{$bs}world{$sq}", 'string', "hello{$bs}world"],     // \\ => \
            [ "{$sq}hello{$bs}{$sq}world{$sq}", 'string', "hello{$sq}world"],     // \' => '

            // backquoted identifiers
            [ "{$bq}{$bq}",                     'identifier', ''],
            [ "{$bq}hello{$bq}",                'identifier', 'hello'],
            [ "{$bq}hello{$bq}{$bq}world{$bq}", 'identifier', "hello{$bq}world"],      // `` => `
            [ "{$bq}hello{$bs}{$bs}world{$bq}", 'identifier', "hello{$bs}${bs}world"], // \\ => \\
            [ "{$bq}hello{$bs}nworld{$bq}",     'identifier', "hello{$bs}nworld"],     // \n => \n

            // hex literals
            [ "x''",                    "hex", "" ],
            [ "x'00'",                  "hex", "00" ],
            [ "x'0123456789abcdef'",    "hex", "0123456789abcdef" ],
            [ "x'0123456789ABCDEF'",    "hex", "0123456789ABCDEF" ],

            // binary literals
            [ "b''",            "bin", "" ],
            [ "b'0'",           "bin", "0" ],
            [ "b'00011011'",    "bin", "00011011" ],

            // unquoted identifiers
       //   [ '1_',       'identifier', '1_' ],     // TODO - make this pass
            [ '_',        'identifier', '_' ],
            [ '$',        'identifier', '$' ],
            [ 'a',        'identifier', 'a' ],
            [ 'abc',      'identifier', 'abc' ],
            [ 'abc123',   'identifier', 'abc123' ],
            [ '_abc',     'identifier', '_abc' ],
            [ '_123',     'identifier', '_123' ],
            [ '$_123abc', 'identifier', '$_123abc' ],

            // symbols
            [ "<=_", "symbol", "<=" ],
            [ ">=_", "symbol", ">=" ],
            [ "<>_", "symbol", "<>" ],
            [ "!=_", "symbol", "!=" ],
            [ ":=_", "symbol", ":=" ],
            [ "&&_", "symbol", "&&" ],
            [ "||_", "symbol", "||" ],
            [ "@@_", "symbol", "@@" ],
            [ "@_",  "symbol", "@" ],
            [ "+_",  "symbol", "+"  ],
            [ "-_",  "symbol", "-"  ],
            [ "*_",  "symbol", "*"  ],
            [ "/_",  "symbol", "/"  ],
            [ "%_",  "symbol", "%"  ],
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

    /** @dataProvider consumeProvider */
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

    public function consumeProvider()
    {
        return [
            ['create table t', 'create',          true,  'identifier', 'table'],
            ['create table t', 'create table',    true,  'identifier', 't'],
            ['create table t', 'drop',            false, 'identifier', 'create'],
            ['create table t', 'drop table',      false, 'identifier', 'create'],
            ['create table t', 'create database', false, 'identifier', 'create'],
            ['= "test"',       [['symbol', '=']], true,  'string', 'test'],
            ['();',            [['symbol', '('],
                                ['symbol', ')']], true,  'symbol', ';'],
        ];
    }

    /** @dataProvider peekProvider */
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
        
    public function peekProvider()
    {
        return [
            ['create table t', 'create',          true,  'identifier', 'create'],
            ['create table t', 'create table',    true,  'identifier', 'create'],
            ['create table t', 'drop',            false, 'identifier', 'create'],
            ['create table t', 'drop table',      false, 'identifier', 'create'],
            ['create table t', 'create database', false, 'identifier', 'create'],
            ['= "test"',       [['symbol', '=']], true,  'symbol', '='],
            ['();',            [['symbol', '('],
                                ['symbol', ')']], true,  'symbol', '('],
        ];
    }

    public function testExpectSucc()
    {
        $stream = $this->makeStream('create table t');
        $stream->expect('identifier', 'create');
    }

    /** @expectedException \Exception */
    public function testExpectFail()
    {
        $stream = $this->makeStream('create table t');
        $stream->expect('identifier', 'drop');
    }

    // TODO -
    // following methods are untested:
    //     expectName
    //     expectOpenParen 
    //     expectCloseParen
    //     expectNumber
    //     expectString
    //     expectStringExtended
    //     contextualise
}
