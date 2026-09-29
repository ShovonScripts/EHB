<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Verify that production mode (APP_DEBUG=false) does not leak
 * stack traces, file paths, or query contents in error responses.
 */
class ProductionDebugLeakTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        parent::tearDown();

        Config::set('app.debug', true);
    }

    /** With APP_DEBUG=false, a 500 response contains no stack trace or file paths. */
    public function test_production_mode_hides_debug_details(): void
    {
        Config::set('app.debug', false);

        $response = $this->get('/trigger-500');

        $response->assertStatus(500);

        $content = $response->content();

        $this->assertStringNotContainsString('Stack trace', $content);
        $this->assertStringNotContainsString('/vendor/', $content);
        $this->assertStringNotContainsString('/app/', $content);
        $this->assertStringNotContainsString('PDOException', $content);
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('Whoops', $content);
    }
}
