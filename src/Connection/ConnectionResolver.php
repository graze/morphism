<?php

namespace Graze\Morphism\Connection;

use Doctrine\DBAL\DriverManager;
use Graze\Morphism\Config;

class ConnectionResolver
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $name
     *
     * @return \Doctrine\DBAL\Connection
     * @throws \Doctrine\DBAL\DBALException
     */
    public function resolveFromName($name)
    {
        $entry = $this->config->getEntry($name);
        return DriverManager::getConnection($entry['connection']);
    }
}
