<?php

namespace Graze\Morphism\Parse;

use Exception;
use Graze\Morphism\Test\Parse\TestCase;
use LogicException;
use RuntimeException;

class CollationInfoTest extends TestCase
{
    public function testConstructor()
    {
        $collation = new CollationInfo();
        $this->assertThat($collation, $this->isInstanceOf(__NAMESPACE__ . '\CollationInfo'));
    }

    /**
     * @dataProvider providerConstructorWithArgs
     * @param string|null $charset
     * @param string|null $collation
     * @param string $expectedCharset
     * @param string $expectedCollation
     */
    public function testConstructorWithArgs($charset, $collation, $expectedCharset, $expectedCollation)
    {
        $collation = new CollationInfo($charset, $collation);
        $this->assertSame($expectedCharset, $collation->getCharset());
        $this->assertSame($expectedCollation, $collation->getCollation());
    }

    /**
     * @return array
     */
    public function providerConstructorWithArgs()
    {
        return [
            ['utf8',   null,                'utf8',   'utf8_general_ci'],
            ['UTF8',   null,                'utf8',   'utf8_general_ci'],
            ['utf8',   'utf8_general_ci',   'utf8',   'utf8_general_ci'],
            [null,     'utf8_general_ci',   'utf8',   'utf8_general_ci'],
            [null,     'UTF8_GENERAL_CI',   'utf8',   'utf8_general_ci'],

            ['utf8',   'utf8_unicode_ci',   'utf8',   'utf8_unicode_ci'],
            [null,     'utf8_unicode_ci',   'utf8',   'utf8_unicode_ci'],

            ['latin1', null,                'latin1', 'latin1_swedish_ci'],
            ['latin1', 'latin1_swedish_ci', 'latin1', 'latin1_swedish_ci'],
            [null,     'latin1_swedish_ci', 'latin1', 'latin1_swedish_ci'],
            [null,     'latin1_general_ci', 'latin1', 'latin1_general_ci'],

            ['binary', null,                'binary', 'binary'],
            ['binary', 'binary',            'binary', 'binary'],
            [null,     'binary',            'binary', 'binary'],
        ];
    }

    /**
     * @param string $charset
     * @param string $collation
     * @dataProvider providerConstructorWithBadArgs
     */
    public function testConstructorWithBadArgs($charset, $collation)
    {
        $this->expectException(RuntimeException::class);
        new CollationInfo($charset, $collation);
    }

    /**
     * @return array
     */
    public function providerConstructorWithBadArgs()
    {
        return [
            // Invalid character set
            ['foo', 'utf8_general_ci'],
            // Invalid collation
            ['utf8', 'foo'],
        ];
    }

    public function testIsSpecified()
    {
        $this->assertFalse((new CollationInfo)->isSpecified());
        $this->assertTrue((new CollationInfo('utf8'))->isSpecified());
        $this->assertTrue((new CollationInfo(null, 'utf8_general_ci'))->isSpecified());
    }

    public function testSetBinaryCollation()
    {
        $collation = new CollationInfo();
        $collation->setBinaryCollation();
        $collation->setCharset('utf8');
        $this->assertSame('utf8_bin', $collation->getCollation());

        $collation = new CollationInfo();
        $collation->setCharset('utf8');
        $collation->setBinaryCollation();
        $this->assertSame('utf8_bin', $collation->getCollation());
    }

    public function testIsBinaryCharset()
    {
        $this->assertTrue((new CollationInfo('binary'))->isBinaryCharset());
        $this->assertFalse((new CollationInfo('utf8'))->isBinaryCharset());
        $this->assertFalse((new CollationInfo('utf8', 'utf8_bin'))->isBinaryCharset());
    }

    public function testInvalidIsBinaryCharset()
    {
        $this->expectException(LogicException::class);
        (new CollationInfo())->isBinaryCharset();
    }

    public function testIsDefaultCollation()
    {
        $this->assertTrue((new CollationInfo('utf8'))->isDefaultCollation());
        $this->assertTrue((new CollationInfo('utf8', 'utf8_general_ci'))->isDefaultCollation());
        $this->assertTrue((new CollationInfo('latin1'))->isDefaultCollation());
        $this->assertTrue((new CollationInfo('binary'))->isDefaultCollation());

        $this->assertFalse((new CollationInfo('utf8', 'utf8_unicode_ci'))->isDefaultCollation());
        $this->assertFalse((new CollationInfo('latin1', 'latin1_general_ci'))->isDefaultCollation());
    }

    public function testInvalidSiDefaulfCollation()
    {
        $this->expectException(LogicException::class);
        (new CollationInfo())->isDefaultCollation();
    }

    public function testSetCharset()
    {
        $collation = new CollationInfo();
        $collation->setCharset('utf8');
        $this->assertSame('utf8', $collation->getCharset());

        $collation = new CollationInfo('utf8', 'utf8_bin');
        $collation->setCharset('utf8');
        $this->assertSame('utf8', $collation->getCharset());
    }

    public function testSetCharsetFail()
    {
        $this->expectException(Exception::class);
        (new CollationInfo('latin1'))->setCharset('utf8');
    }

    public function testEmptyCharsetFail()
    {
        $this->expectException(LogicException::class);
        (new CollationInfo())->getCharset();
    }

    public function testSetCollation()
    {
        $collation = new CollationInfo();
        $collation->setCollation('utf8_unicode_ci');
        $this->assertSame('utf8', $collation->getCharset());
        $this->assertSame('utf8_unicode_ci', $collation->getCollation());

        $collation = new CollationInfo('utf8', 'utf8_unicode_ci');
        $collation->setCollation('utf8_general_ci');
        $this->assertSame('utf8', $collation->getCharset());
        $this->assertSame('utf8_general_ci', $collation->getCollation());
    }

    public function testSetCollationFail()
    {
        $this->expectException(Exception::class);
        (new CollationInfo('latin1'))->setCollation('utf8_general_ci');
    }

    public function testGetCollation()
    {
        $collation = new CollationInfo(null, 'utf8_unicode_ci');
        $this->assertSame('utf8_unicode_ci', $collation->getCollation());

        $collation->setBinaryCollation();
        $this->assertSame('utf8_bin', $collation->getCollation());
    }

    public function testEmptyCollationFail()
    {
        $this->expectException(LogicException::class);
        (new CollationInfo())->getCollation();
    }
}
