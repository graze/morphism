<?php
namespace Graze\Morphism\Parse;

/**
 * Handles the specification of character set and collation.
 */
class CollationInfo
{
    // only bothering to detail the charsets + collations we're actually likely to use
    /** @var array */
    private static $data = [
        // In the list of collations for each charset, the entry
        // for the default collation must come first, and the
        // binary collation last:
        'latin1' => [
            'latin1_swedish_ci',
            'latin1_german1_ci',
            'latin1_german2_ci',
            'latin1_spanish_ci',
            'latin1_danish_ci',
            'latin1_general_ci',
            'latin1_general_cs',
            'latin1_bin',
        ],
        'utf8' => [
            'utf8_general_ci',
            'utf8_unicode_ci',
            'utf8_bin',
        ],
        'binary' => [
            'binary',
        ],
    ];

    /** @var string|null */
    private $_charset = null;
    /** @var string|null */
    private $_collation = null;
    /** @var bool|null */
    private $_isBinaryCollation = null;

    /**
     * If neither parameter is specified, creates an object representing an
     * unspecified collation.
     * If only $charset is provided, its default collation will be selected.
     * If only $collation is provided, the charset will be derived from it.
     * If both are provided, $collation must be a valid collation for the
     * charset.
     *
     * @param string|null $charset    name of a character set, e.g. latin1, utf8, binary
     * @param string|null $collation  name of a collation, e.g. latin1_general_ci, utf8_unicode_ci, binary
     */
    public function __construct($charset = null, $collation = null)
    {
        if (!is_null($charset)) {
            $this->setCharset($charset);
        }
        if (!is_null($collation)) {
            $this->setCollation($collation);
        }
    }

    /**
     * Returns true if a charset or collation has not yet been specified.
     *
     * @return bool
     */
    public function isSpecified()
    {
        return !is_null($this->_charset);
    }

    /**
     * Returns the name of the character set.
     * Throws an exception if a charset or collation has not yet been specified.
     *
     * @throws \LogicException
     * @return string
     */
    public function getCharset()
    {
        if (is_null($this->_charset)) {
            throw new \LogicException("getCharset called when charset is unspecified");
        }
        return $this->_charset;
    }

    /**
     * Returns the name of the collation.
     * Throws an exception if a charset or collation has not yet been specified.
     *
     * @throws \LogicException
     * @return string
     */
    public function getCollation()
    {
        if (is_null($this->_charset)) {
            throw new \LogicException("getCollation called when collation is unspecified");
        }
        if ($this->_isBinaryCollation) {
            return self::_getCharsetBinaryCollation($this->_charset);
        }
        return $this->_collation;
    }

    /**
     * Returns true if the selected charset is 'binary'.
     * Throws an exception if a charset or collation has not yet been specified.
     *
     * @throws \LogicException
     * @return bool
     */
    public function isBinaryCharset()
    {
        if (is_null($this->_charset)) {
            throw new \LogicException("isBinaryCharset called when collation is unspecified");
        }
        return $this->_charset === 'binary';
    }

    /**
     * Returns true if the selected collation is the default for the charset.
     * Throws an exception if a charset or collation has not yet been specified.
     *
     * @throws \LogicException
     * @return bool
     */
    public function isDefaultCollation()
    {
        if (is_null($this->_charset)) {
            throw new \LogicException("isDefaultCollation called when collation is unspecified");
        }
        return $this->getCollation() === self::_getCharsetDefaultCollation($this->_charset);
    }

    /**
     * Sets the character set.
     *
     * Throws a RuntimeException if $charset is in conflict with an already
     * specified character set or collation.
     *
     * @param string $charset
     * @throws \RuntimeException
     * @return void
     */
    public function setCharset($charset)
    {
        $charset = strtolower($charset);
        $defaultCollation = self::_getCharsetDefaultCollation($charset);
        if (is_null($defaultCollation)) {
            throw new \RuntimeException("unknown character set '$charset'");
        }
        if (!is_null($this->_charset) &&
            $this->_charset !== $charset
        ) {
            throw new \RuntimeException("Conflicting CHARACTER SET declarations");
        }
        $this->_charset = $charset;
        $this->_collation = $defaultCollation;
    }

    /**
     * Sets the collation.
     *
     * Throws a RuntimeException if a character set has already been specified,
     * but $collation is not compatible with it.
     *
     * @param string $collation
     * @throws \RuntimeException
     * @return void
     */
    public function setCollation($collation)
    {
        $collation = strtolower($collation);
        $charset = self::_getCollationCharset($collation);
        if (is_null($charset)) {
            throw new \RuntimeException("unknown collation '$collation'");
        }
        if (!is_null($this->_charset) &&
            $this->_charset !== $charset
        ) {
            throw new \RuntimeException("COLLATION '$collation' is not valid for CHARACTER SET '$charset'");
        }
        $this->_charset = $charset;
        $this->_collation = $collation;
    }

    /**
     * Ensures that the character set's binary collation will be returned
     * by getCollation() in future (on this object), regardless of any prior
     * or subsequent call to setCollation().
     *
     * @return void
     */
    public function setBinaryCollation()
    {
        $this->_isBinaryCollation = true;
    }

    /**
     * Get all the available collations for the given character set.
     *
     * @param string $charset
     * @return string|null
     */
    private static function _getCharsetCollations($charset)
    {
        return array_key_exists($charset, self::$data)
            ? self::$data[$charset]
            : null;
    }

    /**
     * Get the default collation for the given character set.
     *
     * @param string $charset
     * @return string|null
     */
    private static function _getCharsetDefaultCollation($charset)
    {
        $collations = self::_getCharsetCollations($charset);
        if (null !== $collations) {
            return $collations[0];
        }
        return null;
    }

    /**
     * Get the binary collation for the given character set.
     *
     * @param string $charset
     * @return string|null
     */
    private static function _getCharsetBinaryCollation($charset)
    {
        $collations = self::_getCharsetCollations($charset);
        if (null !== $collations) {
            return $collations[count($collations) - 1];
        }
        return null;
    }

    /**
     * @param string $collation
     * @return string|null
     */
    private static function _getCollationCharset($collation)
    {
        foreach (self::$data as $charset => $collations) {
            if (in_array($collation, $collations)) {
                return $charset;
            }
        }
        return null;
    }
}
