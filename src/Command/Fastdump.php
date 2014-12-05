<?php

namespace Graze\Morphism\Command;

use Graze\Morphism\Parse\Token;

class Fastdump implements Argv\Consumer
{
    private $host = null;
    private $password = null;
    private $port = null;
    private $socket = null;
    private $user = null;
    private $createDatabase = true;
    private $databases = [];

    public function consumeHelp($prog)
    {
        printf(
            "Usage: %s [OPTIONS] [DB] ...\n" .
            "Dump specified database schemas. Dumps all non-system schemas if none specified.\n" .
            "This tool is considerably faster than mysqldump (especially for large schemas).\n" .
            "\n" .
            "OPTIONS\n" .
            "  -h, -help, --help   display this message, and exit\n" .
            "  --socket=FILE       the socket file to use for the connection\n" .
            "  --host=HOST         connect to host\n" .
            "  --port=NUMBER       port number to use for connection\n" .
            "  --user=USER         user for login if not current user\n" .
            "  --password[=PASS]   password to use when connecting to server; if password\n" .
            "                      is not given it's solicited on the tty.\n" .
            "  --[no-]create-db    [do not] output CREATE DATABASEs (and associated CREATE TABLEs)\n" .
            "  --[no-]quote-names  [do not] quote names with `...`\n" .
            "",
            $prog
        );
    }

    public function argv(array $argv)
    {
        $argvParser = new Argv\Parser($argv);
        $argvParser->consumeWith($this);
    }

    public function consumeOption(Argv\Option $option)
    {
        switch($option->getOption()) {
            case '--socket':
                $this->socket = $option->required();
                break;

            case '--host':
                $this->host = $option->required();
                break;

            case '--port':
                $this->port = $option->required();
                break;

            case '--user':
                $this->user = $option->required();
                break;

            case '--password':
                $this->password = $option->optional(false);
                break;

            case '--create-db':
            case '--no-create-db':
                $this->createDatabase = $option->bool();
                break;
            
            case '--quote-names':
            case '--no-quote-names':
                Token::setQuoteNames($option->bool());
                break;

            default:
                $option->unrecognised();
                break;
        }
    }

    public function consumeArgs(array $args)
    {
        $this->databases = $args;
    }

    private function _getPassword()
    {
        $password = $this->password;

        if ($password === false) {
            // get current state of tty
            $sttySave = `/bin/stty -g`;

            // -echo = turn off echo
            // -isig = turn off keyboard signals (^C, ^Z, etc)
            // icanon = respect erase keys (BS, ^U, etc)
            `/bin/stty -echo -isig icanon`;

            echo "Enter password: ";
            $password = fgets(STDIN);
            echo "\n";

            `/bin/stty $sttySave`;

            if ($password === false) {
                throw new \RuntimeException("could not read password");
            }
            $password = preg_replace('/\n$/ms', '', $password);
        }

        return $password;
    }

    private function _connect($user, $password)
    {
        $dsn = 'mysql:dbname=INFORMATION_SCHEMA';
        if (!is_null($this->socket)) {
            $dsn .= ";unix_socket=$this->socket";
        }
        if (!is_null($this->host)) {
            $dsn .= ";host=$this->host";
        }
            
        $this->dbh = new \PDO($dsn, $user, $password);
    }

    private function _query($sql, $binds = [])
    {
        $sth = $this->dbh->prepare($sql);
        $sth->execute($binds);
        $rows = $sth->fetchAll(\PDO::FETCH_OBJ);
        return $rows;
    }

