<?php

declare(strict_types=1);

namespace KeplerObservatory\Services;

use Aws\S3\S3Client;
use RuntimeException;

final class AppcastService
{
    private S3Client $s3;
    private string $cachePath;
    private int $ttlSeconds;

    private static ?string $memoryCache = null;
    private static int $memoryCacheUntil = 0;

    public function __construct()
    {
        $this->s3 = new S3Client([
            'version' => 'latest',
            'region' => $_ENV['S3_REGION'] ?? 'fsn1',
            'endpoint' => $_ENV['S3_ENDPOINT'],
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $_ENV['S3_KEY'],
                'secret' => $_ENV['S3_SECRET'],
            ],
        ]);

        $this->cachePath = dirname(__DIR__, 2) . '/storage/cache/appcast.xml';
        $this->ttlSeconds = (int) ($_ENV['APPCAST_CACHE_TTL'] ?? 60);
    }

    public function read(): string
    {
        if ($this->hasFreshMemoryCache()) {
            return self::$memoryCache;
        }

        if ($this->hasFreshFileCache()) {
            $content = (string) file_get_contents($this->cachePath);
            $this->writeMemoryCache($content);

            return $content;
        }

        try {
            $content = $this->fetchS3();

            $this->writeFileCache($content);
            $this->writeMemoryCache($content);

            return $content;
        } catch (\Throwable $error) {
            if (is_file($this->cachePath)) {
                error_log('Serving stale appcast cache: ' . $error->getMessage());

                $content = (string) file_get_contents($this->cachePath);
                $this->writeMemoryCache($content);

                return $content;
            }

            throw new RuntimeException(
                'Could not fetch appcast from S3 and no cache exists.',
                previous: $error
            );
        }
    }

    private function hasFreshMemoryCache(): bool
    {
        return self::$memoryCache !== null && self::$memoryCacheUntil > time();
    }

    private function writeMemoryCache(string $content): void
    {
        self::$memoryCache = $content;
        self::$memoryCacheUntil = time() + $this->ttlSeconds;
    }

    private function hasFreshFileCache(): bool
    {
        return is_file($this->cachePath)
            && filemtime($this->cachePath) > time() - $this->ttlSeconds;
    }

    private function writeFileCache(string $content): void
    {
        $dir = dirname($this->cachePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->cachePath, $content, LOCK_EX);
    }

    private function fetchS3(): string
    {
        $result = $this->s3->getObject([
            'Bucket' => $_ENV['S3_BUCKET'],
            'Key' => $_ENV['S3_APPCAST_KEY'] ?? 'appcast.xml',
        ]);

        return (string) $result['Body'];
    }
}