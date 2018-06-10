<?php

namespace Graze\Morphism\Command;

use Exception;
use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Parse\TokenStream;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Extract extends Command
{
    const COMMAND_NAME              = 'extract';

    // Command line arguments
    const ARGUMENT_MYSQL_DUMP_FILE  = 'mysql-dump-file';

    // Command line options
    const OPTION_SCHEMA_PATH        = 'schema-path';
    const OPTION_DATABASE           = 'database';
    const OPTION_WRITE              = 'write';
    const OPTION_NO_WRITE           = 'no-write';

    /** @var string */
    private $schemaPath = './schema';
    /** @var bool */
    private $write = false;
    /** @var string|null */
    private $mysqldump = null;
    /** @var string|null */
    private $databaseName = null;

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME);

        $helpText = sprintf(
            "Usage: %s [OPTIONS] [MYSQL-DUMP-FILE]\n" .
            "Extracts schema definition(s) from a mysqldump file. Multiple databases may\n" .
            "be defined in the dump, and they will be extracted to separate directories.\n" .
            "You might use this tool when initialising the schema directory from a dump\n" .
            "created on a production server with 'mysqldump --no-data'.\n" .
            "\n" .
            "OPTIONS\n" .
            "  -h, -help, --help   display this message, and exit\n" .
            "  --schema-path=PATH  location of schemas; default: ./schema\n" .
            "  --database=NAME     name of database if not specified in dump\n" .
            "  --[no-]write        write schema files to schema path; default: no\n" .
            "",
            self::COMMAND_NAME
        );
        $this->setHelp($helpText);

        $this->setDescription("Extract schema definitions from a mysqldump file");

        $this->addArgument(
            self::ARGUMENT_MYSQL_DUMP_FILE,
            InputArgument::OPTIONAL,
            null,
            'php://stdin'
        );

        $this->addOption(
            self::OPTION_SCHEMA_PATH,
            null,
            InputOption::VALUE_REQUIRED,
            null,
            './schema'
        );

        $this->addOption(
            self::OPTION_DATABASE,
            null,
            InputOption::VALUE_REQUIRED
        );

        $this->addOption(self::OPTION_WRITE);
        $this->addOption(self::OPTION_NO_WRITE);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->mysqldump = $input->getArgument(self::ARGUMENT_MYSQL_DUMP_FILE);

        $this->schemaPath = $input->getOption(self::OPTION_SCHEMA_PATH);
        $this->databaseName = $input->getOption(self::OPTION_DATABASE);

        if ($input->getOption(self::OPTION_WRITE)) {
            $this->write = true;
        }

        $stream = TokenStream::newFromFile($this->mysqldump);

        $dump = new MysqlDump();
        try {
            $dump->parse($stream);
        } catch (RuntimeException $e) {
            throw new RuntimeException($stream->contextualise($e->getMessage()));
        } catch (Exception $e) {
            throw new Exception($e->getMessage() . "\n\n" . $e->getTraceAsString());
        }

        if ($this->write) {
            foreach ($dump->databases as $database) {
                $databaseName = ($database->name == '') ? $this->databaseName : $database->name;
                if ($databaseName == '') {
                    throw new RuntimeException("No database name specified in dump - please use --database=NAME to supply one");
                }
                $output = "{$this->schemaPath}/$databaseName";

                if (!is_dir($output)) {
                    if (!@mkdir($output, 0755, true)) {
                        throw new RuntimeException("Could not make directory $output");
                    }
                }
                foreach ($database->tables as $table) {
                    $path = "$output/{$table->name}.sql";
                    $text = '';
                    foreach ($table->getDDL() as $query) {
                        $text .= "$query;\n\n";
                    }
                    if (false === @file_put_contents($path, $text)) {
                        throw new RuntimeException("Could not write $path");
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
