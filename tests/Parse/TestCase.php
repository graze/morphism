<?php

namespace Graze\Morphism\Test\Parse;

use \Graze\Morphism\Parse\Token;
use \Graze\Morphism\Parse\TokenStream;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCase extends PHPUnitTestCase
{
    /** @var bool */
    protected $oldQuoteNames;

    public function setUp()
    {
        $this->oldQuoteNames = Token::getQuoteNames();
        Token::setQuoteNames(true);
    }

    public function tearDown()
    {
        Token::setQuoteNames($this->oldQuoteNames);
    }

    /**
     * @param string $text
     * @return TokenStream
     */
    public function makeStream($text)
    {
        return TokenStream::newFromFile("data://text/plain;base64," . base64_encode($text));
    }
}
