<?php
namespace Graze\Morphism\Parse;

use LogicException;
use RuntimeException;

/**
 * Represents the definition of an check, this is currently only parsed but not added back to any schema. Checks are
 * added automatically by MySQL, excluding them allows morphism to be backwards compatible with MySQL < 8.
 */
class CheckDefinition
{
    /** @var string|null */
    public $name = null;

    /** @var string */
    public $expression;

    /**
     * Parses an check definition from $stream
     *
     * If the optional preceding CONSTRAINT clause was parsed, you should supply its optional $name.
     *
     * @param TokenStream $stream
     * @param string|null $name name supplied in optional CONSTRAINT clause
     */
    public function parse(TokenStream $stream, $name = null)
    {
        $this->name = $name;

        $this->expression = $this->parseExpressionBetweenBrackets($stream);

        $mark = $stream->getMark();
        $token = $stream->nextToken();

        // Check for [NOT] ENFORCED
        if ($token->eq(Token::IDENTIFIER, 'NOT')) {
            $stream->expect(Token::IDENTIFIER, 'ENFORCED');
            $this->expression .= ' NOT ENFORCED';
        } elseif ($token->eq(Token::IDENTIFIER, 'ENFORCED')) {
            $this->expression .= ' ENFORCED';
        } else {
            $stream->rewind($mark);
        }
    }

    /**
     * Parses an expression between brackets, the next token is expected to be a bracket.
     *
     * @param TokenStream $stream
     * @return string
     */
    private function parseExpressionBetweenBrackets(TokenStream $stream)
    {
        $stream->expectOpenParen();

        $expression = '(';
        $previousToken = null;
        $token = null;

        while (true) {
            $mark = $stream->getMark();
            $previousToken = $token;
            $token = $stream->nextToken();

            if ($token->eq(Token::SYMBOL, '(')) {
                // Nested expression found.
                $stream->rewind($mark);

                if ($previousToken && $previousToken->type != Token::SYMBOL) {
                    $expression .= ' ';
                }

                $expression .= $this->parseExpressionBetweenBrackets($stream);
            } elseif ($token->eq(Token::SYMBOL, ')')) {
                // Ending bracket found, stop parsing.
                $expression .= ')';
                break;
            } elseif ($token->isEof()) {
                throw new RuntimeException("Unexpected end-of-file");
            } else {
                if ($previousToken && $previousToken->type != Token::SYMBOL && $token->type != Token::SYMBOL) {
                    $expression .= ' ';
                }
                $expression .= $token->text;
            }
        }

        return $expression;
    }
}
