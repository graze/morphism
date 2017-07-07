<?php

namespace Graze\Morphism\Command\Argv;

class Option
{
    /** @var string */
    private $option;
    /** @var string|null */
    private $value;

    /**
     * Option constructor.
     * @param string $option
     * @param string $value
     */
    public function __construct($option, $value = null)
    {
        $this->option = $option;
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getOption()
    {
        return $this->option;
    }

    public function noValue()
    {
        if (!is_null($this->value)) {
            throw new Exception("does not allow an argument");
        }
    }

    /**
     * @return null|string
     */
    public function required()
    {
        if (is_null($this->value)) {
            throw new Exception("requires an argument");
        }
        return $this->value;
    }

    /**
     * @param string $default
     * @return string|null
     */
    public function optional($default = null)
    {
        return is_null($this->value) ? $default : $this->value;
    }

    /**
     * @return bool
     */
    public function bool()
    {
        $this->noValue();
        return substr($this->option, 0, 5) === '--no-' ? false : true;
    }

    public function unrecognised()
    {
        throw new Exception("unrecognised option");
    }
}
