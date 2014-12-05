<?php

namespace Graze\Morphism\Command;

use Graze\Morphism\Parse\Token;
use Graze\Morphism\Parse\MysqlDump;

class Diff implements Argv\Consumer
{
    private $output = null;
    private $engine = 'InnoDB';
    private $collation = null;
    private $createDatabase = true;
    private $dropDatabase = true;
    private $createTable = true;
    private $dropTable = true;
    private $alterEngine = true;
    private $path1 = null;
    private $path2 = null;

    public function consumeHelp($prog)
    {
        printf(
            "Usage: %s [OPTION]... PATH1 PATH2\n" .
            "Diff two database schemas, and output the necessary ALTER TABLE statements\n" .
            "to transform the schema defined by PATH1 to that defined by PATH2.\n" .
            "\n" .
            "If `-' is specified for PATH1 or PATH2, standard input will be read.\n" .
            "\n" .
            "OPTIONS\n" .
            "  -h, -help, --help      display this message, and exit\n" .
            "  --output=DIR           write statements to files in DIR\n" .
            "  --engine=ENGINE        set the default database engine\n" .
            "  --collation=COLLATION  set the default collation\n" .
            "  --[no-]quote-names     [do not] quote names with `...`\n" .
            "  --[no-]create-db       [do not] output CREATE DATABASEs (and associated CREATE TABLEs)\n" .
            "  --[no-]drop-db         [do not] output DROP DATABASE statements\n" .
            "  --[no-]create-table    [do not] output CREATE TABLE statements\n" .
            "  --[no-]drop-table      [do not] output DROP TABLE statements\n" .
            "  --[no-]alter-engine    [do not] output ALTER TABLE ... ENGINE=\n" .
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
            case '--create-db':
            case '--no-create-db':
                $this->createDatabase = $option->bool();
                break;
            case '--drop-db':
            case '--no-drop-db':
                $option->noValue();
                $this->dropDatabase = $option->bool();
                break;
            case '--create-table':
            case '--no-create-table':
                $this->createTable = $option->bool();
                break;
            case '--drop-table':
            case '--no-drop-table':
                $this->createTable = $option->bool();
                break;
            case '--alter-engine':
            case '--no-alter-engine':
                $this->alterEngine = $option->bool();
                break;
            default:
                $option->unrecognised();
                break;
        }
    }

    public function consumeArgs(array $args)
    {
        if (count($args) != 2) {
            throw new Argv\Exception("expected two PATHS");
        }
        $this->paths = $args;
    }

    public function run()
    {
        $dumps = [];

        try {
            foreach($this->paths as $i => $path) {
                if ($path == '-') {
                    $path = "php://stdin";
                }
                $dumps[$i] = MysqlDump::parseFromPaths([$path], $this->engine, $this->collation);
            }
        }
        catch(\RuntimeException $e) {
            throw $e;
        }
        catch(\Exception $e) {
            throw new \Exception($e->getMessage() . "\n\n" . $e->getTraceAsString());
        }

        echo $dumps[0]->diff(
            $dumps[1], [
                'createDatabase' => $this->createDatabase,
                'dropDatabase'   => $this->dropDatabase,
                'createTable'    => $this->createTable,
                'dropTable'      => $this->dropTable,
                'alterEngine'    => $this->alterEngine,
            ]
        );
    }
}


