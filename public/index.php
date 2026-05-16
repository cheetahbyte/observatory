<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use KeplerObservatory\App;

require __DIR__ . "/../vendor/autoload.php";

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$app = App::create();
$app->run();
