<?php
namespace Graze\Morphism\Parse;

use GlobIterator;
use RuntimeException;

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

    /** @var CreateDatabase */
    private $database = null;
    /** @var string */
    private $defaultDatabaseName = '';
    /** @var string */
    private $defaultEngine = 'InnoDB';
    /** @var CollationInfo */
    private $defaultCollation = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->defaultCollation = new CollationInfo();
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
     * @param string|null $defaultDatabaseName database name to use if unspecified in stream
     * @return MysqlDump
     */
    public static function parseFromPaths(array $paths, $defaultEngine = null, $defaultCollation = null, $defaultDatabaseName = null)
    {
        $dump = new self;
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
        foreach ($paths as $path) {
            if (is_dir($path)) {
                foreach (new GlobIterator("$path/*.sql") as $fileInfo) {
                    $files[] = $fileInfo->getPathname();
                }
            } else {
                $files[] = $path;
            }
        }

        foreach ($files as $file) {
            $stream = TokenStream::newFromFile($file);
            try {
                $dump->parse($stream);
            } catch (RuntimeException $e) {
                $message = $stream->contextualise($e->getMessage());
                throw new RuntimeException($message);
            }
        }

        return $dump;
    }

    /**
     * Sets the name to use for the database if none is specified in the stream.
     *
     * @param string $databaseName
     */
    public function setDefaultDatabase($databaseName)
    {
        $this->defaultDatabaseName = $databaseName;
    }

    /**
     * Sets the default storage engine to assume when the ENGINE= option is
     * not specified in a CREATE TABLE.
     *
     * @param string $engine  name of storage engine, e.g. 'InnoDB'
     */
    public function setDefaultEngine($engine)
    {
        $this->defaultEngine = $engine;
    }

    /**
     * Sets the default collation to assume when no collation or charset
     * is specified in CREATE DATABASE or CREATE TABLE.
     *
     * @param CollationInfo $collation
     */
    public function setDefaultCollation(CollationInfo $collation)
    {
        $this->defaultCollation = clone $collation;
    }

    /**
     * Parses a sequence of CREATE DATABASE and CREATE TABLE clauses from $stream.
     *
     * All other SQL statements and comments are skipped. After a CREATE DATABASE,
     * any subsequent CREATE TABLEs are assumed to belong to that database. Any
     * CREATE TABLEs encountered before the first CREATE DATABASE are assigned to
     * an anonymous dummy database named ''.
     *
     * @param TokenStream $stream
     * @param array $flags
     */
    public function parse(TokenStream $stream, array $flags = [])
    {
        $flags += [
            'matchTables' => [
                'include' => '',
                'exclude' => '',
            ]
        ];

        while (true) {
            if ($stream->peek('CREATE DATABASE')) {
                $this->database = new CreateDatabase($this->defaultCollation);
                $this->database->parse($stream);
                $stream->expect(Token::SYMBOL, ';');

                $this->databases[$this->database->name] = $this->database;
            } elseif ($stream->peek('CREATE TABLE')) {
                if (is_null($this->database)) {
                    $name = $this->defaultDatabaseName;
                    $this->database = new CreateDatabase($this->defaultCollation);
                    $this->database->name = $name;
                    $this->databases[$name] = $this->database;
                }
                $table = new CreateTable($this->database->getCollation());
                $table->setDefaultEngine($this->defaultEngine);
                $table->parse($stream);
                $stream->expect(Token::SYMBOL, ';');

                $includeTablesRegex = $flags['matchTables']['include'];
                $excludeTablesRegex = $flags['matchTables']['exclude'];
                if (($includeTablesRegex == '' || preg_match($includeTablesRegex, $table->getName())) &&
                    ($excludeTablesRegex == '' || !preg_match($excludeTablesRegex, $table->getName()))
                ) {
                    $this->database->addTable($table);
                }
            } elseif (!$this->skipQuery($stream)) {
                break;
            }
        }
    }

    /**
     * Skip any statements which aren't create database/table statements.
     *
     * @param TokenStream $stream
     * @return bool
     */
    private function skipQuery(TokenStream $stream)
    {
        while (true) {
            $token = $stream->nextToken();
            if ($token->isEof()) {
                return false;
            }
            if ($token->eq(Token::SYMBOL, ';')) {
                return true;
            }
        }

        // Shoud never get here, but treat it as EOF
        return false;
    }

    /**
     * Returns an array of SQL DDL statements for creating the database schema.
     *
     * @return string[]
     */
    public function getDDL()
    {
        $ddl = [];

        foreach ($this->databases as $database) {
            if ($database->name !== '') {
                $ddl = array_merge($ddl, $database->getDDL());
                $ddl[] = "USE " . Token::escapeIdentifier($database->name);
            }
            foreach ($database->tables as $table) {
                $ddl = array_merge($ddl, $table->getDDL());
            }
        }

        return $ddl;
    }

    /**
     * Returns an array of SQL DDL statements for transforming the database
     * schema into the one represented by $that.
     *
     * $flags           |
     * :----------------|
     * 'createDatabase' | (bool) include 'CREATE DATABASE' statements and dependent CREATE TABLEs [default: true]
     * 'dropDatabase'   | (bool) include 'DROP DATABASE' statements [default: true]
     * 'createTable'    | (bool) include 'CREATE TABLE' statements [default: true]
     * 'dropTable'      | (bool) include 'DROP TABLE' statements [default: true]
     * 'alterEngine'    | (bool) include 'ALTER TABLE ... ENGINE=' [default: true]
     * 'matchTables'    | [$database => ['include' => $regex, 'exclude' => $regex], ...] tables to include / exclude
     *
     * @param MysqlDump $that
     * @param bool[] $flags controls what to include in the generated DDL
     * @return string[]
     */
    public function diff(MysqlDump $that, array $flags = [])
    {
        $flags += [
            'createDatabase' => true,
            'dropDatabase'   => true,
            'createTable'    => true,
            'dropTable'      => true,
            'alterEngine'    => true,
            'matchTables'    => [
                'include' => '',
                'exclude' => '',
            ],
        ];

        $thisDatabaseNames = array_keys($this->databases);
        $thatDatabaseNames = array_keys($that->databases);

        $commonDatabaseNames  = array_intersect($thisDatabaseNames, $thatDatabaseNames);
        $droppedDatabaseNames = array_diff($thisDatabaseNames, $thatDatabaseNames);
        $createdDatabaseNames = array_diff($thatDatabaseNames, $thisDatabaseNames);

        $diff = [];

        if ($flags['dropDatabase'] && count($droppedDatabaseNames) > 0) {
            foreach ($droppedDatabaseNames as $databaseName) {
                $diff[] = "DROP DATABASE IF EXISTS " . Token::escapeIdentifier($databaseName);
            }
        }

        if ($flags['createDatabase']) {
            foreach ($createdDatabaseNames as $databaseName) {
                $matchTables = $flags['matchTables'][$databaseName];
                $includeTablesRegex = $matchTables['include'];
                $excludeTablesRegex = $matchTables['exclude'];
                $thatDatabase = $that->databases[$databaseName];
                $diff[] = $thatDatabase->getDDL();
                $diff[] = "USE " . Token::escapeIdentifier($databaseName);
                foreach ($thatDatabase->tables as $table) {
                    if (($includeTablesRegex == '' || preg_match($includeTablesRegex, $table->getName())) &&
                        ($excludeTablesRegex == '' || !preg_match($excludeTablesRegex, $table->getName()))
                    ) {
                        $diff[] = $table->getDDL();
                    }
                }
            }
        }

        foreach ($commonDatabaseNames as $databaseName) {
            $matchTables = $flags['matchTables'][$databaseName];
            $thisDatabase = $this->databases[$databaseName];
            $thatDatabase = $that->databases[$databaseName];
            $databaseDiff = $thisDatabase->diff($thatDatabase, [
                'createTable' => $flags['createTable'],
                'dropTable'   => $flags['dropTable'],
                'alterEngine' => $flags['alterEngine'],
                'matchTables' => $matchTables,
            ]);

            if ($databaseDiff !== '') {
                if ($databaseName !== $this->defaultDatabaseName) {
                    $diff[] = "USE " . Token::escapeIdentifier($databaseName);
                }
                $diff = array_merge($diff, $databaseDiff);
            }
        }

        return $diff;
    }
}
