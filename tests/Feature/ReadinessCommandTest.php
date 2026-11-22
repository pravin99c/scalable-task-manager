<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_command_checks_database_and_cache_without_mutations(): void
    {
        config([
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'app.debug' => false,
        ]);

        $this->artisan('taskforge:readiness-check')
            ->expectsOutput('PASS: database connection responded to a read-only query.')
            ->expectsOutput('PASS: configured cache store responded to a read.')
            ->assertExitCode(0);
    }

    public function test_production_debug_or_sync_queue_blocks_release_readiness(): void
    {
        $original = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            config([
                'cache.default' => 'array',
                'queue.default' => 'sync',
                'app.debug' => true,
            ]);

            $this->artisan('taskforge:readiness-check')
                ->expectsOutput('FAIL: production requires a persistent queue connection.')
                ->expectsOutput('FAIL: production debug mode must be disabled.')
                ->assertExitCode(1);
        } finally {
            app()->detectEnvironment(fn () => $original);
        }
    }

    public function test_production_safe_configuration_can_pass_static_and_connectivity_checks(): void
    {
        $original = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            config([
                'cache.default' => 'array',
                'queue.default' => 'database',
                'app.debug' => false,
            ]);
            $this->artisan('taskforge:readiness-check')->assertExitCode(0);
        } finally {
            app()->detectEnvironment(fn () => $original);
        }
    }
}
