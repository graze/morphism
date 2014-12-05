<?php

namespace Graze\Morphism\Command\Argv;

class Option
{
    private $option;
    private $value;

    public function __construct($option, $value = null)
    {
        $this->option = $option;
        $this->value = $value;
    }

    public function getOption()
    {
        return $this->option;
    }

    public function noValue()
    {
        if (!is_null($this->value)) {
            throw new Exception("option `{$this->option}' does not allow an argument");
        }
    }

    public function required()
    {
        if (is_null($this->value)) {
            throw new Exception("option `{$this->option}' requires an argument");
        }
        return $this->value;
    }

    public function optional()
    {
        return $this->value;
    }

    public function bool()
    {
        $this->noValue();
        return substr($this->option, 0, 5) === '--no-' ? false : true;
    }

    public function unrecognised()
    {
        throw new Exception("unrecognised option `{$this->option}'");
    }
}

