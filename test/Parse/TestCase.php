<?php

namespace Graze\Morphism\Test\Parse;

use Graze\Morphism\ExtractorFactory;
use \Graze\Morphism\Parse\Token;
use \Graze\Morphism\Parse\TokenStream;
use Graze\Morphism\Parse\TokenStreamFactory;
use Illuminate\Filesystem\Filesystem;

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
        $streamFactory = new TokenStreamFactory(new ExtractorFactory(), new Filesystem());
        return $streamFactory->buildFromFile('data://text/plain;base64,' . base64_encode($text));
    }
}


