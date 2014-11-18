#!/usr/bin/env php
<?php
require "vendor/autoload.php";

use Constant\Timelog\Command\TimelogCommand;
use Constant\Timelog\TimelogApplication;
use Symfony\Component\Console\Application;

$application = new TimelogApplication();
$application->add(new TimelogCommand);
$application->run();