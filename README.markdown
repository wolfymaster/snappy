# Snappy

Snappy is a PHP5 library allowing thumbnail, snapshot or PDF generation from a url or a html page.
It uses the excellent webkit-based [wkhtmltopdf and wkhtmltoimage](http://code.google.com/p/wkhtmltopdf/)
available on OSX, linux, windows.

You will have to download wkhtmltopdf `0.10.0 >= rc2` in order to use Snappy.

[![Build Status](https://secure.travis-ci.org/KnpLabs/snappy.png?branch=master)](http://travis-ci.org/KnpLabs/snappy)

## Installation using [Composer](http://getcomposer.org/)

Add to your `composer.json`:

```json
{
    "require" :  {
        "knplabs/knp-snappy": "*"
    }
}
```

## Usage

```php
<?php

use Knp\Snappy\Pdf;

$snappy = new Pdf();

// By default Snappy uses path to binary: `/usr/local/bin/wkhtmltopdf`
// but you can change it in two ways
$snappy = new Pdf('/path/to/bin/wkhtmltopdf');
// Or
$snappy = new Pdf();
$snappy->setBinary('/path/to/bin/wkhtmltopdf');

// Display the resulting image in the browser
// by setting the Content-type header to jpg
$snappy = new Pdf();
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="file.pdf"');
echo $snappy->getOutput('http://www.github.com');

// .. or simply save the PDF to a file
$snappy = new Pdf();
$snappy->generateFromHtml('<h1>Bill</h1><p>You owe me money, dude.</p>', '/tmp/bill-123.pdf');

// Pass options to snappy
// Type wkhtmltopdf -H to see the list of options
$snappy = new Pdf();
$snappy->setOption('disable-javascript', true);
$snappy->setOption('no-background', true);
$snappy->setOption('allow', array('/path1', '/path2'));
$snappy->setOption('cookie', array('key' => 'value', 'key2' => 'value2'));
```

## Credits

Snappy has been originally developed by the [KnpLabs](http://knplabs.com) team.
