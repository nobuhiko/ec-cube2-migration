<?php

declare(strict_types=1);

namespace Eccube2\Migration\Command;

trait DatabaseConnectionTrait
{
    /**
     * Get database connection and type
     *
     * @return array{0: \PDO|\SC_Query, 1: string}
     */
    protected function getConnection(): array
    {
        // Try SC_Query first (when running within EC-CUBE)
        if (defined('DB_TYPE') && class_exists('SC_Query')) {
            return [\SC_Query::getSingletonInstance(), DB_TYPE];
        }

        // Fall back to PDO
        if (defined('DB_TYPE')) {
            $pdo = $this->createPdoConnection();
            return [$pdo, DB_TYPE];
        }

        throw new \RuntimeException(
            'Database configuration not found. ' .
            'Please run this command from EC-CUBE root directory or configure database connection.'
        );
    }

    /**
     * Create PDO connection from EC-CUBE config constants
     */
    protected function createPdoConnection(): \PDO
    {
        $dbType = DB_TYPE;
        $dbServer = defined('DB_SERVER') ? DB_SERVER : 'localhost';
        $dbPort = defined('DB_PORT') ? DB_PORT : null;
        $dbName = defined('DB_NAME') ? DB_NAME : '';
        $dbUser = defined('DB_USER') ? DB_USER : '';
        $dbPassword = defined('DB_PASSWORD') ? DB_PASSWORD : '';

        switch ($dbType) {
            case 'mysqli':
            case 'mysql':
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=utf8',
                    $dbServer,
                    $dbName
                );
                if ($dbPort) {
                    $dsn .= ';port=' . $dbPort;
                }
                break;

            case 'pgsql':
            case 'postgres':
            case 'postgresql':
                $dsn = sprintf(
                    'pgsql:host=%s;dbname=%s',
                    $dbServer,
                    $dbName
                );
                if ($dbPort) {
                    $dsn .= ';port=' . $dbPort;
                }
                break;

            case 'sqlite3':
            case 'sqlite':
                $dsn = sprintf('sqlite:%s', $dbName);
                break;

            default:
                throw new \RuntimeException(sprintf('Unsupported database type: %s', $dbType));
        }

        return new \PDO($dsn, $dbUser, $dbPassword, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
    }
}
