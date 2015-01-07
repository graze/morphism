<?php

namespace Graze\Morphism\Command;

use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Parse\TokenStream;

class Extract implements Argv\Consumer
{
    private $quoteNames = true;
    private $schemaPath = './schemas';
    private $write = false;
    private $mysqldump = null;

    public function consumeHelp($prog)
    {
        printf(
            "Usage: %s [OPTIONS] [MYSQL-DUMP-FILE]\n" .
            "Extracts schema definition from a mysqldump file.\n" .
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
        if (count($args) == 0) {
            $this->mysqldump = 'php://stdin';
        }
        else if (count($args) == 1) {
            $this->mysqldump = $args[0];
        }
        else {
            throw new Argv\Exception("expected a mysqldump file");
        }
    }

    public function run()
    {
        $stream = TokenStream::newFromFile($this->mysqldump);

        $this->parseStream($stream);

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
            foreach($dump->databases as $database) {
                $output = "{$this->schemaPath}/$databaseName";

                if (!is_dir($output)) {
                    if (!@mkdir($output, 0777, true)) {
                        throw new \RuntimeException("could not make directory $output");
                    }
                }
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
        }
        else {
            foreach($dump->getDDL() as $query) {
                echo "$query;\n\n";
            }
        }
    }
}
