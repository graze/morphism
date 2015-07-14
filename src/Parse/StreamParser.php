<?php

namespace Graze\Morphism\Parse;

use Exception;
use Graze\Morphism\Specification\TableSpecification;
use RuntimeException;

class StreamParser
{
    /**
     * @var CollationInfo
     */
    private $defaultCollation;

    /**
     * @var string
     */
    private $defaultDatabaseName;

    /**
     * @var string
     */
    private $defaultEngine;

    /**
     * @param CollationInfo $defaultCollation
     * @param string $defaultDatabaseName
     * @param string $defaultEngine
     */
    public function __construct(CollationInfo $defaultCollation, $defaultDatabaseName, $defaultEngine)
    {
        $this->defaultCollation = clone $defaultCollation;
        $this->defaultDatabaseName = $defaultDatabaseName;
        $this->defaultEngine = $defaultEngine;
    }

    /**
     * @param TokenStream $stream
     * @param TableSpecification $specification
     *
     * @return MysqlDump
     * @throws Exception
     */
    public function parse(TokenStream $stream, TableSpecification $specification = null)
    {
        try {
            $databases = [];
            $database = null;

            while (true) {
                if ($stream->peek('CREATE DATABASE')) {
                    $database = new CreateDatabase($this->defaultCollation);
                    $database->parse($stream);
                    $stream->expect('symbol', ';');

                    $databases[$database->name] = $database;
                } elseif ($stream->peek('CREATE TABLE')) {
                    if (is_null($database)) {
                        $name = $this->defaultDatabaseName;
                        $database = new CreateDatabase($this->defaultCollation);
                        $database->name = $name;
                        $databases[$name] = $database;
                    }
                    $table = new CreateTable($database->getCollation());
                    $table->setDefaultEngine($this->defaultEngine);
                    $table->parse($stream);
                    $stream->expect('symbol', ';');

                    if (is_null($specification) || ($specification && $specification->isSatisfiedBy($table))) {
                        $database->addTable($table);
                    }
                } elseif (! $this->skipToken($stream)) {
                    break;
                }
            }

            $dump = new MysqlDump($databases);
            return $dump;
        } catch (RuntimeException $e) {
            throw new RuntimeException($stream->contextualise($e->getMessage()));
        } catch (Exception $e) {
            throw new Exception($e->getMessage() . "\n\n" . $e->getTraceAsString());
        }
    }

    /**
     * @param TokenStream $stream
     *
     * @return bool
     */
    private function skipToken(TokenStream $stream)
    {
        while (true) {
            $token = $stream->nextToken();
            if ($token->isEof()) {
                return false;
            }
            if ($token->eq('symbol', ';')) {
                return true;
            }
        }
    }
}
