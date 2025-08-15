<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CsrfProtectionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip CSRF check for GET, HEAD, OPTIONS requests
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        // Skip CSRF check for API routes with token authentication
        if ($request->bearerToken() && auth('sanctum')->check()) {
            return $next($request);
        }

        // Verify CSRF token for form submissions
        if (!$this->verifyCsrfToken($request)) {
            Log::warning('CSRF token mismatch', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_id' => auth()->id(),
                'referer' => $request->header('referer')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'CSRF token mismatch. Please refresh the page and try again.'
            ], 419);
        }

        return $next($request);
    }

    /**
     * Verify CSRF token
     */
    private function verifyCsrfToken(Request $request): bool
    {
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');
        
        if (!$token) {
            return false;
        }

        $sessionToken = $request->session()->token();
        
        return hash_equals($sessionToken, $token);
    }
}
