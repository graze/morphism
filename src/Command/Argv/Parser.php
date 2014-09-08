<?php

namespace Graze\Morphism\Command\Argv;

class Parser
{
    private $prog = null;
    private $argv = null;
    private $args = [];

    public function __construct(array $argv)
    {
        $this->prog = basename(array_shift($argv));
        $this->argv = $argv;
    }

    public function getProg()
    {
        return $this->prog;
    }

    public function consumeWith(Consumer $consumer)
    {
        try {
            while(count($this->argv) > 0) {
                $opt = array_shift($this->argv);
                if ($opt == '--') {
                    $this->args = array_merge($this->args, $argv);
                    $this->argv = [];
                }
                else if (in_array($opt, ['-h', '-help', '--help'])) {
                    $consumer->consumeHelp($this->prog);
                    exit(0);
                }
                else if (substr($opt, 0, 1) == '-') {
                    $eqPos = strpos($opt, '=');
                    if ($eqPos === false) {
                        $option = new Option($opt);
                    }
                    else {
                        $option = new Option(substr($opt, 0, $eqPos), substr($opt, $eqPos + 1));
                    }
                    $consumer->consumeOption($option);
                }
                else {
                    $this->args[] = $opt;
                }
            }

            $consumer->consumeArgs($this->args);
        }
        catch(Exception $e) {
            $this->usage($e->getMessage());
            exit(1);
        }
    }

    public function usage($msg = null)
    {
        fprintf(STDERR,
            "%s: %s\n" .
            "Try `%s --help' for more information.\n",
            $this->prog,
            $msg,
            $this->prog
        );
    }
}
