<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RecordVisitor
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->hasSession() || ! SchemaReady::hasVisitorsTable()) {
            return $response;
        }

        $sessionId = $request->session()->getId();
        $now = now();
        $ipHash = hash('sha256', $request->ip().'|'.config('app.key'));

        $inserted = DB::table('visitors')->insertOrIgnore([
            'session_id' => $sessionId,
            'ip_hash' => $ipHash,
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            DB::table('visitors')->where(
                'session_id',
                $sessionId
            )->update([
                'ip_hash' => $ipHash,
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'last_seen_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $response;
    }
}
