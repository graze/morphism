<?php

namespace Graze\Morphism\Command;

use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Parse\TokenStream;

class Lint implements Argv\Consumer
{
    private $verbose = false;

    public function consumeHelp($prog)
    {
        printf(
            "Usage: %s [OPTIONS] PATH ...\n" .
            "Check all schema files below the specified paths for correctness.\n" .
            "\n" .
            "OPTIONS\n" .
            "  -h, -help, --help   display this message, and exit\n" .
            "  --[no-]verbose      include valid files in output; default: no\n" .
            "",
            $prog
        );
    }

    public function argv(array $argv)
    {
        $argvParser = new Argv\Parser($argv);
        $argvParser->consumeWith($this);
    }

    public function consumeOption(Argv\Option $option)
    {
        switch($option->getOption()) {
            case '--verbose':
            case '--no-verbose':
                $this->verbose = $option->bool();
                break;

            default:
                $option->unrecognised();
                break;
        }
    }

    public function consumeArgs(array $args)
    {
        $this->paths = count($args) == 0 ? ['php://stdin'] : $args;
    }

    public function run()
    {
        $success = true;

        $engine = null;
        $collation = null;

        $errorFiles = [];

        foreach($this->paths as $path) {
            $dump = new MysqlDump;

            $files = [];
            if (is_dir($path)) {
                if ($this->verbose) {
                    echo "$path\n";
                }
                foreach(new \GlobIterator("$path/*.sql") as $fileInfo) {
                    $files[] = $fileInfo->getPathname();
                }
            }
            else {
                $files[] = $path;
            }

            foreach($files as $file) {
                $stream = TokenStream::newFromFile($file);
                try {
                    $dump->parse($stream);
                    if ($this->verbose) {
                        echo "OK    $file\n";
                    }
                }
                catch(\RuntimeException $e) {
                    $errorFiles[] = $file;
                    $message = $stream->contextualise($e->getMessage());
                    echo "ERROR $message\n";
                }
            }
        }

        return count($errorFiles) == 0;
    }
}
