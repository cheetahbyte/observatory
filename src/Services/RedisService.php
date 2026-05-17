<?php
declare(strict_types=1);

namespace KeplerObservatory\Services;

use Predis\Client;
use Predis\ClientInterface;

final class RedisService
{
    private ClientInterface $client;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?? new Client($_ENV['REDIS_URL'] ?? 'tcp://redis:6379');
    }

    public function get(string $key): ?string
    {
        $value = $this->client->get($key);

        return $value === null ? null : (string) $value;
    }

    public function set(string $key, string $value, ?int $ttlSeconds = null): void
    {
        if ($ttlSeconds !== null && $ttlSeconds > 0) {
            $this->client->setex($key, $ttlSeconds, $value);
            return;
        }

        $this->client->set($key, $value);
    }

    public function delete(string $key): void
    {
        $this->client->del([$key]);
    }

    public function has(string $key): bool
    {
        return (bool) $this->client->exists($key);
    }

    public function flush(): void
    {
        $this->client->flushdb();
    }

    public function ping(): bool
    {
        return (string) $this->client->ping() === 'PONG';
    }

    public function client(): ClientInterface
    {
        return $this->client;
    }
}
