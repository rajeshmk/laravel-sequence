<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;

$container = new Container();
Container::setInstance($container);

$translator = new Translator(new ArrayLoader(), 'en');
$container->instance('translator', $translator);
