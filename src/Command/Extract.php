<?php

namespace Graze\Morphism\Command;

use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Parse\TokenStream;

class Extract implements Argv\ConsumerInterface
{
    /** @var bool */
    private $quoteNames = true;
    /** @var string */
    private $schemaPath = './schema';
    /** @var bool */
    private $write = false;
    /** @var string|null */
    private $mysqldump = null;
    /** @var string|null */
    private $databaseName = null;

    /**
     * @param string $prog
     */
    public function consumeHelp($prog)
    {
        printf(
            "Usage: %s [OPTIONS] [MYSQL-DUMP-FILE]\n" .
            "Extracts schema definition(s) from a mysqldump file. Multiple databases may\n" .
            "be defined in the dump, and they will be extracted to separate directories.\n" .
            "You might use this tool when initialising the schema directory from a dump\n" .
            "created on a production server with 'mysqldump --no-data'.\n" .
            "\n" .
            "OPTIONS\n" .
            "  -h, -help, --help   display this message, and exit\n" .
            "  --[no-]quote-names  [do not] quote names with `...`; default: no\n" .
            "  --schema-path=PATH  location of schemas; default: ./schema\n" .
            "  --database=NAME     name of database if not specified in dump\n" .
            "  --[no-]write        write schema files to schema path; default: no\n" .
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
            case '--quote-names':
            case '--no-quote-names':
                $this->quoteNames = $option->bool();
                break;

            case '--schema-path':
                $this->schemaPath = $option->required();
                break;

            case '--database':
                $this->databaseName = $option->required();
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

    /**
     * @param array $args
     */
    public function consumeArgs(array $args)
    {
        if (count($args) == 0) {
            $this->mysqldump = 'php://stdin';
        } elseif (count($args) == 1) {
            $this->mysqldump = $args[0];
        } else {
            throw new Argv\Exception("expected a mysqldump file");
        }
    }

    /**
     * @throws \Exception
     */
    public function run()
    {
        $stream = TokenStream::newFromFile($this->mysqldump);

        $dump = new MysqlDump();
        try {
            $dump->parse($stream);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($stream->contextualise($e->getMessage()));
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage() . "\n\n" . $e->getTraceAsString());
        }

        if ($this->write) {
            foreach ($dump->databases as $database) {
                $databaseName = ($database->name == '') ? $this->databaseName : $database->name;
                if ($databaseName == '') {
                    throw new \RuntimeException("no database name specified in dump - please use --database=NAME to supply one");
                }
                $output = "{$this->schemaPath}/$databaseName";

                if (!is_dir($output)) {
                    if (!@mkdir($output, 0777, true)) {
                        throw new \RuntimeException("could not make directory $output");
                    }
                }
                foreach ($database->tables as $table) {
                    $path = "$output/{$table->name}.sql";
                    $text = '';
                    foreach ($table->getDDL() as $query) {
                        $text .= "$query;\n\n";
                    }
                    if (false === @file_put_contents($path, $text)) {
                        throw new \RuntimeException("could not write $path");
                    }
                    fprintf(STDERR, "wrote $path\n");
                }
            }
        } else {
            foreach ($dump->getDDL() as $query) {
                echo "$query;\n\n";
            }
        }
    }
}
