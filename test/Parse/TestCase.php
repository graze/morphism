<?php

namespace Graze\Morphism\Test\Parse;

use \Graze\Morphism\Parse\Token;
use \Graze\Morphism\Parse\TokenStream;

abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->_oldQuoteNames = Token::getQuoteNames();
        Token::setQuoteNames(true);
    }

    public function tearDown()
    {
        Token::setQuoteNames($this->_oldQuoteNames);
    }

    public function makeStream($text)
    {
        return TokenStream::newFromFile("data://text/plain;base64," . base64_encode($text));
    }
}


