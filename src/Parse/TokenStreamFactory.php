<?php

namespace Graze\Morphism\Parse;

use Doctrine\DBAL\Connection;
use Graze\Morphism\Extractor\ExtractorFactory;
use Illuminate\Filesystem\Filesystem;

class TokenStreamFactory
{
    /**
     * @var ExtractorFactory
     */
    protected $extractorFactory;

    /**
     * @var Filesystem
     */
    protected $file;

    /**
     * @param ExtractorFactory $extractorFactory
     * @param Filesystem $file
     */
    public function __construct(ExtractorFactory $extractorFactory, Filesystem $file)
    {
        $this->extractorFactory = $extractorFactory;
        $this->file = $file;
    }

    /**
     * @param string $path
     *
     * @return TokenStream
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function buildFromFile($path)
    {
        $text = $this->file->get($path);
        return new TokenStream($path, $text);
    }

    /**
     * @param Connection $connection
     *
     * @return TokenStream
     */
    public function buildFromConnection(Connection $connection)
    {
        $extractor = $this->extractorFactory->buildFromConnection($connection);
        $extractor->setCreateDatabases(false);

        $text = '';
        foreach ($extractor->extract() as $query) {
            $text .= "$query;\n";
        }

        return new TokenStream('', $text);
    }
}
