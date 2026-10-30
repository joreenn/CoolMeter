<?php

namespace App\Http\Controllers;

use App\Models\HouseholdInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HouseholdController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! config('journal.require_login') || ! $user) {
            return response()->json(['enabled' => false, 'owner' => false, 'current_user' => null, 'members' => [], 'invites' => []]);
        }

        return response()->json([
            'enabled' => true,
            'owner' => $user->isJournalOwner(),
            'current_user' => $this->member($user),
            'members' => User::orderBy('id')->get(['id', 'name', 'email'])->map(fn (User $member) => $this->member($member)),
            'invites' => $user->isJournalOwner() ? HouseholdInvitation::whereNull('accepted_at')->whereNull('revoked_at')
                ->where('expires_at', '>', now())->orderByDesc('id')->get(['id', 'email', 'expires_at']) : [],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function invite(Request $request)
    {
        $this->requireOwner($request);
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email', 'max:254']]);
        $token = Str::random(64);
        $invitation = DB::transaction(function () use ($data, $token) {
            // Serialize invite creation with joining so duplicate emails cannot get two accounts.
            User::whereKey(1)->lockForUpdate()->firstOrFail();
            if (User::whereRaw('LOWER(email) = ?', [$data['email']])->exists()) {
                throw ValidationException::withMessages(['email' => 'This person already has access. They can sign in with their account.']);
            }
            HouseholdInvitation::where('email', $data['email'])->whereNull('accepted_at')->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return HouseholdInvitation::create([
                'email' => $data['email'],
                'token_hash' => hash('sha256', $token),
                'invited_by' => 1,
                'expires_at' => now()->addHours(48),
            ]);
        });

        return response()->json([
            'invite_url' => route('household.join', ['token' => $token]),
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'invite' => $invitation->only(['id', 'email', 'expires_at']),
        ], 201)->header('Cache-Control', 'private, no-store');
    }

    public function revokeInvite(Request $request, HouseholdInvitation $invite)
    {
        $this->requireOwner($request);
        DB::transaction(function () use ($invite) {
            $locked = HouseholdInvitation::whereKey($invite->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->accepted_at, 409, 'This invitation has already been used. Remove this person from the members list to end their access.');
            $locked->update(['revoked_at' => now()]);
        });

        return response()->json(['message' => 'Invitation cancelled.']);
    }

    public function removeMember(Request $request, User $user)
    {
        $this->requireOwner($request);
        abort_if($user->isJournalOwner(), 422, 'The journal owner cannot be removed.');
        DB::transaction(function () use ($user) {
            User::whereKey(1)->lockForUpdate()->firstOrFail();
            $member = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            DB::table('sessions')->where('user_id', $member->id)->delete();
            $member->delete();
        });

        return response()->json(['message' => 'Access removed. The shared cooling sessions are still saved.']);
    }

    public function joinForm(string $token)
    {
        $invite = $this->availableInvite($token);

        return response()->view('invite', ['invite' => $invite])->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function join(Request $request, string $token)
    {
        abort_if(Auth::check(), 409, 'Sign out before joining with a separate account.');
        $invite = $this->availableInvite($token);
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', Rule::in([$invite->email])],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);
        $member = DB::transaction(function () use ($invite, $data) {
            User::whereKey(1)->lockForUpdate()->firstOrFail();
            $locked = HouseholdInvitation::whereKey($invite->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isAvailable(), 404);
            if (User::whereRaw('LOWER(email) = ?', [$locked->email])->exists()) {
                throw ValidationException::withMessages(['email' => 'An account already exists for this email. Please sign in instead.']);
            }
            $member = User::create([
                'name' => trim($data['name']),
                'email' => $locked->email,
                'password' => $data['password'],
            ]);
            $locked->update(['accepted_at' => now()]);

            return $member;
        });
        Auth::login($member);
        $request->session()->regenerate();

        return redirect('/');
    }

    private function availableInvite(string $token): HouseholdInvitation
    {
        abort_unless(config('journal.require_login'), 404);
        $invite = HouseholdInvitation::where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_unless($invite->isAvailable(), 404);

        return $invite;
    }

    private function requireOwner(Request $request): void
    {
        abort_unless(config('journal.require_login') && $request->user()?->isJournalOwner(), 403, 'Only the journal owner can manage access.');
    }

    private function member(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'is_owner' => $user->isJournalOwner()];
    }
}
