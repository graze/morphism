<?php

namespace Graze\Morphism\Configuration;

use Symfony\Component\Yaml\Parser;

class ConfigurationParser
{
    /**
     * @param string $configFile
     *
     * @return Configuration
     */
    public function parse($configFile)
    {
        $parser = new Parser();
        $config = $parser->parse(file_get_contents($configFile));

        if (! array_key_exists('databases', $config)) {
            throw new \RuntimeException("missing databases section in '{$configFile}'");
        }

        $entries = [];
        foreach($config['databases'] as $connectionName => $entry) {
            if (empty($entry['morphism']['enable'])) {
                continue;
            }
            $morphism = $entry['morphism'];
            unset($entry['morphism']);

            if (! array_key_exists('dbname', $entry)) {
                $entry['dbname'] = $connectionName;
            }

            $entries[$connectionName] = [
                'connection' => $entry,
                'morphism'   => [
                    'include' => ! empty($morphism['include']) ? $morphism['include'] : null,
                    'exclude' => ! empty($morphism['exclude']) ? $morphism['exclude'] : null
                ]
            ];
        }

        return new Configuration($entries);
    }
}
