<?php

namespace Graze\Morphism\Command\Argv;

interface Consumer
{
    public function consumeOption(Option $option);
    public function consumeArgs(array $args);
}
