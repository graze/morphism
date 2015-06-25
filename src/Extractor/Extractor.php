<?php

namespace Graze\Morphism\Extractor;

use Graze\Morphism\Parse\Token;

/**
 * Fast database schema extractor - instead of using mysqldump, it talks
 * directly to the INFORMATION_SCHEMA, resulting in a 2x speedup.
 */
class Extractor
{
    private $dbh = null;
    private $databases = null;
    private $createDatabase = true;
    private $quoteNames = true;

    /**
     * Constructor
     */
    public function __construct(\Doctrine\DBAL\Connection $dbh)
    {
        $this->dbh = $dbh;
    }

    /**
     * If $flag is true, the extractor will output CREATE DATABASE and USE
     * statements. Defaults to true.
     *
     * @param $flag bool
     */
    public function setCreateDatabases($flag)
    {
        $this->createDatabase = (bool) $flag;
    }

    /**
     * Specifies the database schemas to extract. All non-system schemas
     * (i.e. not MYSQL or INFORMATION_SCHEMA) will be extracted if none
     * are specified.
     *
     * @param string[] $databases
     */
    public function setDatabases(array $databases)
    {
        $this->databases = $databases;
    }

    /**
     * If $flags is true, the extractor will enclose names of database objects
     * (schemas, tables, columns, etc) in backquotes. Defaults to true.
     *
     * @param $flag bool
     */
    public function setQuoteNames($flag)
    {
        $this->quoteNames = (bool) $flag;
    }

    /**
     * Prepare, bind, and execute the specified query against the current
     * connection, and return the result set as an array of objects.
     *
     * @param $sql string
     * @param $binds mixed[]
     * @return result-row-object[]
     */
    private function query($sql, $binds = [])
    {
        $sth = $this->dbh->prepare($sql);
        $sth->execute($binds);
        $rows = $sth->fetchAll(\PDO::FETCH_OBJ);
        return $rows;
    }

    /**
     * Returns $count comma-separated placeholders (e.g. "?", "?,?", etc)
     * or "NULL" if $count is zero, suitable for use with an IN() clause in
     * a prepared query.
     *
     * @param $count integer
     * @return string
     */
    private static function placeholders($count)
    {
        if (is_array($count)) {
            $count = count($count);
        }
        if ($count == 0) {
            return "NULL";
        }
        return implode(',', array_fill(0, $count, '?'));
    }

