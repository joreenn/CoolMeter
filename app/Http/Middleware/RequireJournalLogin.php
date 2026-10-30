<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireJournalLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('journal.require_login') && ! $request->user()) {
            return ($request->is('api/*') || $request->expectsJson())
                ? response()->json(['message' => 'Please sign in to your journal.'], 401)
                : redirect()->route('login');
        }

        return $next($request);
    }
}
