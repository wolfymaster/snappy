<?php

namespace Knp\Snappy\Tests;

use Knp\Snappy\Image;

class ImageTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateInstance()
    {
        $this->assertInstanceOf('\Knp\Snappy\Image', new Image());
    }
}
