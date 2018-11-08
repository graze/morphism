<?php

namespace Graze\Morphism\Command;

use Exception;
use Graze\Morphism\Parse\TokenStream;
use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Extractor;
use Graze\Morphism\Config;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Fastdump extends Command
{
    const COMMAND_NAME          = 'dump';

    // Command line arguments
    const ARGUMENT_CONFIG_FILE  = 'config-file';
    const ARGUMENT_CONNECTIONS  = 'connections';

    // Command line options
    const OPTION_QUOTE_NAMES    = 'quote-names';
    const OPTION_NO_QUOTE_NAMES = 'no-quote-names';
    const OPTION_WRITE          = 'write';
    const OPTION_NO_WRITE       = 'no-write';

    /** @var bool */
    private $quoteNames = true;
    /** @var string|null */
    private $configFile = null;
    /** @var bool */
    private $write = false;
    /** @var array */
    private $connectionNames = [];

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME);

        $helpText = sprintf(
            "Usage: %s [OPTIONS] CONFIG-FILE [CONN ...]\n" .
            "Dumps database schemas for named connections. This tool is considerably faster\n" .
            "than mysqldump, especially for large schemas. You might use this tool to\n" .
            "(re-)initalise your project's schema directory from a local database.\n" .
            "If no connections are specified, all connections\n" .
            "in the config with 'morphism: enable: true' will be used.\n" .
            "\n" .
            "OPTIONS\n" .
            "  -h, -help, --help   display this message, and exit\n" .
            "  --[no-]quote-names  [do not] quote names with `...`; default: yes\n" .
            "  --[no-]write        write schema files to schema path; default: no\n" .
            "\n" .
            "CONFIG-FILE\n" .
            "A YAML file mapping connection names to parameters. See the morphism project's\n" .
            "README.md file for detailed information.\n" .
            "",
            self::COMMAND_NAME
        );
        $this->setHelp($helpText);

        $this->setDescription("Dump database schema for a named database connection");

        $this->addArgument(
            self::ARGUMENT_CONFIG_FILE,
            InputArgument::REQUIRED
        );

        $this->addArgument(
            self::ARGUMENT_CONNECTIONS,
            InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
            '',
            []
        );

        $this->addOption(self::OPTION_QUOTE_NAMES);
        $this->addOption(self::OPTION_NO_QUOTE_NAMES);

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
        $this->configFile = $input->getArgument(self::ARGUMENT_CONFIG_FILE);
        $this->connectionNames = $input->getArgument(self::ARGUMENT_CONNECTIONS);

        if ($input->getOption(self::OPTION_QUOTE_NAMES)) {
            $this->quoteNames = false;
        }

        if ($input->getOption(self::OPTION_WRITE)) {
            $this->write = true;
        }

        $config = new Config($this->configFile);
        $config->parse();

        if (! $this->connectionNames) {
            $this->connectionNames = $config->getConnectionNames();
        }

        foreach ($this->connectionNames as $connectionName) {
            $connection = $config->getConnection($connectionName);

            $entry = $config->getEntry($connectionName);
            $dbName = $entry['connection']['dbname'];
            $matchTables = $entry['morphism']['matchTables'];
            $schemaDefinitionPaths = $entry['morphism']['schemaDefinitionPath'];

            if (!$this->write) {
                echo "\n";
                echo "/********* Connection: $connectionName Database: $dbName *********/\n";
                echo "\n";
            }

            $extractor = new Extractor($connection);
            $extractor->setDatabases([$dbName]);
            $extractor->setCreateDatabases(false);
            $extractor->setQuoteNames($this->quoteNames);
            $text = '';
            foreach ($extractor->extract() as $query) {
                $text .= "$query;\n";
            }
            $stream = TokenStream::newFromText($text, '');

            $dump = new MysqlDump();
            try {
                $dump->parse($stream, ['matchTables' => $matchTables]);
            } catch (RuntimeException $e) {
                throw new RuntimeException($stream->contextualise($e->getMessage()));
            } catch (Exception $e) {
                throw new Exception($e->getMessage() . "\n\n" . $e->getTraceAsString());
            }

            if ($this->write) {
                // Ensure the schema directories to write to exist
                foreach ($schemaDefinitionPaths as $schemaPath) {
                    if (!is_dir($schemaPath)) {
                        if (!@mkdir($schemaPath, 0755, true)) {
                            throw new RuntimeException("Could not make directory $schemaPath");
                        }
                    }
                }
                $database = reset($dump->databases);
                foreach ($database->tables as $table) {
                    // Find any existing schema file. If it doesn't exist, use the first schema path.
                    $path = null;

                    foreach ($schemaDefinitionPaths as $schemaPath) {
                        $possiblePath = "$schemaPath/{$table->name}.sql";
                        if (file_exists($possiblePath)) {
                            $path = $possiblePath;
                            break;
                        }
                    }

                    if (! $path) {
                        $path = "{$schemaDefinitionPaths[0]}/{$table->name}.sql";
                    }

                    $text = '';
                    foreach ($table->getDDL() as $query) {
                        $text .= "$query;\n\n";
                    }
                    if (false === @file_put_contents($path, $text)) {
                        throw new RuntimeException("Could not write $path");
                    }
                    fprintf(STDERR, "wrote $path\n");
                }
            } else {
                foreach ($dump->getDDL() as $query) {
                    echo "$query;\n\n";
                }
            }
        }
    }
}
