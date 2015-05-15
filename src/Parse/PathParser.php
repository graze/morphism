<?php

namespace Graze\Morphism\Parse;

use Graze\Morphism\ExtractorFactory;
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
        $dump = new MysqlDump();
        if (!is_null($defaultEngine)) {
            $dump->setDefaultEngine($defaultEngine);
        }
        if (!is_null($defaultCollation)) {
            $dump->setDefaultCollation(new CollationInfo($defaultCollation));
        }
        if (!is_null($defaultDatabaseName)) {
            $dump->setDefaultDatabase($defaultDatabaseName);
        }

        $files = [];
        foreach($paths as $path) {
            if (is_dir($path)) {
                foreach(new \GlobIterator("$path/*.sql") as $fileInfo) {
                    $files[] = $fileInfo->getPathname();
                }
            }
            else {
                $files[] = $path;
            }
        }

        foreach($files as $file) {
            $streamFactory = new TokenStreamFactory(new ExtractorFactory(), new Filesystem());
            $stream = $streamFactory->buildFromFile($file);
            try {
                $dump->parse($stream);
            }
            catch(\RuntimeException $e) {
                $message = $stream->contextualise($e->getMessage());
                throw new \RuntimeException($message);
            }
        }

        return $dump;
    }
}
