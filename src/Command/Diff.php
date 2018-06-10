<?php

namespace Graze\Morphism\Command;

use Doctrine\DBAL\Connection;
use Exception;
use Graze\Morphism\Parse\TokenStream;
use Graze\Morphism\Parse\Token;
use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Extractor;
use Graze\Morphism\Config;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Diff extends Command
{
    const COMMAND_NAME              = 'diff';

    // Command line arguments
    const ARGUMENT_CONFIG_FILE      = 'config-file';
    const ARGUMENT_CONNECTIONS      = 'connections';

    // Command line options
    const OPTION_ENGINE             = 'engine';
    const OPTION_COLLATION          = 'collation';
    const OPTION_APPLY_CHANGES      = 'apply-changes';
    const OPTION_LOG_DIR            = 'log-dir';

    const OPTION_QUOTE_NAMES        = 'quote-names';
    const OPTION_NO_QUOTE_NAMES     = 'no-quote-names';
    const OPTION_CREATE_TABLE       = 'create-table';
    const OPTION_NO_CREATE_TABLE    = 'no-create-table';
    const OPTION_DROP_TABLE         = 'drop-table';
    const OPTION_NO_DROP_TABLE      = 'no-drop-table';
    const OPTION_ALTER_ENGINE       = 'alter-engine';
    const OPTION_NO_ALTER_ENGINE    = 'no-alter-engine';
    const OPTION_LOG_SKIPPED        = 'log-skipped';
    const OPTION_NO_LOG_SKIPPED     = 'no-log-skipped';

    /** @var string */
    private $engine = 'InnoDB';
    /** @var string|null */
    private $collation = null;
    /** @var bool */
    private $quoteNames = true;
    /** @var bool */
    private $createTable = true;
    /** @var bool */
    private $dropTable = true;
    /** @var bool */
    private $alterEngine = true;
    /** @var string|null */
    private $configFile = null;
    /** @var array */
    private $connectionNames = [];
    /** @var string */
    private $applyChanges = 'no';
    /** @var string null */
    private $logDir = null;
    /** @var bool */
    private $logSkipped = true;

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME);

        $helpText = sprintf(
            "Usage: %s [OPTION] CONFIG-FILE [CONN] ...\n" .
            "Extracts schema definitions from the named connections, and outputs the\n" .
            "necessary ALTER TABLE statements to transform them into what is defined\n" .
            "under the schema path. If no connections are specified, all connections\n" .
            "in the config with 'morphism: enable: true' will be used.\n" .
            "\n" .
            "GENERAL OPTIONS:\n" .
            "  -h, -help, --help      display this message, and exit\n" .
            "  --engine=ENGINE        set the default database engine\n" .
            "  --collation=COLLATION  set the default collation\n" .
            "  --[no-]quote-names     quote names with `...`; default: yes\n" .
            "  --[no-]create-table    output CREATE TABLE statements; default: yes\n" .
            "  --[no-]drop-table      output DROP TABLE statements; default: yes\n" .
            "  --[no-]alter-engine    output ALTER TABLE ... ENGINE=...; default: yes\n" .
            "  --apply-changes=WHEN   apply changes (yes/no/confirm); default: no\n" .
            "  --log-dir=DIR          log applied changes to DIR - one log file will be\n" .
            "                         created per connection; default: none\n" .
            "  --[no-]log-skipped     log skipped queries (commented out); default: yes\n" .
            "\n" .
            "CONFIG-FILE\n" .
            "A YAML file mapping connection names to parameters. See the morphism project's\n" .
            "README.md file for detailed information.\n" .
            "",
            self::COMMAND_NAME
        );
        $this->setHelp($helpText);

        $this->addArgument(
            self::ARGUMENT_CONFIG_FILE,
            InputArgument::REQUIRED
        );

        $this->addArgument(
            self::ARGUMENT_CONNECTIONS,
            InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
            null,
            []
        );

        $this->addOption(
            self::OPTION_ENGINE,
            null,
            InputOption::VALUE_REQUIRED,
            'Database engine',
            'InnoDB'
        );
        $this->addOption(
            self::OPTION_COLLATION,
            null,
            InputOption::VALUE_REQUIRED,
            'Database collation'
        );

        $this->addOption(self::OPTION_QUOTE_NAMES);
        $this->addOption(self::OPTION_NO_QUOTE_NAMES);

        $this->addOption(self::OPTION_CREATE_TABLE);
        $this->addOption(self::OPTION_NO_CREATE_TABLE);

        $this->addOption(self::OPTION_DROP_TABLE);
        $this->addOption(self::OPTION_NO_DROP_TABLE);

        $this->addOption(self::OPTION_ALTER_ENGINE);
        $this->addOption(self::OPTION_NO_ALTER_ENGINE);

        $this->addOption(
            self::OPTION_APPLY_CHANGES,
            null,
            InputOption::VALUE_REQUIRED,
            null,
            "no"
        );

        $this->addOption(
            self::OPTION_LOG_DIR,
            null,
            InputOption::VALUE_REQUIRED
        );

        $this->addOption(self::OPTION_LOG_SKIPPED);
        $this->addOption(self::OPTION_NO_LOG_SKIPPED);
    }

    /**
     * @param Connection $connection
     * @param string $dbName
     * @return MysqlDump
     */
    private function getCurrentSchema(Connection $connection, $dbName)
    {
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
        $dump->setDefaultDatabase($dbName);
        $dump->parse($stream);

        return $dump;
    }

    /**
     * @param string $schemaDefinitionPath
     * @param string $dbName
     *
     * @return MySqlDump
     */
    private function getTargetSchema($schemaDefinitionPath, $dbName)
    {
        return MysqlDump::parseFromPaths(
            [$schemaDefinitionPath],
            $this->engine,
            $this->collation,
            $dbName
        );
    }

    /**
     * @param Connection $connection
     * @param string $connectionName
     * @param array $diff
     * @throws Exception
     */
    private function applyChanges(Connection $connection, $connectionName, array $diff)
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

        if (!is_null($this->logDir)) {
            $logFile = "{$this->logDir}/{$connectionName}.sql";
            $logHandle = fopen($logFile, "w");
            if ($logHandle == false) {
                fprintf(STDERR, "Could not open log file for writing: $logFile\n");
                exit(1);
            }
        }

        if (count($diff) > 0 && $confirm) {
            echo "\n";
            echo "-- Confirm changes to $connectionName:\n";
        }

        foreach ($diff as $query) {
            $response = $defaultResponse;
            $apply = false;

            if ($confirm) {
                echo "\n";
                echo "$query;\n\n";
                do {
                    echo "-- Apply this change? [y]es [n]o [a]ll [q]uit: ";
                    $response = fgets(STDIN);
                    if ($response === false) {
                        throw new Exception("Could not read response");
                    }
                    $response = rtrim($response);
                } while (!in_array($response, ['y', 'n', 'a', 'q']));
            }

            switch ($response) {
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
            } elseif ($logHandle && $this->logSkipped) {
                fwrite(
                    $logHandle,
                    "-- [SKIPPED]\n" .
                    preg_replace('/^/xms', '-- ', $query) .  ";\n" .
                    "\n"
                );
            }
        }
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

        if ($input->getOption(self::OPTION_NO_QUOTE_NAMES)) {
            $this->quoteNames = false;
        }

        if ($input->getOption(self::OPTION_NO_CREATE_TABLE)) {
            $this->createTable = false;
        }

        if ($input->getOption(self::OPTION_NO_DROP_TABLE)) {
            $this->dropTable = false;
        }

        $this->applyChanges = $input->getOption(self::OPTION_APPLY_CHANGES);
        if (!in_array($this->applyChanges, ['yes', 'no', 'confirm'])) {
            throw new InvalidArgumentException(sprintf(
                "Unknown value for --%s: %s",
                self::OPTION_APPLY_CHANGES,
                $this->applyChanges
            ));
        }

        if ($input->getOption(self::OPTION_NO_LOG_SKIPPED)) {
            $this->logSkipped = false;
        }

        try {
            $config = new Config($this->configFile);
            $config->parse();

            $connectionNames =
                (count($this->connectionNames) > 0)
                    ? $this->connectionNames
                    : $config->getConnectionNames();

            Token::setQuoteNames($this->quoteNames);

            if (!is_null($this->logDir)) {
                if (!is_dir($this->logDir)) {
                    if (!@mkdir($this->logDir, 0777, true)) {
                        fprintf(STDERR, "Could not create log directory: {$this->logDir}\n");
                        exit(1);
                    }
                }
            }

            foreach ($connectionNames as $connectionName) {
                echo "-- --------------------------------\n";
                echo "--   Connection: $connectionName\n";
                echo "-- --------------------------------\n";
                $connection = $config->getConnection($connectionName);
                $entry = $config->getEntry($connectionName);
                $dbName = $entry['connection']['dbname'];
                $schemaDefinitionPath = $entry['morphism']['schemaDefinitionPath'];
                $matchTables = [
                    $dbName => $entry['morphism']['matchTables'],
                ];

                $currentSchema = $this->getCurrentSchema($connection, $dbName);
                $targetSchema = $this->getTargetSchema($schemaDefinitionPath, $dbName);

                $diff = $currentSchema->diff(
                    $targetSchema,
                    [
                        'createTable'    => $this->createTable,
                        'dropTable'      => $this->dropTable,
                        'alterEngine'    => $this->alterEngine,
                        'matchTables'    => $matchTables,
                    ]
                );

                $statements = array_reduce($diff, function ($acc, $item) {
                    if (is_array($item)) {
                        foreach ($item as $statement) {
                            $acc[] = $statement;
                        }
                    } else {
                        $acc[] = $item;
                    }

                    return $acc;
                }, []);

                foreach ($statements as $query) {
                    echo "$query;\n\n";
                }

                $this->applyChanges($connection, $connectionName, $statements);
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new Exception($e->getMessage() . "\n\n" . $e->getTraceAsString());
        }
    }
}
