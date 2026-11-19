<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RejectDisabledAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->disabled_at !== null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Akun tidak aktif.'], 403)
                : redirect()->route('auth')->with('status', 'Akun tidak aktif.');
        }

        return $next($request);
    }
}
