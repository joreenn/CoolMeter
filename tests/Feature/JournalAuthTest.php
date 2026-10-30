<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $invite = 'test-invite-token-with-more-than-forty-characters-12345';

    protected function setUp(): void
    {
        parent::setUp();
        config(['journal.require_login' => true, 'journal.setup_token_hash' => hash('sha256', $this->invite)]);
    }

    public function test_online_journal_redirects_guests_and_protects_every_api(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->getJson('/api/state')->assertUnauthorized();
        $this->get('/api/state')->assertUnauthorized()->assertJsonPath('message', 'Please sign in to your journal.');
        $this->getJson('/api/export')->assertUnauthorized();
        $this->postJson('/api/sessions', [])->assertUnauthorized();
        $this->postJson('/api/sessions/start', [])->assertUnauthorized();
        $this->postJson('/api/estimate', [])->assertUnauthorized();
        $this->putJson('/api/settings', [])->assertUnauthorized();
    }

    public function test_anonymous_registration_and_invalid_invites_are_unavailable(): void
    {
        $this->post('/register', [])->assertNotFound();
        $this->get('/setup/'.str_repeat('x', 50))->assertNotFound();
        $this->post('/setup/'.str_repeat('x', 50), [])->assertNotFound();
        config(['journal.setup_token_hash' => null]);
        $this->get('/setup/'.$this->invite)->assertNotFound();
    }

    public function test_invite_creates_one_owner_and_cannot_be_reused(): void
    {
        $this->get('/setup/'.$this->invite)->assertOk()->assertSee('Your journal, just for you.');
        $this->post('/setup/'.$this->invite, ['email' => 'owner@example.test', 'password' => 'test-password-12345', 'password_confirmation' => 'test-password-12345'])->assertRedirect('/');
        $this->assertAuthenticated();
        $owner = User::sole();
        $this->assertSame(1, $owner->id);
        $this->assertNotSame('test-password-12345', $owner->password);
        $this->get('/setup/'.$this->invite)->assertNotFound();
        $this->post('/setup/'.$this->invite, [])->assertNotFound();
        $this->getJson('/api/state')->assertOk();
    }

    public function test_setup_rejects_short_or_mismatched_passwords(): void
    {
        $this->post('/setup/'.$this->invite, ['email' => 'owner@example.test', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_rejects_wrong_password_then_allows_owner(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.test', 'password' => 'test-password-12345']);
        $this->post('/login', ['email' => $owner->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $owner->email, 'password' => 'test-password-12345'])->assertRedirect('/');
        $this->assertAuthenticatedAs($owner);
        $this->get('/')->assertOk()->assertSee('Sign out');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->getJson('/api/state')->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'owner@example.test', 'password' => 'wrong'])->assertStatus(302);
        }
        $this->post('/login', ['email' => 'owner@example.test', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_home_mode_can_remain_available_without_login(): void
    {
        config(['journal.require_login' => false]);
        $this->get('/')->assertOk();
        $this->get('/login')->assertRedirect('/');
        $this->getJson('/api/state')->assertOk();
    }

    public function test_cloud_ui_does_not_claim_data_is_stored_on_the_pc(): void
    {
        config(['journal.hosted' => true]);
        $this->actingAs(User::factory()->create())->get('/')->assertOk()
            ->assertSee('Your journal, wherever you are')->assertDontSee('Keep the PC and app server running.');
    }
}
