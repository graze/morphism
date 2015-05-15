<?php

namespace Graze\Morphism\Parse;

use Illuminate\Filesystem\Filesystem;

class PathParser
{
    /**
     * @param array $paths
     * @param string $defaultEngine
     * @param string $defaultCollation
     * @param string $defaultDatabaseName
     *
     * @return MysqlDump
     */
    public function parse(array $paths, $defaultEngine = null, $defaultCollation = null, $defaultDatabaseName = null)
    {
        $parser = new StreamParser(
            $defaultCollation ? new CollationInfo($defaultCollation) : new CollationInfo(),
            $defaultDatabaseName ?: '',
            $defaultEngine ?: 'InnoDB'
        );

        $files = [];
        foreach ($paths as $path) {
            if (is_dir($path)) {
                foreach(new \GlobIterator("$path/*.sql") as $fileInfo) {
                    $files[] = $fileInfo->getPathname();
                }
            } else {
                $files[] = $path;
            }
        }

        $text = '';
        foreach ($files as $file) {
            $filesystem = new Filesystem();
            $text .= $filesystem->get($file);
        }

        $stream = new TokenStream('', $text);
        return $parser->parse($stream);
    }
}
