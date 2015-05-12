<?php

namespace Graze\Morphism\Connection;

use Doctrine\DBAL\DriverManager;
use Graze\Morphism\Configuration\Configuration;

class ConnectionResolver
{
    /**
     * @var Configuration
     */
    private $config;

    /**
     * @param Configuration $config
     */
    public function __construct(Configuration $config)
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
        return DriverManager::getConnection($this->config->getConnectionConfiguration($name));
    }
}
