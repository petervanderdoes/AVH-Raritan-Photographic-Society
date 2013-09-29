<?php
/**
 * @link    http://github.com/myclabs/php-enum
 * @author  Matthieu Napoli <matthieu@mnapoli.fr>
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

namespace UnitTest\MyCLabs\Enum\Enum;

use MyCLabs\Enum\Enum;

/**
 * Enum test
 */
class EnumTest extends \PHPUnit_Framework_TestCase
{

    /**
     * getValue()
     */
    public function testGetValue()
    {
        $value = new EnumFixture(EnumFixture::FOO);
        $this->assertEquals(EnumFixture::FOO, $value->getValue());

        $value = new EnumFixture(EnumFixture::BAR);
        $this->assertEquals(EnumFixture::BAR, $value->getValue());

        $value = new EnumFixture(EnumFixture::NUMBER);
        $this->assertEquals(EnumFixture::NUMBER, $value->getValue());
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testInvalidValue1()
    {
        new EnumFixture("test");
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testInvalidValue2()
    {
        new EnumFixture(1234);
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testInvalidValue3()
    {
        new EnumFixture(null);
    }

    /**
     * __toString()
     */
    public function testToString()
    {
        $value = new EnumFixture(EnumFixture::FOO);
        $this->assertEquals(EnumFixture::FOO, (string) $value);

        $value = new EnumFixture(EnumFixture::BAR);
        $this->assertEquals(EnumFixture::BAR, (string) $value);

        $value = new EnumFixture(EnumFixture::NUMBER);
        $this->assertEquals((string) EnumFixture::NUMBER, (string) $value);
    }

    /**
     * toArray()
     */
    public function testToArray()
    {
        $values = EnumFixture::toArray();
        $this->assertInternalType("array", $values);
        $expectedValues = array(
            "FOO"    => EnumFixture::FOO,
            "BAR"    => EnumFixture::BAR,
            "NUMBER" => EnumFixture::NUMBER,
        );
        $this->assertEquals($expectedValues, $values);
    }

    /**
     * __callStatic()
     */
    public function testStaticAccess()
    {
        $this->assertEquals(new EnumFixture(EnumFixture::FOO), EnumFixture::FOO());
        $this->assertEquals(new EnumFixture(EnumFixture::BAR), EnumFixture::BAR());
        $this->assertEquals(new EnumFixture(EnumFixture::NUMBER), EnumFixture::NUMBER());
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage No static method or enum constant 'UNKNOWN' in class UnitTest\MyCLabs\Enum\Enum\EnumFixture
     */
    public function testBadStaticAccess()
    {
        EnumFixture::UNKNOWN();
    }

}

/**
 * Fixture class
 * @method static EnumFixture FOO()
 * @method static EnumFixture BAR()
 * @method static EnumFixture NUMBER()
 */
class EnumFixture extends Enum
{

    const FOO = "foo";
    const BAR = "bar";
    const NUMBER = 42;

}