    /**
     * @return [$schema => SCHEMATA-object, ...]
     */
    private function getSchemata()
    {
        if (count($this->databases) == 0) {
            $rows = $this->query("
                SELECT *
                FROM INFORMATION_SCHEMA.SCHEMATA
                WHERE SCHEMA_NAME NOT IN ('mysql', 'information_schema')
            ");
        }
        else {
            $binds = $this->databases;
            $placeholders = self::placeholders($binds);
            $rows = $this->query("
                SELECT *
                FROM INFORMATION_SCHEMA.SCHEMATA
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

    /**
     * @return [$schema => [$table => TABLES-object, ...], ...]
     */
    private function getTables(array $databases)
    {
        $placeholders = self::placeholders($databases);
        $rows = $this->query("
            SELECT *
            FROM INFORMATION_SCHEMA.TABLES
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

    /**
     * @param $databases string[]
     * @return [$schema => [$table => [COLUMNS-object, ...], ...], ...]
     */
    private function getColumns(array $databases)
    {
        $placeholders = self::placeholders($databases);
        $rows = $this->query("
            SELECT *
            FROM INFORMATION_SCHEMA.COLUMNS
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

    /**
     * @param $databases string[]
     * @return [$schema => [$table => [$index => [STATISTICS-object, ...], ...], ...], ...]
     */
    private function getKeys(array $databases)
    {
        $placeholders = self::placeholders($databases);
        $rows = $this->query("
            SELECT *
            FROM INFORMATION_SCHEMA.STATISTICS
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

    /**
     * @param $databases string[]
     * @return [$schema => [$table => [$constraint => [KEY_COLUMN_USAGE-object, ...], ...], ...], ...]
     */
    private function getReferences(array $databases)
    {
        $placeholders = self::placeholders($databases);
        $rows = $this->query("
            SELECT *
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
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

    private function getConstraints(array $databases)
    {
        $placeholders = self::placeholders($databases);
        $rows = $this->query("
            SELECT *
            FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA IN ($placeholders)
            ORDER BY
                CONSTRAINT_SCHEMA,
                TABLE_NAME,
                CONSTRAINT_NAME
            ",
            $databases
        );
        $constraints = [];
        foreach($rows as $constraint) {
            $constraints[$constraint->CONSTRAINT_SCHEMA][$constraint->TABLE_NAME][$constraint->CONSTRAINT_NAME] = $constraint;
        }
        return $constraints;
    }

    /**
     * Returns an array of SQL DDL statements to create the specified database.
     *
     * @param $schema SCHEMA-object
     * @return string[]
     */
    private function getCreateDatabase($schema)
    {
        $escapedDatabase = Token::escapeIdentifier($schema->SCHEMA_NAME);
        return [
            "CREATE DATABASE $escapedDatabase" .
            " DEFAULT CHARACTER SET $schema->DEFAULT_CHARACTER_SET_NAME" .
            " COLLATE $schema->DEFAULT_COLLATION_NAME",
        ];
    }

    /**
     * Returns an array of SQL statements to select the specified database
     * as the default for the connection.
     *
     * @param $schema SCHEMA-object
     * @return string[]
     */
    private function getUseDatabase($schema)
    {
        $escapedDatabase = Token::escapeIdentifier($schema->SCHEMA_NAME);
        return [
            "USE $escapedDatabase",
        ];
    }

    /**
     * Returns an array of clauses that will form part of a CREATE TABLE statement
     * to create the specified columns.
     *
     * @param $columns COLUMNS-object[]
     * @return string[]
     */
    private function getColumnDefs(array $columns)
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

    /**
     * Returns an array of clauses that will form part of a CREATE TABLE statement
     * to create the specified indexes.
     *
     * @param $keys [$index => STATISTICS-object[]]
     * @return string[]
     */
    private function getKeyDefs(array $keys)
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
                    $defKeyPart .= "($keyPart->SUB_PART)";
                }
                $defKeyParts[] = $defKeyPart;
            }
            $defKeys[] = "  $defKey (" . implode(',', $defKeyParts) . ")";
        }
        return $defKeys;
    }

    /**
     * Returns an array of clauses that will form part of a CREATE TABLE statement
     * to create the specified foreign key constraints.
     *
     * @param $references [$constraint => [KEY_COLUMN_USAGE-object, ...], ...]
     * @param $constraints [$constraint => REFERENTIAL_CONSTRAINTS-object, ...]
     * @return string[]
     */
    private function getReferenceDefs(array $references, array $constraints)
    {
        $defReferences = [];
        foreach($references as $reference) {
            $firstRefPart = $reference[0];
            $constraintName = Token::escapeIdentifier($firstRefPart->CONSTRAINT_NAME);
            $referencedTable = Token::escapeIdentifier($firstRefPart->REFERENCED_TABLE_NAME);
            if ($firstRefPart->REFERENCED_TABLE_SCHEMA != $firstRefPart->TABLE_SCHEMA) {
                $referencedTable = Token::escapeIdentifier($firstRefPart->REFERENCED_TABLE_SCHEMA) . '.' . $referencedTable;
            }
            $defForeignParts = [];
            $defReferenceParts = [];
            foreach($reference as $referencePart) {
                $defForeignParts[] = Token::escapeIdentifier($referencePart->COLUMN_NAME);
                $defReferenceParts[] = Token::escapeIdentifier($referencePart->REFERENCED_COLUMN_NAME);
            }
            $constraint = $constraints[$firstRefPart->CONSTRAINT_NAME];
            $options = "";
            if ($constraint->MATCH_OPTION != 'NONE') {
                $options = " MATCH " . $constraint->MATCH_OPTION;
            }
            if ($constraint->UPDATE_RULE != 'RESTRICT') {
                $options .= " ON UPDATE " . $constraint->UPDATE_RULE;
            }
            if ($constraint->DELETE_RULE != 'RESTRICT') {
                $options .= " ON DELETE " . $constraint->DELETE_RULE;
            }
            $defReferences[] =
                "  CONSTRAINT $constraintName" .
                " FOREIGN KEY (" . implode(',', $defForeignParts) . ")" .
                " REFERENCES $referencedTable (" . implode(',', $defReferenceParts) . ")" .
                $options;
        }
        return $defReferences;
    }

    /**
     * Returns an array of table options which will form part of the DDL
     * necessary to create the specified table.
     *
     * @param $table TABLES-object[]
     * @return string[]
     */
    private function getTableOptionDefs($table)
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

    /**
     * Returns an array of SQL DDL statements to create the specified table.
     *
     * @param $table TABLES-object
     * @param $columns COLUMNS-object[]
     * @param $keys [$index => STATISTICS-object[]]
     * @param $references [$constraint => [KEY_COLUMN_USAGE-object, ...], ...]
     * @param $constraints [$constraint => REFERENTIAL_CONSTRAINTS-object, ...]
     * @return string
     */
    private function getCreateTable($table, $columns, $keys, $references, $constraints)
    {
        $tableName = $table->TABLE_NAME;

        $defColumns = $this->getColumnDefs($columns);
        $defKeys = $this->getKeyDefs($keys);
        $defReferences = $this->getReferenceDefs($references, $constraints);
        $defTableOptions = $this->getTableOptionDefs($table);

        return [
            "CREATE TABLE " . Token::escapeIdentifier($tableName) . " (\n" .
            implode(",\n", array_merge($defColumns, $defKeys, $defReferences)) . "\n" .
            ") " . implode(' ', $defTableOptions),
        ];
    }

    /**
     * Returns an array of SQL DDL statements extracted from the database
     * connection provided at construction.
     *
     * @return string[]
     */
    public function extract()
    {
        $text = '';
        Token::setQuoteNames($this->quoteNames);

        $schemata = $this->getSchemata();
        $databases = array_keys($schemata);
        $tables = $this->getTables($databases);
        $columns = $this->getColumns($databases);
        $keys = $this->getKeys($databases);
        $references = $this->getReferences($databases);
        $constraints = $this->getConstraints($databases);

        $statements = [];
        foreach($schemata as $database => $schema) {
            if ($this->createDatabase) {
                $statements = array_merge(
                    $statements,
                    $this->getCreateDatabase($schema),
                    $this->getUseDatabase($schema)
                );
            }

            foreach(isset($tables[$database]) ? $tables[$database] : [] as $tableName => $table) {
                $tableColumns = $columns[$database][$tableName];
                $tableKeys = isset($keys[$database][$tableName]) ? $keys[$database][$tableName] : [];
                $tableReferences = isset($references[$database][$tableName]) ? $references[$database][$tableName] : [];
                $tableConstraints = isset($constraints[$database][$tableName]) ? $constraints[$database][$tableName] : [];
                $statements = array_merge($statements, $this->getCreateTable($table, $tableColumns, $tableKeys, $tableReferences, $tableConstraints));
            }
        }

        return $statements;
    }
}
