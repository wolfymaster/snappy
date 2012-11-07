<?php

namespace Knp\Snappy\Tests;

use Knp\Snappy\Pdf;

class PdfTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateInstance()
    {
        $this->assertInstanceOf('\Knp\Snappy\Pdf', new Pdf());
    }
}
