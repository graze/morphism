<?php

namespace Graze\Morphism\Dump;

use Graze\Morphism\Dump\Output\OutputInterface;
use Graze\Morphism\Parse\TokenStream;

interface DumperInterface
{
    /**
     * @param TokenStream $stream
     * @param OutputInterface $output
     *
     * @return mixed
     */
    public function dump(TokenStream $stream, OutputInterface $output);
}
