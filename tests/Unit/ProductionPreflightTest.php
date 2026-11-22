<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../scripts/production-preflight.php';

class ProductionPreflightTest extends TestCase
{
    private function validConfiguration(): array
    {
        return [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://tasks.example.org',
            'APP_KEY' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'SESSION_SECURE_COOKIE' => 'true',
            'SESSION_HTTP_ONLY' => 'true',
            'SESSION_SAME_SITE' => 'lax',
            'SESSION_DRIVER' => 'database',
            'QUEUE_CONNECTION' => 'database',
            'DB_PASSWORD' => 'synthetic-not-a-real-password',
            'LOG_LEVEL' => 'warning',
            'MAIL_MAILER' => 'smtp',
            'MAIL_FROM_ADDRESS' => 'taskforge@example.org',
            'BROADCAST_CONNECTION' => 'log',
        ];
    }

    public function test_valid_production_configuration_passes_static_checks(): void
    {
        $this->assertNotContains(false, productionChecks($this->validConfiguration()));
    }

    public function test_unsafe_configuration_fails_without_disclosing_values(): void
    {
        $env = array_merge($this->validConfiguration(), [
            'APP_DEBUG' => 'true',
            'APP_URL' => 'http://localhost:8080',
            'SESSION_SECURE_COOKIE' => 'false',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'log',
            'DB_PASSWORD' => 'password',
        ]);
        $checks = productionChecks($env);
        $this->assertFalse($checks['Debugging disabled']);
        $this->assertFalse($checks['HTTPS public application URL']);
        $this->assertFalse($checks['HTTPS-only session cookies']);
        $this->assertFalse($checks['Persistent background queue']);
        $this->assertFalse($checks['Explicit outbound mail decision']);
        $this->assertFalse($checks['Protected database credentials']);
        $this->assertArrayNotHasKey('password', $checks);
    }

    public function test_reverb_requires_explicit_secure_configuration(): void
    {
        $env = $this->validConfiguration();
        $env['BROADCAST_CONNECTION'] = 'reverb';
        $this->assertFalse(productionChecks($env)['Reverb server configured']);
        $env += [
            'REVERB_APP_ID' => 'synthetic',
            'REVERB_APP_KEY' => 'synthetic',
            'REVERB_APP_SECRET' => 'synthetic',
            'REVERB_SCHEME' => 'https',
            'REVERB_HOST' => 'wss.example.org',
        ];
        $this->assertTrue(productionChecks($env)['Reverb server configured']);
    }

    public function test_missing_configuration_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        readEnvironment('/definitely/absent/taskforge-env');
    }
}
