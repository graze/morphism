<?php

namespace Graze\Morphism\Test\Parse;

use Graze\Morphism\Extractor\ExtractorFactory;
use Graze\Morphism\Parse\TokenStreamFactory;
use Illuminate\Filesystem\Filesystem;

abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    public function makeStream($text)
    {
        $streamFactory = new TokenStreamFactory(new ExtractorFactory(), new Filesystem());
        return $streamFactory->buildFromFile('data://text/plain;base64,' . base64_encode($text));
    }
}


