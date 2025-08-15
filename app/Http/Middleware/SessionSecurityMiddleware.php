<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SessionSecurityMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for suspicious session activity
        $this->validateSessionSecurity($request);
        
        // Add security headers
        $response = $next($request);
        
        // Set secure session and security headers
        $this->setSecurityHeaders($response);
        
        // Log security events
        $this->logSecurityEvent($request);
        
        return $response;
    }

    /**
     * Validate session security
     */
    private function validateSessionSecurity(Request $request)
    {
        // Check for session hijacking indicators
        if ($request->hasSession()) {
            $session = $request->session();
            
            // Check IP consistency (if stored)
            if ($session->has('user_ip')) {
                $storedIp = $session->get('user_ip');
                $currentIp = $request->ip();
                
                if ($storedIp !== $currentIp) {
                    Log::warning('Session IP mismatch detected', [
                        'stored_ip' => $storedIp,
                        'current_ip' => $currentIp,
                        'user_id' => auth()->id(),
                        'session_id' => $session->getId()
                    ]);
                    
                    // Optionally invalidate session on IP mismatch
                    // $session->invalidate();
                    // return redirect('/login')->with('error', 'Session security violation detected');
                }
            } else {
                // Store IP on first access
                $session->put('user_ip', $request->ip());
            }
            
            // Check User-Agent consistency
            if ($session->has('user_agent')) {
                $storedAgent = $session->get('user_agent');
                $currentAgent = $request->userAgent();
                
                if ($storedAgent !== $currentAgent) {
                    Log::warning('Session User-Agent mismatch detected', [
                        'stored_agent' => $storedAgent,
                        'current_agent' => $currentAgent,
                        'user_id' => auth()->id(),
                        'session_id' => $session->getId()
                    ]);
                }
            } else {
                // Store User-Agent on first access
                $session->put('user_agent', $request->userAgent());
            }
            
            // Session timeout check
            $lastActivity = $session->get('last_activity', time());
            $sessionTimeout = config('session.lifetime') * 60; // Convert minutes to seconds
            
            if (time() - $lastActivity > $sessionTimeout) {
                Log::info('Session expired due to inactivity', [
                    'user_id' => auth()->id(),
                    'last_activity' => $lastActivity,
                    'session_id' => $session->getId()
                ]);
                
                $session->invalidate();
                $session->regenerateToken();
            } else {
                // Update last activity
                $session->put('last_activity', time());
            }
        }
    }

    /**
     * Set security headers
     */
    private function setSecurityHeaders(Response $response)
    {
        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // Enable XSS protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Strict transport security (HTTPS only)
        if (request()->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
               "style-src 'self' 'unsafe-inline'; " .
               "img-src 'self' data: blob:; " .
               "font-src 'self'; " .
               "connect-src 'self'; " .
               "frame-ancestors 'none';";
        
        $response->headers->set('Content-Security-Policy', $csp);
        
        // Referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Feature policy
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
    }

    /**
     * Log security events
     */
    private function logSecurityEvent(Request $request)
    {
        // Log authentication events
        if (auth()->check()) {
            $user = auth()->user();
            
            // Log admin access
            if ($user->isAdmin() && $request->is('api/admin/*')) {
                Log::info('Admin access logged', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'route' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'timestamp' => now()
                ]);
            }
        }
        
        // Log suspicious requests
        $suspiciousPatterns = [
            'script', 'javascript:', 'vbscript:', 'onload', 'onerror',
            '../', '..\\', '/etc/passwd', 'cmd.exe', 'powershell'
        ];
        
        $requestData = $request->all();
        $requestString = json_encode($requestData);
        
        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($requestString, $pattern) !== false) {
                Log::warning('Suspicious request pattern detected', [
                    'pattern' => $pattern,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'url' => $request->fullUrl(),
                    'data' => $requestData,
                    'user_id' => auth()->id()
                ]);
                break;
            }
        }
    }
}
