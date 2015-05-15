<?php

namespace Graze\Morphism\Dump\Output;

use Graze\Morphism\Parse\CreateDatabase;
use Graze\Morphism\Parse\MysqlDump;
use Illuminate\Filesystem\Filesystem;

class FileOutput implements OutputInterface
{
    /**
     * @var Filesystem
     */
    private $file;

    /**
     * @var string
     */
    private $path;

    /**
     * @param Filesystem $file
     * @param string $path
     */
    public function __construct(Filesystem $file, $path)
    {
        $this->file = $file;
        $this->path = $path;
    }

    /**
     * @param MysqlDump $dump
     *
     * @return void
     */
    public function output(MysqlDump $dump)
    {
        $database = reset($dump->databases); /** @var CreateDatabase $database */
        $output = "{$this->path}/{$database->name}";

        if (! is_dir($output)) {
            $this->file->makeDirectory($output, 0777, true, true);
        }

        foreach ($database->tables as $table) {
            $path = "$output/{$table->name}.sql";

            $text = '';
            foreach ($table->getDDL() as $query) {
                $text .= "$query;\n\n";
            }

            $this->file->put($path, $text);
        }
    }
}
