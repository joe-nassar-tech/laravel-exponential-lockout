<?php

namespace ExponentialLockout\Middleware;

use Closure;
use ExponentialLockout\LockoutManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * ExponentialLockout Middleware
 * 
 * This middleware checks if a user is locked out for a specific context
 * and returns appropriate responses if they are.
 */
class ExponentialLockout
{
    /**
     * The lockout manager instance
     */
    protected LockoutManager $lockoutManager;

    /**
     * Package configuration
     */
    protected array $config;

    /**
     * Create a new middleware instance
     */
    public function __construct(LockoutManager $lockoutManager, array $config)
    {
        $this->lockoutManager = $lockoutManager;
        $this->config = $config;
    }

    /**
     * Handle an incoming request
     * 
     * @param Request $request
     * @param Closure $next
     * @param string $context The lockout context (e.g., 'login', 'otp')
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $context)
    {
        // Extract the key for this request and context
        $key = $this->lockoutManager->extractKeyFromRequest($context, $request);
        
        // Check if the user is locked out
        if ($this->lockoutManager->isLockedOut($context, $key)) {
            return $this->buildLockoutResponse($request, $context, $key);
        }

        // Continue with the request
        return $next($request);
    }

    /**
     * Build the lockout response
     * 
     * @param Request $request
     * @param string $context
     * @param string $key
     * @return SymfonyResponse
     */
    protected function buildLockoutResponse(Request $request, string $context, string $key): SymfonyResponse
    {
        $remainingTime = $this->lockoutManager->getRemainingTime($context, $key);
        $contextConfig = $this->getContextConfig($context);
        $responseMode = $this->determineResponseMode($request, $contextConfig);

        // Build base response data
        $responseData = [
            'message' => 'Too many failed attempts. Please try again later.',
            'error' => 'lockout_active',
            'context' => $context,
            'retry_after' => $remainingTime,
            'locked_until' => now()->addSeconds($remainingTime)->toISOString(),
        ];

        // Handle different response modes
        switch ($responseMode) {
            case 'json':
                return $this->buildJsonResponse($responseData, $remainingTime);
                
            case 'redirect':
                return $this->buildRedirectResponse($request, $contextConfig, $responseData);
                
            case 'callback':
                return $this->buildCallbackResponse($context, $key, $remainingTime);
                
            case 'auto':
            default:
                return $this->buildAutoResponse($request, $contextConfig, $responseData, $remainingTime);
        }
    }

    /**
     * Build JSON response
     */
    protected function buildJsonResponse(array $responseData, int $remainingTime): SymfonyResponse
    {
        $response = response()->json($responseData, $this->config['http_status_code']);
        
        return $this->addHeaders($response, $remainingTime);
    }

    /**
     * Build redirect response
     */
    protected function buildRedirectResponse(Request $request, array $contextConfig, array $responseData): SymfonyResponse
    {
        $redirectRoute = $contextConfig['redirect_route'] ?? $this->config['default_redirect_route'];
        
        // Flash error message to session
        $request->session()->flash('lockout_error', $responseData['message']);
        $request->session()->flash('lockout_retry_after', $responseData['retry_after']);
        
        return redirect()->route($redirectRoute);
    }

    /**
     * Build callback response
     */
    protected function buildCallbackResponse(string $context, string $key, int $remainingTime): SymfonyResponse
    {
        $callback = $this->config['custom_response_callback'];
        
        if (is_callable($callback)) {
            $response = $callback($context, $key, $remainingTime);
            
            if ($response instanceof SymfonyResponse) {
                return $this->addHeaders($response, $remainingTime);
            }
        }
        
        // Fallback to JSON response if callback fails
        return $this->buildJsonResponse([
            'message' => 'Too many failed attempts. Please try again later.',
            'error' => 'lockout_active',
            'context' => $context,
            'retry_after' => $remainingTime,
        ], $remainingTime);
    }

    /**
     * Build auto-detected response
     */
    protected function buildAutoResponse(Request $request, array $contextConfig, array $responseData, int $remainingTime): SymfonyResponse
    {
        // Auto-detect based on request type
        if ($this->expectsJson($request)) {
            return $this->buildJsonResponse($responseData, $remainingTime);
        } else {
            return $this->buildRedirectResponse($request, $contextConfig, $responseData);
        }
    }

    /**
     * Add appropriate headers to the response
     */
    protected function addHeaders(SymfonyResponse $response, int $remainingTime): SymfonyResponse
    {
        if (!$this->config['include_headers']) {
            return $response;
        }

        $response->headers->set('Retry-After', $remainingTime);
        $response->headers->set('X-RateLimit-Limit', 'exponential');
        $response->headers->set('X-RateLimit-Remaining', '0');
        $response->headers->set('X-RateLimit-Reset', now()->addSeconds($remainingTime)->timestamp);
        
        return $response;
    }

    /**
     * Determine the response mode for the given request and context
     */
    protected function determineResponseMode(Request $request, array $contextConfig): string
    {
        return $contextConfig['response_mode'] ?? $this->config['default_response_mode'] ?? 'auto';
    }

    /**
     * Check if the request expects a JSON response
     */
    protected function expectsJson(Request $request): bool
    {
        return $request->expectsJson() || 
               $request->isXmlHttpRequest() ||
               $request->is('api/*') ||
               $request->header('Accept') === 'application/json' ||
               $request->header('Content-Type') === 'application/json';
    }

    /**
     * Get configuration for the given context
     */
    protected function getContextConfig(string $context): array
    {
        $contexts = $this->config['contexts'] ?? [];
        return $contexts[$context] ?? [];
    }
}