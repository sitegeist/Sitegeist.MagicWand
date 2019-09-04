<?php
namespace Sitegeist\MagicWand\DBAL;

/*                                                                        *
 * This script belongs to the Neos Flow package "Sitegeist.MagicWand".    *
 *                                                                        */

use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;
use Neos\Flow\Core\Bootstrap;

class SimpleDBAL {
    /**
     * @param string $driver
     * @param string $host
     * @param int $port
     * @param string $username
     * @param string $password
     * @param string $database
     * @return string
     */
    public function buildCmd(string $driver, ?string $host, int $port, string $username, string $password, string $database): string
    {
        if ($driver === 'pdo_mysql') {
            return sprintf('mysql --host=%s --port=%s --user=%s --password=%s %s', escapeshellarg($host), escapeshellarg($port), escapeshellarg($username), escapeshellarg($password), escapeshellarg($database));
        } else if ($driver === 'pdo_pgsql') {
            return sprintf('PGOPTIONS=--client-min-messages=warning PGPASSWORD=%s psql --quiet --host=%s --port=%s --username=%s --dbname=%s', escapeshellarg($password), escapeshellarg($host), escapeshellarg($port), escapeshellarg($username), escapeshellarg($database));
        }
    }

    /**
     * @param string $driver
     * @param string $host
     * @param int $port
     * @param string $username
     * @param string $password
     * @param string $database
     * @return string
     */
    public function buildDumpCmd(string $driver, ?string $host, int $port, string $username, string $password, string $database): string
    {
        if ($driver === 'pdo_mysql') {
            return sprintf('mysqldump --single-transaction --add-drop-table --host=%s --port=%d --user=%s --password=\'%s\' %s', escapeshellarg($host), escapeshellarg($port), escapeshellarg($username), escapeshellarg($password), escapeshellarg($database));
        } else if ($driver === 'pdo_pgsql') {
            return sprintf('PGPASSWORD=%s pg_dump --host=%s --port=%s --username=%s --dbname=%s --schema=public --no-owner --no-privileges', escapeshellarg($password), escapeshellarg($host), escapeshellarg($port), escapeshellarg($username), escapeshellarg($database));
        }
    }

    /**
     * @param string $driver
     * @param string $database
     * @return string
     */
    public function flushDbSql(string $driver, string $database): string
    {
        if ($driver === 'pdo_mysql') {
            return sprintf('DROP DATABASE %s; CREATE DATABASE %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;', $this->quoteIdentifier($driver, $database), $this->quoteIdentifier($driver, $database));
        } else if ($driver === 'pdo_pgsql') {
            return sprintf('DROP SCHEMA public CASCADE; CREATE SCHEMA public;', $this->quoteIdentifier($driver, $database), $this->quoteIdentifier($driver, $database));
        }
    }

    /**
     * @param string $driver
     * @param string $str
     * @return string
     */
    public function quoteIdentifier(string $driver, string $str): string
    {
        if ($driver === 'pdo_mysql') {
            return sprintf('`%s`', $str);
        } else if ($driver === 'pdo_pgsql') {
            return sprintf('"%s"', $str);
        }
    }

    /**
     * @param string $driver
     * @return integer
     */
    public function getDefaultPort(string $driver): int
    {
        if ($driver === 'pdo_mysql') {
            return 3306;
        } else if ($driver === 'pdo_pgsql') {
            return 5432;
        }
    }

    /**
     * @param string $driver
     * @return boolean
     */
    public function driverIsSupported(string $driver): bool
    {
        if ($driver === 'pdo_mysql') {
            return true;
        } else if ($driver === 'pdo_pgsql') {
            return true;
        }
        return false;
    }
}
