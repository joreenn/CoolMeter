<?php

namespace App\Database;

use Illuminate\Database\Connectors\PostgresConnector;

class NeonPostgresConnector extends PostgresConnector
{
    protected function getDsn(array $config)
    {
        $dsn = parent::getDsn($config);
        $host = $config['host'] ?? '';
        if (preg_match('/^(ep-[a-z0-9-]+)\.[a-z0-9.-]+\.neon\.tech$/', $host, $matches)) {
            // Some bundled libpq clients lack SNI. Neon accepts an explicit endpoint
            // startup option; no password rewriting or SSL downgrade is needed.
            $endpoint = preg_replace('/-pooler$/', '', $matches[1]);
            $dsn .= ";options='endpoint={$endpoint}'";
        }

        return $dsn;
    }
}
