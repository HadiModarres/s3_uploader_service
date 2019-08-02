#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use UploaderService\Command;

$app = new Application();

$app->add( new Command\Upload() );

$app->run();
