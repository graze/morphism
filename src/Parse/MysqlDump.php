<?php
namespace Graze\Morphism\Parse;

/**
 * Represents a dump of one or more databases.
 */
class MysqlDump
{
    /** 
     * @var CreateDatabase[] 
     *
     * indexed by (string) database name; when enumerated, reflects the order
     * in which the databases declarations were parsed.
     */
    public $databases = [];

    private $database = null;
    private $_defaultEngine = 'InnoDB';
    private $_defaultCollation = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_defaultCollation = new CollationInfo();
    }

    /**
     * Parses one or more MySQL dump files from the specified paths.
     *
     * Each path may refer either to a file, or to a directory from which each
     * contained file is parsed. There is no recursion into sub-directories.
     *
     * @param string[]    $paths            files or directories to parse
     * @param string|null $defaultEngine    default database engine to use (e.g. InnoDB)
     * @param string|null $defaultCollation default collation to use (e.g. utf8)
     * @return MysqlDump
     */
    public static function parseFromPaths(array $paths, $defaultEngine = null, $defaultCollation = null)
    {
        $dump = new self;
        if (!is_null($defaultEngine)) {
            $dump->setDefaultEngine($defaultEngine);
        }
        if (!is_null($defaultCollation)) {
            $dump->setDefaultCollation(new CollationInfo($defaultCollation));
        }
        
        
        $files = [];
        foreach($paths as $path) {
            if (is_dir($path)) {
                foreach(new \DirectoryIterator($path) as $fileInfo) {
                    if (!$fileInfo->isDot()) {
                        $files[] = $fileInfo->getPathname();
                    }
                }
            }
            else {
                $files[] = $path;
            }
        }

        foreach($files as $file) {
            $stream = TokenStream::newFromFile($file);
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

    /**
     * Sets the default storage engine to assume when the ENGINE= option is
     * not specified in a CREATE TABLE.
     *
     * @param string $engine  name of storage engine, e.g. 'InnoDB'
     */
    public function setDefaultEngine($engine)
    {
        $this->_defaultEngine = $engine;
    }

    /**
     * Sets the default collation to assume when no collation or charset
     * is specified in CREATE DATABASE or CREATE TABLE.
     */
    public function setDefaultCollation(CollationInfo $collation)
    {
        $this->_defaultCollation = clone $collation;
    }

    /**
     * Parses a sequence of CREATE DATABASE and CREATE TABLE clauses from $stream.
     *
     * All other SQL statements and comments are skipped. After a CREATE DATABASE,
     * any subsequent CREATE TABLEs are assumed to belong to that database. Any
     * CREATE TABLEs encountered before the first CREATE DATABASE are assigned to 
     * an anonymous dummy database named ''.
     */
    public function parse(TokenStream $stream)
    {
        while(true) {
            if ($stream->peek('CREATE DATABASE')) {
                $this->database = new CreateDatabase($this->_defaultCollation);
                $this->database->parse($stream);
                $stream->expect('symbol', ';');

                $this->databases[$this->database->name] = $this->database;
            }
            else if ($stream->peek('CREATE TABLE')) {
                if (is_null($this->database)) {
                    $this->database = new CreateDatabase($this->_defaultCollation);
                    $this->databases[''] = $this->database;
                }
                $table = new CreateTable($this->database->getCollation());
                $table->setDefaultEngine($this->_defaultEngine);
                $table->parse($stream);
                $stream->expect('symbol', ';');

                $this->database->addTable($table);
            }
            else if (!$this->_skipQuery($stream)) {
                break;
            }
        }
    }

    private function _skipQuery(TokenStream $stream)
    {
        while(true) {
            $token = $stream->nextToken();
            if ($token->isEof()) {
                return false;
            }
            if ($token->eq('symbol', ';')) {
                return true;
            }
        }
    }

    /**
     * Returns SQL DDL for creating the database schema.
     *
     * @return string
     */
    public function toString()
    {
        $text = '';

        foreach($this->databases as $database) {
            if ($database->name !== '') {
                $text .= $database->toString() . ";\n";
                $text .= "USE " . Token::escapeIdentifier($database->name) . ";\n";
            }
            foreach($database->tables as $table) {
                $text .= $table->toString();
                $text .= "\n";
            }
        }

        return $text;
    }

    /**
     * Returns SQL DDL for transforming the database schema into the one 
     * represented by $that.
     *
     * $flags           |
     * :----------------|
     * 'createDatabase' | (bool) include 'CREATE DATABASE' statements and dependent CREATE TABLEs [default: true]
     * 'dropDatabase'   | (bool) include 'DROP DATABASE' statements [default: true]
     * 'createTable'    | (bool) include 'CREATE TABLE' statements [default: true]
     * 'dropTable'      | (bool) include 'DROP TABLE' statements [default: true]
     * 'alterEngine'    | (bool) include 'ALTER TABLE ... ENGINE=' [default: true]
     *
     * @param bool[] $flags  controls what to include in the generated DDL
     * @return string
     */
    public function diff(self $that, $flags = [])
    {
        $flags += [
            'createDatabase' => true,
            'dropDatabase'   => true,
            'createTable'    => true,
            'dropTable'      => true,
            'alterEngine'    => true,
        ];

        $thisDatabaseNames = array_keys($this->databases);
        $thatDatabaseNames = array_keys($that->databases);

        $commonDatabaseNames  = array_intersect($thisDatabaseNames, $thatDatabaseNames);
        $droppedDatabaseNames = array_diff($thisDatabaseNames, $thatDatabaseNames);
        $createdDatabaseNames = array_diff($thatDatabaseNames, $thisDatabaseNames);

        $text = '';

        if ($flags['dropDatabase'] && count($droppedDatabaseNames) > 0) {
            foreach($droppedDatabaseNames as $databaseName) {
                $text .= "DROP DATABASE IF EXISTS " . Token::escapeIdentifier($databaseName) . ";\n";
            }
            $text .= "\n";
        }

        if ($flags['createDatabase']) {
            foreach($createdDatabaseNames as $databaseName) {
                $thatDatabase = $that->databases[$databaseName];
                $text .= $thatDatabase->toString() . ";\n";
                $text .= "USE " . Token::escapeIdentifier($databaseName) . ";\n";
                $text .= "\n";
                foreach($thatDatabase->tables as $table) {
                    $text .= $table->toString($thatDatabase->getCollation());
                    $text .= "\n";
                }
            }
        }

        foreach($commonDatabaseNames as $databaseName) {
            $thisDatabase = $this->databases[$databaseName];
            $thatDatabase = $that->databases[$databaseName];
            $databaseDiff = $thisDatabase->diff($thatDatabase, [
                'createTable' => $flags['createTable'],
                'dropTable'   => $flags['dropTable'],
                'alterEngine' => $flags['alterEngine'],
            ]);

            if ($databaseDiff !== '') {
                if ($databaseName !== '') {
                    $text .= "USE " . Token::escapeIdentifier($databaseName) . ";\n";
                }
                $text .= $databaseDiff . "\n";
            }
        }

        return $text;
    }
}
