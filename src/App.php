<?php

declare(strict_types=1);

namespace KeplerObservatory;

use KeplerObservatory\Controllers\AppcastController;
use KeplerObservatory\Controllers\LatestController;
use KeplerObservatory\Repositories\TelemetryRepository;
use KeplerObservatory\Services\AppcastService;
use KeplerObservatory\Services\RedisService;
use Slim\Factory\AppFactory;

final class App
{
    public static function create(): \Slim\App
    {
        $app = AppFactory::create();

        $telemetryRepository = null;

        try {
            $database = new Database();
            $telemetryRepository = new TelemetryRepository($database->pdo());
        } catch (\Throwable $error) {
            error_log('Telemetry disabled: ' . $error->getMessage());
        }

        $appcastService = new AppcastService(new RedisService());

        $controller = new AppcastController(
            $telemetryRepository,
            $appcastService
        );

        $app->get('/appcast.xml', [$controller, 'show']);

        $latestController = new LatestController($appcastService);
        $app->get('/latest', [$latestController, 'latest']);
        $app->get('/latest-changelog', [$latestController, 'latestChangelog']);
        $app->get('/latest-version', [$latestController, 'latestVersion']);

        return $app;
    }
}
