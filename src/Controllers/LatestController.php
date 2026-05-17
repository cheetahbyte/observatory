<?php

declare(strict_types=1);

namespace KeplerObservatory\Controllers;

use Fig\Http\Message\StatusCodeInterface;
use KeplerObservatory\Services\AppcastService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LatestController
{
    public function __construct(
        private AppcastService $appcast,
    ) {
    }

    public function latest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $release = $this->readLatest($response);
        if ($release instanceof ResponseInterface) {
            return $release;
        }

        if ($release['url'] === null) {
            $response->getBody()->write('Latest download is unavailable');
            return $response
                ->withStatus(StatusCodeInterface::STATUS_NOT_FOUND)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        return $response
            ->withStatus(StatusCodeInterface::STATUS_FOUND)
            ->withHeader('Location', $release['url'])
            ->withHeader('Cache-Control', 'no-store');
    }

    public function latestVersion(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $release = $this->readLatest($response);
        if ($release instanceof ResponseInterface) {
            return $release;
        }

        if ($release['version'] === null) {
            $response->getBody()->write('Latest version is unavailable');
            return $response
                ->withStatus(StatusCodeInterface::STATUS_NOT_FOUND)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        $response->getBody()->write($release['version']);

        return $response
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }

    public function latestChangelog(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $release = $this->readLatest($response);
        if ($release instanceof ResponseInterface) {
            return $release;
        }

        if ($release['changelog'] === null) {
            return $response
                ->withStatus(StatusCodeInterface::STATUS_NO_CONTENT)
                ->withHeader('Cache-Control', 'no-store');
        }

        $response->getBody()->write($release['changelog']);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }

    /**
     * @return array{version: string|null, build: string|null, url: string|null, changelog: string|null}|ResponseInterface
     */
    private function readLatest(ResponseInterface $response): array|ResponseInterface
    {
        try {
            return $this->appcast->latestRelease();
        } catch (\Throwable $error) {
            error_log('Latest release read failed: ' . $error->getMessage());
            $response->getBody()->write('Latest release is temporarily unavailable');

            return $response
                ->withStatus(StatusCodeInterface::STATUS_SERVICE_UNAVAILABLE)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }
    }
}
