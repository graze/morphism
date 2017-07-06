<?php

namespace Graze\Morphism\Command\Argv;

interface ConsumerInterface
{
    /**
     * @param Option $option
     * @return mixed
     */
    public function consumeOption(Option $option);

    /**
     * @param array $args
     * @return mixed
     */
    public function consumeArgs(array $args);
}
