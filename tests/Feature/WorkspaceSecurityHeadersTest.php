<?php

namespace Tests\Feature;

use Tests\TestCase;

class WorkspaceSecurityHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_workspace_response_has_security_headers(): void
    {
        $this->get('/app')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_workspace_nested_route_has_same_headers(): void
    {
        $this->get('/app/projects/123')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_admin_login_and_app_api_routes_are_not_changed_by_workspace_html_middleware(): void
    {
        $this->get('/admin/login')->assertOk();
        $this->getJson('/app/api/session')->assertOk()
            ->assertHeaderMissing('Permissions-Policy');
    }
}
