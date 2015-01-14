<?php

namespace Graze\Morphism\Command;

use Graze\Morphism\Parse\TokenStream;
use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Extractor;
use Graze\Morphism\Config;

class Fastdump implements Argv\Consumer
{
    private $quoteNames = true;
    private $configFile = null;
    private $schemaPath = './schemas';
    private $write = false;
    private $connectionNames = [];

    public function consumeHelp($prog)
    {
        printf(
            "Usage: %s [OPTIONS] CONFIG-FILE CONN [CONN ...]\n" .
            "Dump specified database schemas. This tool is considerably faster than mysqldump\n" .
            "(especially for large schemas).\n" .
            "\n" .
            "OPTIONS\n" .
            "  -h, -help, --help   display this message, and exit\n" .
            "  --[no-]quote-names  [do not] quote names with `...`; default: no\n" .
            "  --schema-path=PATH  location of schemas; default: ./schemas\n" .
            "  --[no-]write        write schema files to schema path; default: no\n" .
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
            case '--quote-names':
            case '--no-quote-names':
                $this->quoteNames = $option->bool();
                break;
            
            case '--schema-path':
                $this->schemaPath = $option->required();
                break;

            case '--write':
            case '--no-write':
                $this->write = $option->bool();
                break;
            
            default:
                $option->unrecognised();
                break;
        }
    }

    public function consumeArgs(array $args)
    {
        if (count($args) < 2) {
            throw new Argv\Exception("expected CONFIG-FILE CONN [CONN ...]");
        }
        $this->configFile = array_shift($args);
        $this->connectionNames = $args;
    }

    public function run()
    {
        $config = new Config($this->configFile);
        $config->parse();

        foreach($this->connectionNames as $connectionName) {
            // the connection name does double duty as the database name
            // but it should really be specified in the config file itself
            $databaseName = $connectionName;

            if (!$this->write) {
                echo "\n";
                echo "/********* Connection: $connectionName Database: $databaseName *********/\n";
                echo "\n";
            }

            $dbh = $config->getConnection($connectionName);

            $extractor = new Extractor($dbh);
            $extractor->setDatabases([$databaseName]);
            $extractor->setCreateDatabases(false);
            $extractor->setQuoteNames($this->quoteNames);
            $text = '';
            foreach($extractor->extract() as $query) {
                $text .= "$query;\n";
            }
            $stream = TokenStream::newFromText($text, '');

            $dump = new MysqlDump();
            try {
                $dump->parse($stream);
            }
            catch(\RuntimeException $e) {
                throw new \RuntimeException($stream->contextualise($e->getMessage()));
            }
            catch(\Exception $e) {
                throw new \Exception($e->getMessage() . "\n\n" . $e->getTraceAsString());
            }

            if ($this->write) {
                $output = "{$this->schemaPath}/$databaseName";

                if (!is_dir($output)) {
                    if (!@mkdir($output, 0777, true)) {
                        throw new \RuntimeException("could not make directory $output");
                    }
                }
                $database = reset($dump->databases);
                foreach($database->tables as $table) {
                    $path = "$output/{$table->name}.sql";
                    $text = '';
                    foreach($table->getDDL() as $query) {
                        $text .= "$query;\n\n";
                    }
                    if (false === @file_put_contents($path, $text)) {
                        throw new \RuntimeException("could not write $path");
                    }
                    fprintf(STDERR, "wrote $path\n");
                }
            }
            else {
                foreach($dump->getDDL() as $query) {
                    echo "$query;\n\n";
                }
            }
        }
    }
}
