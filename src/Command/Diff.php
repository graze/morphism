<?php

namespace Graze\Morphism\Command;

use Graze\Morphism\Parse\TokenStream;
use Graze\Morphism\Parse\Token;
use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Extractor;
use Graze\Morphism\Config;

class Diff implements Argv\Consumer
{
    private $engine = 'InnoDB';
    private $collation = null;
    private $quoteNames = true;
    private $createTable = true;
    private $dropTable = true;
    private $alterEngine = true;
    private $schemaPath = './schemas';
    private $configFile = null;
    private $connectionName = null;
    private $applyChanges = 'no';
    private $logFile = null;
    private $logSkipped = true;

    public function consumeHelp($prog)
    {
        printf(
            "Usage: %s [OPTION] CONFIG-FILE CONN\n" .
            "Diff database schemas, and output the necessary ALTER TABLE statements\n" .
            "to transform the schema found on the conection to that defined under the\n" .
            "schema path.\n" .
            "\n" .
            "GENERAL OPTIONS:\n" .
            "  -h, -help, --help      display this message, and exit\n" .
            "  --engine=ENGINE        set the default database engine\n" .
            "  --collation=COLLATION  set the default collation\n" .
            "  --[no-]quote-names     quote names with `...`; default: yes\n" .
            "  --[no-]create-table    output CREATE TABLE statements; default: yes\n" .
            "  --[no-]drop-table      output DROP TABLE statements; default: yes\n" .
            "  --[no-]alter-engine    output ALTER TABLE ... ENGINE=...; default: yes\n" .
            "  --schema-path=PATH     location of schemas; default: ./schemas\n" .
            "  --apply-changes=WHEN   apply changes (yes/no/confirm); default: no\n" .
            "  --log-file=FILE        log applied changes to FILE; default: none\n" .
            "  --[no-]log-skipped     include skipped queries (commented out); default: yes\n" .
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
            case '--engine':
                $this->engine = $option->required();
                break;

            case '--collation':
                $this->collation = $option->required();
                break;

            case '--quote-names':
            case '--no-quote-names':
                $this->quoteNames = $option->bool();
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

            case '--schema-path':
                $this->schemaPath = $option->required();
                break;

            case '--apply-changes':
                $applyChanges = $option->required();
                if (!in_array($applyChanges, ['yes', 'no', 'confirm'])) {
                    throw new Argv\Exception("unknown value");
                }
                $this->applyChanges = $applyChanges;
                break;

            case '--log-file':
                $this->logFile = $option->required();
                break;

            case '--log-skipped':
            case '--no-log-skipped':
                $this->logSkipped = $option->bool();
                break;

            default:
                $option->unrecognised();
                break;
        }
    }

    public function consumeArgs(array $args)
    {
        if (count($args) != 2) {
            throw new Argv\Exception("expected CONFIG-FILE and CONN as arguments");
        }
        $this->configFile = $args[0];
        $this->connectionName = $args[1];
    }

    private function getConnection()
    {
        $config = new Config($this->configFile);
        $config->parse();
        return $config->getConnection($this->connectionName);
    }

    private function getCurrentSchema($connection)
    {
        $extractor = new Extractor($connection);
        $extractor->setDatabases([$this->connectionName]);
        $extractor->setCreateDatabases(false);
        $extractor->setQuoteNames($this->quoteNames);

        $text = '';
        foreach($extractor->extract() as $query) {
            $text .= "$query;\n";
        }
        $stream = TokenStream::newFromText($text, '');

        $dump = new MysqlDump();
        $dump->parse($stream);

        return $dump;
    }

    private function getTargetSchema()
    {
        $path = $this->schemaPath . "/" . $this->connectionName;

        return MysqlDump::parseFromPaths(
            [$path],
            $this->engine,
            $this->collation
        );
    }

    private function applyChanges($connection, $diff)
    {
        if (count($diff) == 0) {
            return;
        }
        if ($this->applyChanges == 'no') {
            return;
        }

        $confirm = $this->applyChanges == 'confirm';
        $defaultResponse = 'y';
        $logHandle = null;

        if (!is_null($this->logFile)) {
            $logHandle = fopen($this->logFile, "w");
            if ($logHandle == false) {
                fprintf(STDERR, "Could not open log file for writing: {$this->logFile}\n");
                exit(1);
            }
        }

        foreach($diff as $query) {
            $response = $defaultResponse;
            $apply = false;

            if ($confirm) {
                echo "\n";
                echo "$query;\n\n";
                do {
                    echo "Apply this change? [y]es [n]o [a]ll [q]uit: ";
                    $response = fgets(STDIN);
                    if ($response === false) {
                        throw new \Exception("could not read response");
                    }
                    $response = rtrim($response);
                }
                while(!in_array($response, ['y', 'n', 'a', 'q']));
            }

            switch($response) {
                case 'y':
                    $apply = true; 
                    break;

                case 'n':
                    $apply = false;
                    break;

                case 'a':
                    $apply = true; 
                    $confirm = false;
                    $defaultResponse = 'y';
                    break;

                case 'q':
                    $apply = false;
                    $confirm = false;
                    $defaultResponse = 'n';
                    break;
            }

            if ($apply) {
                if ($logHandle) {
                    fwrite($logHandle, "$query;\n\n");
                }
                $connection->executeQuery($query);
            }
            else if ($logHandle && $this->logSkipped) {
                fwrite($logHandle, 
                    "/********** SKIPPED **********\n" .
                    "$query;\n" .
                    "******************************/\n" .
                    "\n"
                );
            }
        }
    }

    public function run()
    {
        try {
            Token::setQuoteNames($this->quoteNames);
            $connection = $this->getConnection();
            $currentSchema = $this->getCurrentSchema($connection);
            $targetSchema = $this->getTargetSchema();

            $diff = $currentSchema->diff(
                $targetSchema,
                [
                    'createDatabase' => false,
                    'dropDatabase'   => false,
                    'createTable'    => $this->createTable,
                    'dropTable'      => $this->dropTable,
                    'alterEngine'    => $this->alterEngine,
                ]
            );

            foreach($diff as $query) {
                echo "$query;\n\n";
            }

            $this->applyChanges($connection, $diff);
        }
        catch(\RuntimeException $e) {
            throw $e;
        }
        catch(\Exception $e) {
            throw new \Exception($e->getMessage() . "\n\n" . $e->getTraceAsString());
        }
    }
}


