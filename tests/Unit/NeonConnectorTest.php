<?php

namespace Tests\Unit;

use App\Database\NeonPostgresConnector;
use PHPUnit\Framework\TestCase;

class NeonConnectorTest extends TestCase
{
    private function dsn(string $host): string
    {
        $connector = new class extends NeonPostgresConnector {
            public function inspectDsn(array $config): string { return $this->getDsn($config); }
        };

        return $connector->inspectDsn(['host' => $host, 'database' => 'journal', 'port' => 5432, 'sslmode' => 'require']);
    }

    public function test_neon_endpoint_is_supplied_without_pooler_suffix_and_ssl_is_retained(): void
    {
        $dsn = $this->dsn('ep-example-name-123-pooler.ap-southeast-1.aws.neon.tech');
        $this->assertStringContainsString("options='endpoint=ep-example-name-123'", $dsn);
        $this->assertStringContainsString('sslmode=require', $dsn);
    }

    public function test_regular_postgres_hosts_do_not_receive_neon_options(): void
    {
        $this->assertStringNotContainsString('endpoint=', $this->dsn('postgres.example.test'));
    }
}
