<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_homepage_redirects_to_admin_login(): void
    {
        // The application intentionally routes / to /admin/login.
        $this->get('/')->assertRedirect(route('admin.login'));
    }
}
