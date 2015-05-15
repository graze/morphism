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
    private $_defaultDatabaseName = '';
    private $_defaultEngine = 'InnoDB';
    private $_defaultCollation = null;

    /**
     * @param array $databases
     */
    public function __construct(array $databases)
    {
        $this->_defaultCollation = new CollationInfo();
        $this->databases = $databases;
    }

    /**
     * Sets the name to use for the database if none is specified in the stream.
     *
     * @param string $databaseName
     */
    public function setDefaultDatabase($databaseName)
    {
        $this->_defaultDatabaseName = $databaseName;
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

        while(true) {
            if ($stream->peek('CREATE DATABASE')) {
                $this->database = new CreateDatabase($this->_defaultCollation);
                $this->database->parse($stream);
                $stream->expect('symbol', ';');

                $this->databases[$this->database->name] = $this->database;
            }
            else if ($stream->peek('CREATE TABLE')) {
                if (is_null($this->database)) {
                    $name = $this->_defaultDatabaseName;
                    $this->database = new CreateDatabase($this->_defaultCollation);
                    $this->database->name = $name;
                    $this->databases[$name] = $this->database;
                }
                $table = new CreateTable($this->database->getCollation());
                $table->setDefaultEngine($this->_defaultEngine);
                $table->parse($stream);
                $stream->expect('symbol', ';');

                $includeTablesRegex = $flags['matchTables']['include'];
                $excludeTablesRegex = $flags['matchTables']['exclude'];
                if (
                    ($includeTablesRegex == '' || preg_match($includeTablesRegex, $table->name)) &&
                    ($excludeTablesRegex == '' || !preg_match($excludeTablesRegex, $table->name))
                ) {
                    $this->database->addTable($table);
                }
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
     * Returns an array of SQL DDL statements for creating the database schema.
     *
     * @return string[]
     */
    public function getDDL()
    {
        $ddl = [];

        foreach($this->databases as $database) {
            if ($database->name !== '') {
                $ddl = array_merge($ddl, $database->getDDL());
                $ddl[] = "USE " . Token::escapeIdentifier($database->name);
            }
            foreach($database->tables as $table) {
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
     * @param bool[] $flags  controls what to include in the generated DDL
     * @return string[]
     */
    public function diff(self $that, $flags = [])
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
            foreach($droppedDatabaseNames as $databaseName) {
                $diff[] = "DROP DATABASE IF EXISTS " . Token::escapeIdentifier($databaseName);
            }
        }

        if ($flags['createDatabase']) {
            foreach($createdDatabaseNames as $databaseName) {
                $matchTables = $flags['matchTables'][$databaseName];
                $includeTablesRegex = $matchTables['include'];
                $excludeTablesRegex = $matchTables['exclude'];
                $thatDatabase = $that->databases[$databaseName];
                $diff[] = $thatDatabase->getDDL();
                $diff[] = "USE " . Token::escapeIdentifier($databaseName);
                foreach($thatDatabase->tables as $table) {
                    if (
                        ($includeTablesRegex == '' || preg_match($includeTablesRegex, $tableName)) &&
                        ($excludeTablesRegex == '' || !preg_match($excludeTablesRegex, $tableName))
                    ) {
                        $diff[] = $table->getDDL($thatDatabase->getCollation());
                    }
                }
            }
        }

        foreach($commonDatabaseNames as $databaseName) {
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
                if ($databaseName !== $this->_defaultDatabaseName) {
                    $diff[] = "USE " . Token::escapeIdentifier($databaseName);
                }
                $diff = array_merge($diff, $databaseDiff);
            }
        }

        return $diff;
    }
}
