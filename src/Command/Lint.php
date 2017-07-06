<?php

namespace Graze\Morphism\Command;

use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Parse\TokenStream;

class Lint implements Argv\ConsumerInterface
{
    /** @var bool */
    private $verbose = false;

    /**
     * @param string $prog
     */
    public function consumeHelp($prog)
    {
        printf(
            "Usage: %s [OPTIONS] PATH ...\n" .
            "Checks all schema files below the specified paths for correctness. If no PATH\n" .
            "is given, checks standard input. By default output is only produced if errors\n" .
            "are detected.\n" .
            "\n" .
            "OPTIONS\n" .
            "  -h, -help, --help   display this message, and exit\n" .
            "  --[no-]verbose      include valid files in output; default: no\n" .
            "\n" .
            "EXIT STATUS\n" .
            "The exit status will be 1 if any errors were detected, or 0 otherwise.\n" .
            "",
            $prog
        );
    }

    /**
     * @param array $argv
     */
    public function argv(array $argv)
    {
        $argvParser = new Argv\Parser($argv);
        $argvParser->consumeWith($this);
    }

    /**
     * @param Argv\Option $option
     */
    public function consumeOption(Argv\Option $option)
    {
        switch ($option->getOption()) {
            case '--verbose':
            case '--no-verbose':
                $this->verbose = $option->bool();
                break;

            default:
                $option->unrecognised();
                break;
        }
    }

    /**
     * @param array $args
     */
    public function consumeArgs(array $args)
    {
        $this->paths = count($args) == 0 ? ['php://stdin'] : $args;
    }

    /**
     * @return bool
     */
    public function run()
    {
        $success = true;

        $engine = null;
        $collation = null;

        $errorFiles = [];

        foreach ($this->paths as $path) {
            $dump = new MysqlDump;

            $files = [];
            if (is_dir($path)) {
                if ($this->verbose) {
                    echo "$path\n";
                }
                foreach (new \GlobIterator("$path/*.sql") as $fileInfo) {
                    $files[] = $fileInfo->getPathname();
                }
            } else {
                $files[] = $path;
            }

            foreach ($files as $file) {
                $stream = TokenStream::newFromFile($file);
                try {
                    $dump->parse($stream);
                    if ($this->verbose) {
                        echo "OK    $file\n";
                    }
                } catch (\RuntimeException $e) {
                    $errorFiles[] = $file;
                    $message = $stream->contextualise($e->getMessage());
                    echo "ERROR $message\n";
                }
            }
        }

        return count($errorFiles) == 0;
    }
}
