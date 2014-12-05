<?php

namespace Graze\Morphism\Command;

use Graze\Morphism\Parse\Token;
use Graze\Morphism\Parse\MysqlDump;

class Normalise implements Argv\Consumer
{
    private $output = null;
    private $engine = 'InnoDB';
    private $collation = null;
    private $paths = [];

    public function argv(array $argv)
    {
        $argvParser = new Argv\Parser($argv);
        $argvParser->consumeWith($this);
    }

    public function consumeHelp($prog)
    {
        printf(
            "Usage: %s [OPTION]... [PATH]...\n" .
            "Read MySQL CREATE TABLE statements, and write out canonical versions.\n" .
            "\n" .
            "OPTIONS\n" .
            "  -h, -help, --help      display this message, and exit\n" .
            "  --output=DIR           write statements to files in DIR\n" .
            "  --engine=ENGINE        set the default database engine\n" .
            "  --collation=COLLATION  set the default collation\n" .
            "  --[no-]quote-names     [do not] quote names with `...`\n" .
            "\n" .
            "Each PATH may specify a mysqldump file, or a directory of such files.\n" .
            "If no PATH is given, standard input will be read.\n" .
            "\n" .
            "If --output is specified, one file for each CREATE TABLE will be created in\n" .
            "the specified directory, otherwise they will all be written to standard out.\n" .
            "",
            $prog
        );
    }

    public function consumeOption(Argv\Option $option)
    {
        switch($option->getOption()) {
            case '--output':
                $this->output = $option->required();
                break;
            case '--engine':
                $this->engine = $option->required();
                break;
            case '--collation':
                $this->collation = $option->required();
                break;
            case '--quote-names':
            case '--no-quote-names':
                Token::setQuoteNames($option->bool());
                break;
            default:
                $option->unrecognised();
                break;
        }
    }

    public function consumeArgs(array $args)
    {
        $this->paths = $args;
    }

    public function run()
    {
        $output           = $this->output;
        $defaultEngine    = $this->engine;
        $defaultCollation = $this->collation;
        $paths            = $this->paths;

        if (count($paths) == 0) {
            $paths = ["php://stdin"];
        }

        try {
            $dump = MysqlDump::parseFromPaths($paths, $defaultEngine, $defaultCollation);
        }
        catch(\RuntimeException $e) {
            throw $e;
        }
        catch(\Exception $e) {
            throw new \Exception($e->getMessage() . "\n\n" . $e->getTraceAsString());
        }

        $n = count($dump->databases);
        if ($n !== 1) {
            throw new \RuntimeException("this tool will only process one database per dump, but $n were found");
        }

        if (!is_null($output)) {
            if (!is_dir($output)) {
                if (!@mkdir($output)) {
                    throw new \RuntimeException("could not make directory $output");
                }
            }
            $database = reset($dump->databases);
            foreach($database->tables as $table) {
                $path = "$output/{$table->name}.sql";
                if (false === @file_put_contents($path, $table->toString() . "\n")) {
                    throw new \RuntimeException("could not write $path");
                }
                fprintf(STDERR, "wrote $path\n");
            }
        }
        else {
            echo $dump->toString();
        }
    }
}

