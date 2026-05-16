<?php

declare(strict_types=1);

namespace KeplerObservatory\Repositories;

use PDO;

final class TelemetryRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function insert(array $data): void
    {
        $stmt = $this->pdo->prepare(
            <<<SQL
            INSERT INTO update_checks (
                app_version,
                app_build,
                sparkle_version,
                os_version,
                os_build,
                arch,
                language,
                ip_hash,
                user_agent,
                raw_query
            ) VALUES (
                :app_version,
                :app_build,
                :sparkle_version,
                :os_version,
                :os_build,
                :arch,
                :language,
                :ip_hash,
                :user_agent,
                :raw_query::jsonb
            )
            SQL
        );

        $stmt->execute($data);
    }
}