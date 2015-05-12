<?php

namespace Graze\Morphism\Diff;

use Doctrine\DBAL\Connection;
use Graze\Morphism\Parse\TokenStream;
use Graze\Morphism\Parse\Token;
use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Extractor;
use Graze\Morphism\Config;

class Differ
{
    /**
     * @var DiffConfiguration
     */
    private $config;

    /**
     * @param DiffConfiguration $config
     */
    public function __construct(DiffConfiguration $config)
    {
        $this->config = $config;
    }

    /**
     * @param Connection $connection
     * @param string $dbName
     *
     * @return MysqlDump
     */
    private function getCurrentSchema(Connection $connection, $dbName)
    {
        $extractor = new Extractor($connection);
        $extractor->setDatabases([$dbName]);
        $extractor->setCreateDatabases(false);
        $extractor->setQuoteNames($this->config->isQuoteNames());

        $text = '';
        foreach($extractor->extract() as $query) {
            $text .= "$query;\n";
        }
        $stream = TokenStream::newFromText($text, '');

        $dump = new MysqlDump();
        $dump->setDefaultDatabase($dbName);
        $dump->parse($stream);

        return $dump;
    }

    /**
     * @param string $connectionName
     * @param string $dbName
     *
     * @return MysqlDump
     */
    private function getTargetSchema($connectionName, $dbName)
    {
        $path = $this->config->getSchemaPath() . '/' . $connectionName;

        return MysqlDump::parseFromPaths(
            [$path],
            $this->config->getEngine(),
            $this->config->getCollation(),
            $dbName
        );
    }

    /**
     * @param Connection $connection
     * @param string $connectionName
     * @param array $diff
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    private function applyChanges(Connection $connection, $connectionName, $diff)
    {
        if (count($diff) === 0) {
            return;
        }

        if ($this->config->getApplyChanges() === 'no') {
            return;
        }

        $confirm = $this->config->getApplyChanges() === 'confirm';
        $defaultResponse = 'y';
        $logHandle = null;
        $logDir = $this->config->getLogDir();

        if ($logDir !== null) {
            $logFile = "{$logDir}/{$connectionName}.sql";
            $logHandle = fopen($logFile, 'w');
            if ($logHandle === false) {
                fprintf(STDERR, "Could not open log file for writing: $logFile\n");
                exit(1);
            }
        }

        if (count($diff) > 0 && $confirm) {
            echo "\n";
            echo "-- Confirm changes to $connectionName:\n";
        }

        foreach($diff as $query) {
            $response = $defaultResponse;
            $apply = false;

            if ($confirm) {
                echo "\n";
                echo "$query;\n\n";
                do {
                    echo '-- Apply this change? [y]es [n]o [a]ll [q]uit: ';
                    $response = fgets(STDIN);
                    if ($response === false) {
                        throw new \Exception('could not read response');
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
            } elseif ($logHandle && $this->config->isLogSkipped()) {
                fwrite($logHandle,
                    "-- [SKIPPED]\n" .
                    preg_replace('/^/xms', '-- ', $query) .  ";\n" .
                    "\n"
                );
            }
        }
    }

    public function run()
    {
        try {
            $config = new Config($this->config->getConfigFile());
            $config->parse();

            $connectionNames =
                (count($this->config->getConnectionNames()) > 0)
                    ? $this->config->getConnectionNames()
                    : $config->getConnectionNames();

            Token::setQuoteNames($this->config->isQuoteNames());

            $logDir = $this->config->getLogDir();

            if ($logDir !== null
                && ! is_dir($logDir)
                && ! @mkdir($logDir, 0777, true)) {
                fprintf(STDERR, "Could not create log directory: {$logDir}\n");
                exit(1);
            }

            foreach($connectionNames as $connectionName) {
                echo "-- --------------------------------\n";
                echo "--   Connection: $connectionName\n";
                echo "-- --------------------------------\n";
                $connection = $config->getConnection($connectionName);
                $entry = $config->getEntry($connectionName);
                $dbName = $entry['connection']['dbname'];
                $matchTables = [
                    $dbName => $entry['morphism']['matchTables']
                ];

                $currentSchema = $this->getCurrentSchema($connection, $dbName);
                $targetSchema = $this->getTargetSchema($connectionName, $dbName);

                $diff = $currentSchema->diff(
                    $targetSchema,
                    [
                        'createDatabase' => false,
                        'dropDatabase'   => false,
                        'createTable'    => $this->config->isCreateTable(),
                        'dropTable'      => $this->config->isDropTable(),
                        'alterEngine'    => $this->config->isAlterEngine(),
                        'matchTables'    => $matchTables
                    ]
                );

                foreach($diff as $query) {
                    echo "$query;\n\n";
                }

                $this->applyChanges($connection, $connectionName, $diff);
            }
        } catch(\RuntimeException $e) {
            throw $e;
        } catch(\Exception $e) {
            throw new \Exception($e->getMessage() . "\n\n" . $e->getTraceAsString());
        }
    }
}


