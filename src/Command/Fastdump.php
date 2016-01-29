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
    private $schemaPath = './schema';
    private $write = false;
    private $connectionNames = [];

    public function consumeHelp($prog)
    {
        printf(
            "Usage: %s [OPTIONS] CONFIG-FILE CONN [CONN ...]\n" .
            "Dumps database schemas for named connections. This tool is considerably faster\n" .
            "than mysqldump, especially for large schemas. You might use this tool to\n" .
            "(re-)initalise your project's schema directory from a local database.\n" .
            "\n" .
            "OPTIONS\n" .
            "  -h, -help, --help   display this message, and exit\n" .
            "  --[no-]quote-names  [do not] quote names with `...`; default: no\n" .
            "  --schema-path=PATH  location of schemas; default: ./schema\n" .
            "  --[no-]write        write schema files to schema path; default: no\n" .
            "\n" .
            "CONFIG-FILE\n" .
            "A YAML file mapping connection names to parameters. See the morphism project's\n" .
            "README.md file for detailed information.\n" .
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
            $connection = $config->getConnection($connectionName);
            $entry = $config->getEntry($connectionName);
            $dbName = $entry['connection']['dbname'];
            $matchTables = $entry['morphism']['matchTables'];

            if (!$this->write) {
                echo "\n";
                echo "/********* Connection: $connectionName Database: $dbName *********/\n";
                echo "\n";
            }

            $connection = $config->getConnection($connectionName);
            $entry = $config->getEntry($connectionName);
            $matchTables = $entry['morphism']['matchTables'];
            $directory = $entry['morphism']['definition'];

            $extractor = new Extractor($connection);
            $extractor->setDatabases([$dbName]);
            $extractor->setCreateDatabases(false);
            $extractor->setQuoteNames($this->quoteNames);
            $text = '';
            foreach($extractor->extract() as $query) {
                $text .= "$query;\n";
            }
            $stream = TokenStream::newFromText($text, '');

            $dump = new MysqlDump();
            try {
                $dump->parse($stream, ['matchTables' => $matchTables]);
            }
            catch(\RuntimeException $e) {
                throw new \RuntimeException($stream->contextualise($e->getMessage()));
            }
            catch(\Exception $e) {
                throw new \Exception($e->getMessage() . "\n\n" . $e->getTraceAsString());
            }

            if ($this->write) {
                $output = "{$this->schemaPath}/$directory";

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
