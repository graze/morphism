<?php

namespace Graze\Morphism;


/**
 * A parser for config files
 */
class Config
{
    private $dbParams = [];
    private $path;

    /**
     * Constructor
     *
     * @param $path string - path to config file.
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
        $parser = new \Symfony\Component\Yaml\Parser;
        $config = $parser->parse(file_get_contents($this->path));

        if (!isset($config['databases'])) {
            throw new \RuntimeException("missing databases section in '{$this->path}'");
        }

        $this->dbParams = $config['databases'];
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
     * @param $connectionName string - name of the connection to look up
     * @return [$param => $value, ...]
     */
    public function getConnectionParams($connectionName)
    {
        if (!isset($this->dbParams[$connectionName])) {
            throw new \RuntimeException("unknown connection '$connectionName'");
        }

        $params = $this->dbParams[$connectionName];
        if (!isset($params['dbname'])) {
            $params['dbname'] = $connectionName;
        }
        return $params;
    }

    /**
     * Returns a new database connection using the parameters named by
     * connectionName.
     *
     * @param $connectionName string
     * @return \Doctrine\DBAL\Connection
     */
    public function getConnection($connectionName)
    {
        $params = $this->getConnectionParams($connectionName);
        $dbalConfig = new \Doctrine\DBAL\Configuration();
        return \Doctrine\DBAL\DriverManager::getConnection($params, $dbalConfig);
    }

    /**
     * Returns the names of all the connections specified in the config file.
     *
     * @return string[]
     */
    public function getConnectionNames()
    {
        return array_keys($this->dbParams);
    }
}
