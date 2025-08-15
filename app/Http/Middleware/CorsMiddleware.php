<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Log CORS requests for security monitoring
        $this->logCorsRequest($request);

        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($request);
        }

        $response = $next($request);

        // Add CORS headers to the response
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Handle preflight OPTIONS request
     */
    private function handlePreflightRequest(Request $request): Response
    {
        $origin = $request->headers->get('Origin');
        
        if (!$this->isOriginAllowed($origin)) {
            Log::warning('CORS: Blocked preflight request from unauthorized origin', [
                'origin' => $origin,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            return response('', 403);
        }

        return response('', 200)
            ->header('Access-Control-Allow-Origin', $origin)
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Accept, Authorization, Content-Type, X-Requested-With, X-CSRF-TOKEN, X-XSRF-TOKEN, Origin, Cache-Control, Pragma')
            ->header('Access-Control-Allow-Credentials', 'true')
            ->header('Access-Control-Max-Age', '86400'); // 24 hours
    }

    /**
     * Add CORS headers to response
     */
    private function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->headers->get('Origin');

        if ($this->isOriginAllowed($origin)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Expose-Headers', 'Cache-Control, Content-Language, Content-Type, Expires, Last-Modified, Pragma');
        } else if ($origin) {
            // Log unauthorized CORS attempts
            Log::warning('CORS: Blocked request from unauthorized origin', [
                'origin' => $origin,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl()
            ]);
        }

        return $response;
    }

    /**
     * Check if origin is allowed
     */
    private function isOriginAllowed(?string $origin): bool
    {
        if (!$origin) {
            return false;
        }

        $allowedOrigins = [
            'http://localhost:3000',
            'http://localhost:3001',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:3001',
        ];

        // Check exact matches
        if (in_array($origin, $allowedOrigins)) {
            return true;
        }

        // Check patterns for development
        $allowedPatterns = [
            '/^http:\/\/localhost:\d+$/',
            '/^http:\/\/127\.0\.0\.1:\d+$/',
        ];

        foreach ($allowedPatterns as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        // Add production domains check here
        // Example:
        // if (app()->environment('production')) {
        //     $productionOrigins = ['https://yourdomain.com', 'https://www.yourdomain.com'];
        //     return in_array($origin, $productionOrigins);
        // }

        return false;
    }

    /**
     * Log CORS requests for monitoring
     */
    private function logCorsRequest(Request $request): void
    {
        $origin = $request->headers->get('Origin');
        
        if ($origin && $request->getMethod() !== 'OPTIONS') {
            Log::info('CORS request processed', [
                'origin' => $origin,
                'method' => $request->getMethod(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'allowed' => $this->isOriginAllowed($origin)
            ]);
        }
    }
}
