<?php

namespace Tests\Feature;

use App\Jobs\DeliverTaskForgeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TaskForgeSpaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_spa_route_loads_without_overwriting_admin_routes(): void
    {
        $this->get('/app')->assertOk()->assertSee('taskforge-root');
        $this->get('/admin/login')->assertOk();
    }

    public function test_anonymous_session_provides_csrf_token_without_user_data(): void
    {
        $this->getJson('/app/api/session')->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonStructure(['csrf_token']);
    }

    public function test_react_login_uses_web_session_and_can_access_sanctum_api(): void
    {
        $user = User::factory()->create([
            'email' => 'web-session@example.test',
        ]);

        $this->postJson('/app/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('data.id', $user->id);

        $this->getJson('/app/api/session')
            ->assertOk()->assertJsonPath('data.email', $user->email);
        $this->getJson('/api/v2/projects')->assertOk()->assertJsonPath('meta.total', 0);
        $this->postJson('/app/api/logout')->assertOk()->assertJsonPath('data', null);
    }

    public function test_registration_creates_unverified_account_with_queued_challenge(): void
    {
        Queue::fake([DeliverTaskForgeMail::class]);

        $this->postJson('/app/api/register', [
            'name' => 'Workspace User',
            'email' => 'workspace@example.test',
            'password' => 'long-safe-password-123',
            'password_confirmation' => 'long-safe-password-123',
        ])->assertCreated()->assertJsonPath('data.email_verified_at', null);

        $this->assertDatabaseHas('email_verification_challenges', ['email' => 'workspace@example.test']);
        Queue::assertPushed(DeliverTaskForgeMail::class);
    }


    public function test_changing_email_logs_out_the_current_browser_session(): void
    {
        Queue::fake([DeliverTaskForgeMail::class]);
        $user = User::factory()->create(['email' => 'old-browser@example.test']);

        $this->postJson('/app/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->patchJson('/api/v2/auth/email', [
            'email' => 'new-browser@example.test',
            'current_password' => 'password',
        ])->assertOk();

        $this->getJson('/app/api/session')->assertOk()->assertJsonPath('data', null);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_an_api_bearer_token_never_counts_as_a_browser_session(): void
    {
        $apiUser = User::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($apiUser);

        $this->getJson('/app/api/session')->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonStructure(['csrf_token']);
    }

    public function test_a_session_with_an_outdated_security_version_is_signed_out(): void
    {
        $user = User::factory()->create(['email' => 'epoch-check@example.test']);
        $this->postJson('/app/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        // The previous login's cookie can still exist on file/Redis
        // session drivers; the per-user version is checked independently.
        $this->withSession(['taskforge_session_version' => -1]);
        $this->getJson('/app/api/session')->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_failed_login_does_not_authenticate_a_user(): void
    {
        User::factory()->create(['email' => 'login@example.test']);
        $this->postJson('/app/api/login', [
            'email' => 'login@example.test',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
        $this->getJson('/app/api/session')->assertJsonPath('data', null);
    }
}
