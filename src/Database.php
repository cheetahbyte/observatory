<?php
declare(strict_types=1);

namespace KeplerObservatory;

use PDO;

final class Database
{
    public function pdo(): PDO
    {
        $pdo = new PDO(
            $_ENV['DATABASE_URL'],
            $_ENV['DATABASE_USER'],
            $_ENV['DATABASE_PASSWORD'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        return $pdo;
    }
}


?>