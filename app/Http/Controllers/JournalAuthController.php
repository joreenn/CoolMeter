<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class JournalAuthController extends Controller
{
    public function loginForm()
    {
        if (! config('journal.require_login') || Auth::check()) {
            return redirect('/');
        }

        return response()->view('auth', ['setup' => false])->header('Cache-Control', 'private, no-store');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }
        $request->session()->regenerate();

        return redirect('/');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function checkInvite(string $token): void
    {
        $expected = config('journal.setup_token_hash');
        abort_unless(config('journal.require_login') && is_string($expected) && strlen($expected) === 64
            && hash_equals($expected, hash('sha256', $token)) && ! User::exists(), 404);
    }

    public function setupForm(string $token)
    {
        $this->checkInvite($token);

        return response()->view('auth', ['setup' => true])->header('Cache-Control', 'private, no-store');
    }

    public function setup(Request $request, string $token)
    {
        $this->checkInvite($token);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);
        try {
            // Fixed primary key makes simultaneous first-owner submissions exclusive.
            $owner = User::forceCreate(['id' => 1, 'name' => 'My journal', 'email' => $data['email'], 'password' => $data['password']]);
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                abort(404);
            }
            throw $exception;
        }
        Auth::login($owner);
        $request->session()->regenerate();

        return redirect('/');
    }
}