    private function _getSchemata()
    {
        if (count($this->databases) == 0) {
            $rows = $this->_query("
                SELECT *
                FROM SCHEMATA
                WHERE SCHEMA_NAME NOT IN ('mysql', 'information_schema')
            ");
        }
        else {
            $binds = $this->databases;
            $placeholders = implode(',', array_fill(0, count($binds), '?'));
            $rows = $this->_query("
                SELECT *
                FROM SCHEMATA
                WHERE SCHEMA_NAME IN ($placeholders)
                ",
                $binds
            );
        }

        $schemata = [];
        foreach($rows as $schema) {
            $schemata[$schema->SCHEMA_NAME] = $schema;
        }
        return $schemata;
    }

    private function _getTables($databases)
    {
        $placeholders = implode(',', array_fill(0, count($databases), '?'));
        $rows = $this->_query("
            SELECT *
            FROM TABLES
            WHERE TABLE_SCHEMA IN ($placeholders)
            ORDER BY
                TABLE_SCHEMA,
                TABLE_NAME
            ",
            $databases
        );
        $tables = [];
        foreach($rows as $table) {
            $tables[$table->TABLE_SCHEMA][$table->TABLE_NAME] = $table;
        }
        return $tables;
    }

    public function _getColumns($databases)
    {
        $placeholders = implode(',', array_fill(0, count($databases), '?'));
        $rows = $this->_query("
            SELECT *
            FROM COLUMNS
            WHERE TABLE_SCHEMA IN ($placeholders)
            ORDER BY
                TABLE_SCHEMA,
                TABLE_NAME,
                ORDINAL_POSITION
            ",
            $databases
        );
        $columns = [];
        foreach($rows as $column) {
            $columns[$column->TABLE_SCHEMA][$column->TABLE_NAME][$column->ORDINAL_POSITION] = $column;
        }
        return $columns;
    }

    public function _getKeys($databases)
    {
        $placeholders = implode(',', array_fill(0, count($databases), '?'));
        $rows = $this->_query("
            SELECT *
            FROM STATISTICS
            WHERE TABLE_SCHEMA IN ($placeholders)
            ORDER BY
                TABLE_SCHEMA,
                TABLE_NAME,
                INDEX_NAME = 'PRIMARY' DESC,
                INDEX_NAME,
                SEQ_IN_INDEX
            ",
            $databases
        );
        $keys = [];
        foreach($rows as $key) {
            $keys[$key->TABLE_SCHEMA][$key->TABLE_NAME][$key->INDEX_NAME][] = $key;
        }
        return $keys;
    }

    public function _getReferences($databases)
    {
        $placeholders = implode(',', array_fill(0, count($databases), '?'));
        $rows = $this->_query("
            SELECT *
            FROM KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA IN ($placeholders)
            AND REFERENCED_TABLE_SCHEMA IS NOT NULL
            ORDER BY
                TABLE_SCHEMA,
                TABLE_NAME,
                CONSTRAINT_NAME,
                ORDINAL_POSITION
            ",
            $databases
        );
        $references = [];
        foreach($rows as $reference) {
            $references[$reference->TABLE_SCHEMA][$reference->TABLE_NAME][$reference->CONSTRAINT_NAME][] = $reference;
        }
        return $references;
    }

    private function _getCreateDatabase($schema)
    {
        $escapedDatabase = Token::escapeIdentifier($schema->SCHEMA_NAME);
        $defDatabase =
            "CREATE DATABASE $escapedDatabase" .
            " DEFAULT CHARACTER SET $schema->DEFAULT_CHARACTER_SET_NAME" .
            " COLLATE $schema->DEFAULT_COLLATION_NAME;" .
            "\n" .
            "USE $escapedDatabase;" .
            "\n";
        return $defDatabase;
    }

    private function _getColumnDefs($columns)
    {
        $defColumns = [];
        foreach($columns as $column) {
            $defColumn = "$column->COLUMN_NAME";
            $defColumn .= " $column->COLUMN_TYPE";
            if (!is_null($column->CHARACTER_SET_NAME)) {
                $defColumn .= " CHARACTER SET $column->CHARACTER_SET_NAME";
            }
            if (!is_null($column->COLLATION_NAME)) {
                $defColumn .= " COLLATE $column->COLLATION_NAME";
            }
            if ($column->IS_NULLABLE == 'NO') {
                $defColumn .= " NOT NULL";
            }
            else {
                $defColumn .= " NULL";
            }
            if (!is_null($column->COLUMN_DEFAULT)) {
                if (
                    in_array($column->DATA_TYPE, ['timestamp', 'datetime']) &&
                    $column->COLUMN_DEFAULT == 'CURRENT_TIMESTAMP'
                ) {
                    $defColumn .= " DEFAULT $column->COLUMN_DEFAULT";
                }
                else {
                    $defColumn .= " DEFAULT " . Token::escapeString($column->COLUMN_DEFAULT);
                }
            }
            if ($column->EXTRA != '') {
                $defColumn .= " $column->EXTRA";
            }
            if ($column->COLUMN_COMMENT != '') {
                $defColumn .= " COMMENT " . Token::escapeString($column->COLUMN_COMMENT);
            }
            $defColumns[] = "  $defColumn";
        }
        return $defColumns;
    }

    private function _getKeyDefs($keys)
    {
        $defKeys = [];
        foreach($keys as $key) {
            $defKey = '';
            $firstKeyPart = $key[0];
            if ($firstKeyPart->INDEX_NAME == 'PRIMARY') {
                $defKey = 'PRIMARY KEY';
            }
            else {
                $escapedIndexName = Token::escapeIdentifier($firstKeyPart->INDEX_NAME);
                if ($firstKeyPart->INDEX_TYPE == 'FULLTEXT') {
                    $defKey = "FULLTEXT $escapedIndexName";
                }
                else if ($firstKeyPart->NON_UNIQUE) {
                    $defKey = "KEY $escapedIndexName";
                }
                else {
                    $defKey = "UNIQUE KEY $escapedIndexName";
                }
            }
            $defKeyParts = [];
            foreach($key as $keyPart) {
                $defKeyPart = $keyPart->COLUMN_NAME;
                if (!is_null($keyPart->SUB_PART)) {
                    $defKeyPart .= "(" . Token::escapeIdentifier($keyPart->SUB_PART) . ")";
                }
                $defKeyParts[] = $defKeyPart;
            }
            $defKeys[] = "  $defKey (" . implode(',', $defKeyParts) . ")";
        }
        return $defKeys;
    }

    private function _getReferenceDefs($references)
    {
        $defReferences = [];
        foreach($references as $reference) {
            $firstRefPart = $reference[0];
            $constraintName = Token::escapeIdentifier($firstRefPart->CONSTRAINT_NAME);
            $referencedTable = Token::escapeIdentifier($firstRefPart->REFERENCED_TABLE_NAME);
            $defForeignParts = [];
            $defReferenceParts = [];
            foreach($reference as $referencePart) {
                $defForeignParts[] = Token::escapeIdentifier($referencePart->COLUMN_NAME);
                $defReferenceParts[] = Token::escapeIdentifier($referencePart->REFERENCED_COLUMN_NAME);
            }
            $defReferences[] = 
                "  CONSTRAINT $constraintName" .
                " FOREIGN KEY (" . implode(',', $defForeignParts) . ")" .
                " REFERENCES $referencedTable (" . implode(',', $defReferenceParts) . ")";
        }
        return $defReferences;
    }

    private function _getTableOptionDefs($table)
    {
        $defTableOptions = [];
        $defTableOptions[] = "ENGINE=$table->ENGINE";
        if (strcasecmp($table->ROW_FORMAT, 'COMPACT') != 0) {
            $defTableOptions[] = "ROW_FORMAT=$table->ROW_FORMAT";
        }
        if ($table->AUTO_INCREMENT) {
            $defTableOptions[] = "AUTO_INCREMENT=$table->AUTO_INCREMENT";
        }
        $collate = $table->TABLE_COLLATION;
        list($charset) = explode('_', $table->TABLE_COLLATION);
        $defTableOptions[] = "DEFAULT CHARSET=$charset COLLATE=$collate";
        if ($table->CREATE_OPTIONS != '') {
            $defTableOptions[] = $table->CREATE_OPTIONS;
        }
        if ($table->TABLE_COMMENT != '') {
            $defTableOptions[] = "COMMENT " . Token::escapeString($table->TABLE_COMMENT);
        }
        return $defTableOptions;
    }

    private function _getCreateTable($table, $columns, $keys, $references)
    {
        $tableName = $table->TABLE_NAME;

        $defColumns = $this->_getColumnDefs($columns);
        $defKeys = $this->_getKeyDefs($keys);
        $defReferences = $this->_getReferenceDefs($references);
        $defTableOptions = $this->_getTableOptionDefs($table);

        $defTable = "CREATE TABLE " . Token::escapeIdentifier($tableName) . " (\n";
        $defTable .= implode(",\n", array_merge($defColumns, $defKeys, $defReferences)) . "\n";
        $defTable .= ") " . implode(' ', $defTableOptions) . ";\n";

        return $defTable;
    }

    public function run()
    {
        $this->_connect($this->user, $this->_getPassword());

        $schemata = $this->_getSchemata();
        $databases = array_keys($schemata);
        $tables = $this->_getTables($databases);
        $columns = $this->_getColumns($databases);
        $keys = $this->_getKeys($databases);
        $refs = $this->_getReferences($databases);

        foreach($schemata as $database => $schema) {
            if ($this->createDatabase) {
                echo $this->_getCreateDatabase($schema);
                echo "\n";
            }

            foreach(isset($tables[$database]) ? $tables[$database] : [] as $tableName => $table) {
                $tableColumns = $columns[$database][$tableName];
                $tableKeys = isset($keys[$database][$tableName]) ? $keys[$database][$tableName] : [];
                $tableRefs = isset($refs[$database][$tableName]) ? $refs[$database][$tableName] : [];
                echo $this->_getCreateTable($table, $tableColumns, $tableKeys, $tableRefs);
                echo "\n";
            }
        }
    }
}
