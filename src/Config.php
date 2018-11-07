<?php

namespace Graze\Morphism;

use RuntimeException;

/**
 * A parser for config files
 */
class Config
{
    /** @var array */
    private $entries = [];
    /** @var string */
    private $path;

    /**
     * Constructor
     *
     * @param string $path Path to config file.
     */
    public function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * Parse the config file provided in the constructor.
     */
    public function parse()
    {
        if (! file_exists($this->path)) {
            throw new RuntimeException("Specified config file does not exist: {$this->path}");
        }

        $parser = new \Symfony\Component\Yaml\Parser;
        $config = $parser->parse(file_get_contents($this->path));

        if (!isset($config['databases'])) {
            throw new RuntimeException("Missing databases section in '{$this->path}'");
        }

        $entries = [];
        foreach ($config['databases'] as $connectionName => $entry) {
            if (empty($entry['morphism']['enable'])) {
                continue;
            }
            $morphism = $entry['morphism'];
            unset($entry['morphism']);

            if (!isset($entry['dbname'])) {
                $entry['dbname'] = $connectionName;
            }

            $matchTables = [];
            foreach (['include', 'exclude'] as $key) {
                $regex = '';
                if (!empty($morphism["{$key}"])) {
                    $regex =  '/^(' . implode('|', $morphism["{$key}"]) . ')$/';
                }
                $matchTables[$key] = $regex;
            }

            $schemaDefinitionPaths = $morphism['schemaDefinitionPath'];
            if (!$schemaDefinitionPaths) {
                $schemaDefinitionPaths = [ 'schema/'.$connectionName ];
            }

            $entries[$connectionName] = [
                'connection' => $entry,
                'morphism'   => [
                    'matchTables' => $matchTables,
                    'schemaDefinitionPath' => $schemaDefinitionPaths,
                ],
            ];
        }
        $this->entries = $entries;
    }

    /**
     * Returns the parameters to open the named database connection, suitable
     * for passing to \Doctrine\DBAL\DriverManager::getConnection().
     *
     * Some subset of these parameters will be returned:
     *
     *      'driver'      => $pdo_driver
     *      'dbname'      => $dbname
     *      'user'        => $user
     *      'password'    => $password
     *      'host'        => $host
     *      'port'        => $port
     *      'unix_socket' => $socket
     *
     * @param string $connectionName Name of the connection to look up
     * @return ['connection' => [$param => $value, ...], 'morphism' => [ ... ] ]
     */
    public function getEntry($connectionName)
    {
        if (!isset($this->entries[$connectionName])) {
            throw new RuntimeException("Unknown connection '$connectionName'");
        }

        return $this->entries[$connectionName];
    }

    /**
     * Returns a new database connection using the parameters named by
     * connectionName.
     *
     * @param string $connectionName
     * @return \Doctrine\DBAL\Connection
     */
    public function getConnection($connectionName)
    {
        $entry = $this->getEntry($connectionName);
        $dbalConfig = new \Doctrine\DBAL\Configuration();
        return \Doctrine\DBAL\DriverManager::getConnection($entry['connection'], $dbalConfig);
    }

    /**
     * Returns the names of all the connections specified in the config file.
     *
     * @return string[]
     */
    public function getConnectionNames()
    {
        return array_keys($this->entries);
    }
}
