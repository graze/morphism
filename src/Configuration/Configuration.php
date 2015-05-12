<?php

namespace Graze\Morphism\Configuration;

class Configuration
{
    /**
     * @var array
     */
    private $entries;

    /**
     * @param array $entries
     */
    public function __construct($entries)
    {
        $this->entries = $entries;
    }

    /**
     * @param string $connectionName
     *
     * @return array
     */
    public function getEntry($connectionName)
    {
        return $this->entries[$connectionName];
    }

    /**
     * @param string $connectionName
     *
     * @return array
     */
    public function getConnectionConfiguration($connectionName)
    {
        return $this->entries[$connectionName]['connection'];
    }

    /**
     * @return array
     */
    public function getConnectionNames()
    {
        return array_keys($this->entries);
    }
}
