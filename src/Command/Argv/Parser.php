<?php

namespace Graze\Morphism\Command\Argv;

class Parser
{
    /** @var string|null */
    private $prog = null;
    /** @var array|null */
    private $argv = null;
    /** @var array */
    private $args = [];

    /**
     * Parser constructor.
     * @param array $argv
     */
    public function __construct(array $argv)
    {
        $this->prog = basename(array_shift($argv));
        $this->argv = $argv;
    }

    /**
     * @return string|null
     */
    public function getProg()
    {
        return $this->prog;
    }

    /**
     * @param ConsumerInterface $consumer
     */
    public function consumeWith(ConsumerInterface $consumer)
    {
        try {
            while (count($this->argv) > 0) {
                $opt = array_shift($this->argv);
                if ($opt == '--') {
                    $this->args = array_merge($this->args, $argv);
                    $this->argv = [];
                } elseif (in_array($opt, ['-h', '-help', '--help'])) {
                    $consumer->consumeHelp($this->prog);
                    exit(0);
                } elseif (strlen($opt) > 1 && $opt[0] == '-') {
                    $eqPos = strpos($opt, '=');
                    if ($eqPos === false) {
                        $option = new Option($opt);
                    } else {
                        $option = new Option(
                            substr($opt, 0, $eqPos),
                            // substr($s, strlen($s)) returns false, so we must
                            // cast to string to ensure we get '' as any sane
                            // person would expect
                            (string)substr($opt, $eqPos + 1)
                        );
                    }
                    try {
                        $consumer->consumeOption($option);
                    } catch (Exception $e) {
                        throw new Exception("option `{$option->getOption()}`: " . $e->getMessage());
                    }
                } else {
                    $this->args[] = $opt;
                }
            }

            $consumer->consumeArgs($this->args);
        } catch (Exception $e) {
            $this->usage($e->getMessage());
            exit(1);
        }
    }

    /**
     * @param string $msg
     */
    public function usage($msg = null)
    {
        fprintf(
            STDERR,
            "%s: %s\n" .
            "Try `%s --help' for more information.\n",
            $this->prog,
            $msg,
            $this->prog
        );
    }
}
