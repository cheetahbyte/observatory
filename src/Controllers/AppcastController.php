<?php
declare(strict_types=1);

namespace KeplerObservatory\Controllers;

use Fig\Http\Message\StatusCodeInterface;
use KeplerObservatory\Repositories\TelemetryRepository;
use KeplerObservatory\Services\AppcastService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AppcastController
{
    public function __construct(
        private ?TelemetryRepository $telemetry,
        private AppcastService $appcast,
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $server = $request->getServerParams();

        $ip = $server["REMOTE_ADDR"] ?? '';
        $ipHash = hash('sha256', $ip . ($_ENV["IP_HASH_SALT"] ?? ""));

        $telemetry = $this->telemetry;
        $telemetryData = [
            'app_version' => $query['appVersion'] ?? null,
            'app_build' => $query['appBuild'] ?? null,
            'sparkle_version' => $query['sparkleVersion'] ?? null,
            'os_version' => $query['osVersion'] ?? null,
            'os_build' => $query['osBuild'] ?? null,
            'arch' => $query['arch'] ?? null,
            'language' => $query['language'] ?? null,
            'ip_hash' => $ipHash,
            'user_agent' => $server['HTTP_USER_AGENT'] ?? null,
            'raw_query' => json_encode($query, JSON_THROW_ON_ERROR),
        ];

        register_shutdown_function(function () use ($telemetry, $telemetryData): void {
            try {
                $telemetry?->insert($telemetryData);
            } catch (\Throwable $error) {
                error_log('Telemetry insert failed: ' . $error->getMessage());
            }
        });

        try {
            $appcast = $this->appcast->read();
        } catch (\Throwable $error) {
            error_log("Appcast read failed " . $error->getMessage());
            $response->getBody()->write("Appcast temporarily unavailable");
            return $response
                ->withStatus(StatusCodeInterface::STATUS_SERVICE_UNAVAILABLE)
                ->withHeader("Content-Type", "text/plain; charset=utf-8");
        }

        $response->getBody()->write($appcast);

        return $response
            ->withHeader('Content-Type', 'application/rss+xml; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }
}
?>