<?php

require_once 'vendor/autoload.php';
require_once 'src/Nubeiro/ActiveCollab/Projects/DumpProjectToCsv.php';

use Nubeiro\ActiveCollab\Projects\ListTasksCommand;
use Symfony\Component\Console\Application;

$app = new Application();
$app->add(new ListTasksCommand());
$app->run();