<?php

declare(strict_types=1);

namespace KeplerObservatory\Services;

use Aws\S3\S3Client;
use RuntimeException;

final class AppcastService
{
    private const REDIS_APPCAST_KEY = 'appcast.xml';
    private const REDIS_LATEST_KEY = 'latest';
    private const REDIS_APPCAST_TTL_SECONDS = 180;

    private S3Client $s3;
    private ?RedisService $redis;
    private string $cachePath;
    private int $ttlSeconds;

    private static ?string $memoryCache = null;
    private static int $memoryCacheUntil = 0;

    public function __construct(?RedisService $redis = null)
    {
        $this->redis = $redis;
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

        $content = $this->readRedisCache();
        if ($content !== null) {
            $this->writeMemoryCache($content);

            return $content;
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
            $this->writeRedisCache($content);

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

    /**
     * @return array{version: string|null, build: string|null, url: string|null, changelog: string|null}
     */
    public function latestRelease(): array
    {
        return $this->latestReleaseFromAppcast($this->read());
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

    private function readRedisCache(): ?string
    {
        if ($this->redis === null) {
            return null;
        }

        try {
            return $this->redis->get(self::REDIS_APPCAST_KEY);
        } catch (\Throwable $error) {
            error_log('Redis appcast cache read failed: ' . $error->getMessage());

            return null;
        }
    }

    private function writeRedisCache(string $content): void
    {
        if ($this->redis === null) {
            return;
        }

        try {
            $this->redis->set(self::REDIS_APPCAST_KEY, $content, self::REDIS_APPCAST_TTL_SECONDS);

            $latestVersion = $this->latestReleaseFromAppcast($content)['version'];
            if ($latestVersion !== null) {
                $this->redis->set(self::REDIS_LATEST_KEY, $latestVersion);
            }
        } catch (\Throwable $error) {
            error_log('Redis appcast cache write failed: ' . $error->getMessage());
        }
    }

    /**
     * @return array{version: string|null, build: string|null, url: string|null, changelog: string|null}
     */
    private function latestReleaseFromAppcast(string $content): array
    {
        $previousXmlErrorHandling = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_clear_errors();
        libxml_use_internal_errors($previousXmlErrorHandling);

        if (!$xml instanceof \SimpleXMLElement || !isset($xml->channel->item)) {
            return [
                'version' => null,
                'build' => null,
                'url' => null,
                'changelog' => null,
            ];
        }

        $sparkleNamespace = $xml->getNamespaces(true)['sparkle']
            ?? 'http://www.andymatuschak.org/xml-namespaces/sparkle';

        foreach ($xml->channel->item as $item) {
            $sparkle = $item->children($sparkleNamespace);
            $shortVersion = trim((string) ($sparkle->shortVersionString ?? ''));
            $buildVersion = trim((string) ($sparkle->version ?? ''));
            $title = trim((string) ($item->title ?? ''));
            $changelog = trim((string) ($item->description ?? ''));

            foreach ($item->enclosure as $enclosure) {
                $attributes = $enclosure->attributes();
                $sparkleAttributes = $enclosure->attributes($sparkleNamespace);
                $url = trim((string) ($attributes['url'] ?? ''));

                if ($shortVersion === '') {
                    $shortVersion = trim((string) ($sparkleAttributes['shortVersionString'] ?? ''));
                }

                if ($buildVersion === '') {
                    $buildVersion = trim((string) ($sparkleAttributes['version'] ?? ''));
                }

                return [
                    'version' => $shortVersion !== '' ? $shortVersion : ($title !== '' ? $title : null),
                    'build' => $buildVersion !== '' ? $buildVersion : null,
                    'url' => $url !== '' ? $url : null,
                    'changelog' => $changelog !== '' ? $changelog : null,
                ];
            }
        }

        return [
            'version' => null,
            'build' => null,
            'url' => null,
            'changelog' => null,
        ];
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
