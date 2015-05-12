<?php

namespace Graze\Morphism\Diff;

use Symfony\Component\Console\Input\InputInterface;

class DiffConfiguration
{
    private $engine = 'InnoDB';
    private $collation;
    private $quoteNames = true;
    private $createTable = true;
    private $dropTable = true;
    private $alterEngine = true;
    private $schemaPath = './schema';
    private $configFile;
    private $connectionNames = [];
    private $applyChanges;
    private $logDir;
    private $logSkipped = true;

    /**
     * @param string $configFile
     */
    public function __construct($configFile)
    {
        $this->configFile = $configFile;
    }

    /**
     * @param InputInterface $input
     *
     * @return static
     */
    public static function buildFromInput(InputInterface $input)
    {
        $config = new static($input->getArgument('config-file'));
        $config->setEngine($input->getOption('engine'));
        $config->setCollation($input->getOption('collation'));
        $config->setQuoteNames(!$input->getOption('no-quote-names'));
        $config->setCreateTable(!$input->getOption('no-create-table'));
        $config->setDropTable(!$input->getOption('no-drop-table'));
        $config->setAlterEngine(!$input->getOption('no-alter-engine'));
        $config->setSchemaPath($input->getOption('schema-path'));
        $config->setApplyChanges($input->getOption('apply-changes'));
        $config->setLogDir($input->getOption('log-dir'));
        $config->setLogSkipped(!$input->getOption('no-log-skipped'));

        return $config;
    }

    /**
     * @return string
     */
    public function getEngine()
    {
        return $this->engine;
    }

    /**
     * @param string $engine
     */
    public function setEngine($engine)
    {
        $this->engine = $engine;
    }

    /**
     * @return mixed
     */
    public function getCollation()
    {
        return $this->collation;
    }

    /**
     * @param mixed $collation
     */
    public function setCollation($collation)
    {
        $this->collation = $collation;
    }

    /**
     * @return boolean
     */
    public function isQuoteNames()
    {
        return $this->quoteNames;
    }

    /**
     * @param boolean $quoteNames
     */
    public function setQuoteNames($quoteNames)
    {
        $this->quoteNames = $quoteNames;
    }

    /**
     * @return boolean
     */
    public function isDropTable()
    {
        return $this->dropTable;
    }

    /**
     * @param boolean $dropTable
     */
    public function setDropTable($dropTable)
    {
        $this->dropTable = $dropTable;
    }

    /**
     * @return boolean
     */
    public function isCreateTable()
    {
        return $this->createTable;
    }

    /**
     * @param boolean $createTable
     */
    public function setCreateTable($createTable)
    {
        $this->createTable = $createTable;
    }

    /**
     * @return string
     */
    public function getSchemaPath()
    {
        return $this->schemaPath;
    }

    /**
     * @param string $schemaPath
     */
    public function setSchemaPath($schemaPath)
    {
        $this->schemaPath = $schemaPath;
    }

    /**
     * @return boolean
     */
    public function isAlterEngine()
    {
        return $this->alterEngine;
    }

    /**
     * @param boolean $alterEngine
     */
    public function setAlterEngine($alterEngine)
    {
        $this->alterEngine = $alterEngine;
    }

    /**
     * @return array
     */
    public function getConnectionNames()
    {
        return $this->connectionNames;
    }

    /**
     * @param array $connectionNames
     */
    public function setConnectionNames($connectionNames)
    {
        $this->connectionNames = $connectionNames;
    }

    /**
     * @return mixed
     */
    public function getApplyChanges()
    {
        return $this->applyChanges;
    }

    /**
     * @param mixed $applyChanges
     */
    public function setApplyChanges($applyChanges)
    {
        $this->applyChanges = $applyChanges;
    }

    /**
     * @return mixed
     */
    public function getLogDir()
    {
        return $this->logDir;
    }

    /**
     * @param mixed $logDir
     */
    public function setLogDir($logDir)
    {
        $this->logDir = $logDir;
    }

    /**
     * @return boolean
     */
    public function isLogSkipped()
    {
        return $this->logSkipped;
    }

    /**
     * @param boolean $logSkipped
     */
    public function setLogSkipped($logSkipped)
    {
        $this->logSkipped = $logSkipped;
    }

    /**
     * @return string
     */
    public function getConfigFile()
    {
        return $this->configFile;
    }
}
