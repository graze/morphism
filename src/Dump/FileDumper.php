<?php

namespace Graze\Morphism\Dump;

use Doctrine\DBAL\Connection;
use Graze\Morphism\Configuration\Configuration;
use Graze\Morphism\Parse\CreateDatabase;
use Graze\Morphism\Parse\TokenStreamFactory;
use Illuminate\Filesystem\Filesystem;

class FileDumper extends Dumper
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var Filesystem
     */
    private $file;

    /**
     * @param Configuration $config
     * @param TokenStreamFactory $streamFactory
     * @param string $path
     * @param Filesystem $file
     */
    public function __construct(Configuration $config, TokenStreamFactory $streamFactory, $path, Filesystem $file)
    {
        parent::__construct($config, $streamFactory);
        $this->path = $path;
        $this->file = $file;
    }

    /**
     * {@inheritDoc}
     */
    public function dump(Connection $connection)
    {
        $dump = parent::dump($connection);
        $output = "{$this->path}/{$connection->getDatabase()}";

        if (! is_dir($output)) {
            $this->file->makeDirectory($output, 0777, true, true);
        }
        $database = reset($dump->databases); /** @var CreateDatabase $database */
        foreach($database->tables as $table) {
            $path = "$output/{$table->name}.sql";
            $text = '';
            foreach($table->getDDL() as $query) {
                $text .= "$query;\n\n";
            }
            $this->file->put($path, $text);
        }
    }
}
