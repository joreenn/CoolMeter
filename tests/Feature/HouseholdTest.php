<?php

namespace Tests\Feature;

use App\Models\CoolingSession;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HouseholdTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['journal.require_login' => true]);
        $this->owner = User::factory()->create(['id' => 1, 'email' => 'owner@example.test']);
    }

    private function invite(string $email = 'brother@example.test'): string
    {
        return $this->actingAs($this->owner)->postJson('/api/household/invites', ['email' => $email])
            ->assertCreated()->json('invite_url');
    }

    private function joinData(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Brother', 'email' => 'brother@example.test',
            'password' => 'a-private-password-123', 'password_confirmation' => 'a-private-password-123',
        ], $overrides);
    }

    public function test_invitation_is_private_single_use_and_preserves_owner_account(): void
    {
        $ownerPassword = $this->owner->password;
        $url = $this->invite('Brother@Example.test');
        $token = basename(parse_url($url, PHP_URL_PATH));
        $invite = HouseholdInvitation::sole();
        $this->assertNotSame($token, $invite->token_hash);
        $this->assertSame(hash('sha256', $token), $invite->token_hash);
        $this->getJson('/api/household')->assertOk()->assertJsonPath('owner', true)
            ->assertJsonPath('invites.0.email', 'brother@example.test')->assertDontSee($token)->assertDontSee($invite->token_hash);
        $this->post('/logout');
        $this->get($url)->assertOk()->assertSee('One AC. A shared journal.')->assertSee('brother@example.test');
        $this->post($url, $this->joinData())->assertRedirect('/');
        $member = User::where('email', 'brother@example.test')->sole();
        $this->assertAuthenticatedAs($member);
        $this->assertFalse($member->isJournalOwner());
        $this->assertSame($ownerPassword, $this->owner->fresh()->password);
        $this->get($url)->assertNotFound();
        $this->getJson('/api/household')->assertOk()->assertJsonPath('owner', false)
            ->assertJsonCount(2, 'members')->assertJsonCount(0, 'invites');
        $this->actingAs($this->owner)->deleteJson('/api/household/invites/'.$invite->id)->assertConflict();
        $this->assertDatabaseHas('users', ['id' => $member->id]);
        $this->post('/logout');
        $this->post('/login', ['email' => 'BROTHER@example.test', 'password' => 'a-private-password-123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($member);
    }

    public function test_invitee_can_take_over_same_timer_and_saved_history_is_preserved(): void
    {
        $url = $this->invite();
        $id = $this->postJson('/api/sessions/start', ['temperature' => 26, 'fan' => 'low', 'room_heat' => 'warm', 'notes' => 'Shared AC'])->assertCreated()->json('id');
        $this->post('/logout');
        $this->post($url, $this->joinData())->assertRedirect('/');
        $this->getJson('/api/state')->assertOk()->assertJsonPath('active.id', $id);
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(15));
        \Illuminate\Support\Carbon::setTestNow(CarbonImmutable::now());
        try {
            $this->putJson('/api/sessions/'.$id.'/eco', ['eco_at' => CarbonImmutable::now()->subMinutes(5)->format('Y-m-d\TH:i')])->assertOk();
            $this->postJson('/api/sessions/'.$id.'/stop')->assertOk();
            $this->actingAs($this->owner)->getJson('/api/state')->assertOk()->assertJsonCount(1, 'sessions')->assertJsonPath('active', null);
            $saved = CoolingSession::findOrFail($id)->getAttributes();
            $member = User::where('email', 'brother@example.test')->sole();
            $this->deleteJson('/api/household/members/'.$member->id)->assertOk();
            $this->assertSame($saved, CoolingSession::findOrFail($id)->getAttributes());
            $this->assertDatabaseHas('users', ['id' => 1]);
        } finally {
            CarbonImmutable::setTestNow();
            \Illuminate\Support\Carbon::setTestNow();
        }
    }

    public function test_only_owner_manages_access_and_guests_cannot_read_household(): void
    {
        $this->getJson('/api/household')->assertUnauthorized();
        $this->postJson('/api/household/invites', ['email' => 'any@example.test'])->assertUnauthorized();
        $member = User::factory()->create();
        $url = $this->invite();
        $invite = HouseholdInvitation::sole();
        $this->actingAs($member)->postJson('/api/household/invites', ['email' => 'any@example.test'])->assertForbidden();
        $this->deleteJson('/api/household/invites/'.$invite->id)->assertForbidden();
        $this->deleteJson('/api/household/members/1')->assertForbidden();
        $this->actingAs($this->owner)->deleteJson('/api/household/members/1')->assertUnprocessable();
        $this->assertDatabaseCount('users', 2);
        $this->assertNull($invite->fresh()->revoked_at);
    }

    public function test_cancelled_expired_and_superseded_invites_cannot_be_used(): void
    {
        $first = $this->invite();
        $second = $this->invite();
        $this->get($first)->assertNotFound();
        $invite = HouseholdInvitation::orderByDesc('id')->first();
        $this->deleteJson('/api/household/invites/'.$invite->id)->assertOk();
        $this->get($second)->assertNotFound();
        $expired = $this->invite('another@example.test');
        HouseholdInvitation::orderByDesc('id')->first()->update(['expires_at' => now()->subMinute()]);
        $this->get($expired)->assertNotFound();
        $this->post('/logout');
        $this->post($second, $this->joinData())->assertNotFound();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invite_is_email_bound_and_account_requires_secure_password(): void
    {
        $url = $this->invite();
        $this->post('/logout');
        $this->post($url, $this->joinData(['email' => 'stranger@example.test']))->assertSessionHasErrors('email');
        $this->post($url, $this->joinData(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 1);
        $this->assertNull(HouseholdInvitation::sole()->accepted_at);
    }

    public function test_existing_email_cannot_be_invited_and_signed_in_owner_is_not_replaced(): void
    {
        $this->actingAs($this->owner)->postJson('/api/household/invites', ['email' => 'OWNER@example.test'])->assertUnprocessable();
        $url = $this->invite();
        $this->post($url, $this->joinData())->assertConflict();
        $this->assertAuthenticatedAs($this->owner);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_removing_member_invalidates_database_sessions_without_touching_cooling_history(): void
    {
        $member = User::factory()->create(['email' => 'brother@example.test']);
        DB::table('sessions')->insert(['id' => 'member-session', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('sessions')->insert(['id' => 'owner-session', 'user_id' => 1, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($this->owner)->deleteJson('/api/household/members/'.$member->id)->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $member->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'member-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'owner-session']);
        $this->post('/logout');
        $this->getJson('/api/state')->assertUnauthorized();
    }

    public function test_home_mode_does_not_open_invitation_management_without_authentication(): void
    {
        config(['journal.require_login' => false]);
        $this->getJson('/api/household')->assertOk()->assertJsonPath('enabled', false);
        $this->postJson('/api/household/invites', ['email' => 'brother@example.test'])->assertForbidden();
        $this->get('/join/'.str_repeat('x', 64))->assertNotFound();
    }

    public function test_removed_account_id_is_never_assigned_to_a_new_member(): void
    {
        $firstInvite = $this->invite();
        $this->post('/logout');
        $this->post($firstInvite, $this->joinData())->assertRedirect('/');
        $removedId = User::where('email', 'brother@example.test')->value('id');
        $this->actingAs($this->owner)->deleteJson('/api/household/members/'.$removedId)->assertOk();
        $nextInvite = $this->invite('sister@example.test');
        $this->post('/logout');
        $this->post($nextInvite, $this->joinData(['name' => 'Sister', 'email' => 'sister@example.test']))->assertRedirect('/');
        $newId = User::where('email', 'sister@example.test')->value('id');
        $this->assertGreaterThan($removedId, $newId);

        // A response already in flight during revocation could save its old
        // login session again. Its user ID must remain unable to authenticate.
        $sessionKey = auth()->guard()->getName();
        $this->post('/logout');
        $this->withSession([$sessionKey => $removedId]);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/state')->assertUnauthorized();
    }
}
