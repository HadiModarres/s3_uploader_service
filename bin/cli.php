#!/usr/bin/env php
<?php

if ( is_file( __DIR__ . '/../../../autoload.php' ) ) {

    require_once __DIR__ . '/../../../autoload.php';

}

if ( is_file( __DIR__ . '/../vendor/autoload.php' ) ) {

    require_once __DIR__ . '/../vendor/autoload.php';

}

use Symfony\Component\Console\Application;
use UploaderService\Command;

$app = new Application();

$app->add( new Command\Upload() );

$app->run();
