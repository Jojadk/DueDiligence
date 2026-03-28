<?php
/**
 * Advanced Rate Limiter
 * Global API rate limiting with different tiers and strategies
 *
 * @version 1.0.0
 */

class RateLimiter {
    /**
     * Rate limit configurations by endpoint type
     */
    private static $limits = [
        // Authentication endpoints - strict limits
        'auth' => [
            'max_attempts' => 5,
            'window' => 900, // 15 minutes
            'strategy' => 'sliding_window'
        ],

        // Read operations - generous limits
        'read' => [
            'max_attempts' => 100,
            'window' => 60, // 1 minute
            'strategy' => 'fixed_window'
        ],

        // Write operations - moderate limits
        'write' => [
            'max_attempts' => 30,
            'window' => 60, // 1 minute
            'strategy' => 'sliding_window'
        ],

        // File upload - strict limits
        'upload' => [
            'max_attempts' => 10,
            'window' => 300, // 5 minutes
            'strategy' => 'sliding_window'
        ],

        // Report generation - strict limits (resource intensive)
        'report' => [
            'max_attempts' => 5,
            'window' => 300, // 5 minutes
            'strategy' => 'sliding_window'
        ],

        // Default fallback
        'default' => [
            'max_attempts' => 60,
            'window' => 60, // 1 minute
            'strategy' => 'fixed_window'
        ]
    ];

    /**
     * Check if request is allowed
     *
     * @param string $endpoint - Endpoint identifier (e.g., 'api.project.create')
     * @param string $identifier - User identifier (IP, user ID, etc.)
     * @param string $tier - Rate limit tier ('auth', 'read', 'write', etc.)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => int]
     */
    public static function check(string $endpoint, string $identifier, string $tier = 'default'): array {
        // Get limit configuration
        $config = self::$limits[$tier] ?? self::$limits['default'];

        // Get strategy
        $strategy = $config['strategy'] ?? 'fixed_window';

        // Execute check based on strategy
        if ($strategy === 'sliding_window') {
            return self::checkSlidingWindow($endpoint, $identifier, $config);
        } else {
            return self::checkFixedWindow($endpoint, $identifier, $config);
        }
    }

    /**
     * Fixed window rate limiting
     * Simple, uses less storage, but allows burst at window boundaries
     */
    private static function checkFixedWindow(string $endpoint, string $identifier, array $config): array {
        $maxAttempts = $config['max_attempts'];
        $window = $config['window'];

        // Create cache key
        $currentWindow = floor(time() / $window);
        $cacheKey = "ratelimit:fixed:{$endpoint}:{$identifier}:{$currentWindow}";

        // Get current count from cache
        $attempts = apcu_exists($cacheKey) ? apcu_fetch($cacheKey) : 0;

        // Check if limit exceeded
        if ($attempts >= $maxAttempts) {
            $resetAt = ($currentWindow + 1) * $window;
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset_at' => $resetAt,
                'limit' => $maxAttempts,
                'window' => $window
            ];
        }

        // Increment counter
        if ($attempts === 0) {
            apcu_add($cacheKey, 1, $window);
        } else {
            apcu_inc($cacheKey);
        }

        $resetAt = ($currentWindow + 1) * $window;

        return [
            'allowed' => true,
            'remaining' => $maxAttempts - ($attempts + 1),
            'reset_at' => $resetAt,
            'limit' => $maxAttempts,
            'window' => $window
        ];
    }

    /**
     * Sliding window rate limiting
     * More accurate, prevents burst at window boundaries, but uses more storage
     */
    private static function checkSlidingWindow(string $endpoint, string $identifier, array $config): array {
        $maxAttempts = $config['max_attempts'];
        $window = $config['window'];

        $now = microtime(true);
        $windowStart = $now - $window;

        // Create cache key
        $cacheKey = "ratelimit:sliding:{$endpoint}:{$identifier}";

        // Get existing attempts
        $attempts = apcu_exists($cacheKey) ? apcu_fetch($cacheKey) : [];

        // Filter out expired attempts
        $attempts = array_filter($attempts, function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });

        // Check if limit exceeded
        if (count($attempts) >= $maxAttempts) {
            // Calculate when oldest request will expire
            $oldestAttempt = min($attempts);
            $resetAt = ceil($oldestAttempt + $window);

            return [
                'allowed' => false,
                'remaining' => 0,
                'reset_at' => $resetAt,
                'limit' => $maxAttempts,
                'window' => $window
            ];
        }

        // Add current attempt
        $attempts[] = $now;

        // Store with TTL
        apcu_store($cacheKey, $attempts, $window + 60);

        // Calculate reset time (when oldest request expires)
        $resetAt = count($attempts) > 0 ? ceil(min($attempts) + $window) : ceil($now + $window);

        return [
            'allowed' => true,
            'remaining' => $maxAttempts - count($attempts),
            'reset_at' => $resetAt,
            'limit' => $maxAttempts,
            'window' => $window
        ];
    }

    /**
     * Check and enforce rate limit (throws exception if exceeded)
     *
     * @param string $endpoint
     * @param string $identifier
     * @param string $tier
     * @throws Exception if rate limit exceeded
     */
    public static function enforce(string $endpoint, string $identifier, string $tier = 'default'): void {
        $result = self::check($endpoint, $identifier, $tier);

        if (!$result['allowed']) {
            $waitTime = $result['reset_at'] - time();

            http_response_code(429);
            header('X-RateLimit-Limit: ' . $result['limit']);
            header('X-RateLimit-Remaining: 0');
            header('X-RateLimit-Reset: ' . $result['reset_at']);
            header('Retry-After: ' . $waitTime);

            throw new Exception(
                "Rate limit exceeded. Please try again in {$waitTime} seconds.",
                429
            );
        }

        // Add rate limit headers to response
        header('X-RateLimit-Limit: ' . $result['limit']);
        header('X-RateLimit-Remaining: ' . $result['remaining']);
        header('X-RateLimit-Reset: ' . $result['reset_at']);
    }

    /**
     * Get rate limit status for endpoint
     *
     * @param string $endpoint
     * @param string $identifier
     * @param string $tier
     * @return array Status information
     */
    public static function getStatus(string $endpoint, string $identifier, string $tier = 'default'): array {
        return self::check($endpoint, $identifier, $tier);
    }

    /**
     * Reset rate limit for identifier
     *
     * @param string $endpoint
     * @param string $identifier
     */
    public static function reset(string $endpoint, string $identifier): void {
        // Clear all cache keys for this endpoint and identifier
        $patterns = [
            "ratelimit:fixed:{$endpoint}:{$identifier}:*",
            "ratelimit:sliding:{$endpoint}:{$identifier}"
        ];

        foreach ($patterns as $pattern) {
            // APCu doesn't support pattern deletion, so we'd need to track keys
            // For now, just clear the sliding window cache
            if (strpos($pattern, 'sliding') !== false) {
                apcu_delete(str_replace('*', '', $pattern));
            }
        }
    }

    /**
     * Get identifier from request
     * Uses IP address, or user ID if authenticated
     *
     * @return string Identifier
     */
    public static function getIdentifier(): string {
        // If user is logged in, use user ID
        if (isset($_SESSION['user_id'])) {
            return 'user:' . $_SESSION['user_id'];
        }

        // Otherwise use IP address
        return 'ip:' . self::getClientIp();
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private static function getClientIp(): string {
        // Check for proxy headers
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Register custom rate limit tier
     *
     * @param string $name Tier name
     * @param array $config Configuration
     */
    public static function registerTier(string $name, array $config): void {
        self::$limits[$name] = array_merge([
            'max_attempts' => 60,
            'window' => 60,
            'strategy' => 'fixed_window'
        ], $config);
    }

    /**
     * Determine appropriate tier for API action
     *
     * @param string $module Module name
     * @param string $action Action name
     * @return string Tier name
     */
    public static function getTierForAction(string $module, string $action): string {
        // Authentication actions
        if ($module === 'auth' || in_array($action, ['login', 'register', 'reset_password'])) {
            return 'auth';
        }

        // File upload actions
        if (in_array($action, ['upload', 'upload_image', 'import'])) {
            return 'upload';
        }

        // Report generation
        if ($module === 'report' || in_array($action, ['generate', 'export', 'pdf'])) {
            return 'report';
        }

        // Write operations
        if (in_array($action, ['create', 'update', 'delete', 'save'])) {
            return 'write';
        }

        // Read operations
        if (in_array($action, ['get', 'list', 'fetch', 'search', 'view'])) {
            return 'read';
        }

        // Default tier
        return 'default';
    }

    /**
     * Middleware function for API router
     *
     * @param string $module
     * @param string $action
     */
    public static function middleware(string $module, string $action): void {
        $endpoint = "api.{$module}.{$action}";
        $identifier = self::getIdentifier();
        $tier = self::getTierForAction($module, $action);

        try {
            self::enforce($endpoint, $identifier, $tier);
        } catch (Exception $e) {
            // Log rate limit exceeded
            error_log("Rate limit exceeded: {$endpoint} for {$identifier}");

            // Return JSON error
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 'RATE_LIMIT_EXCEEDED'
            ]);
            exit;
        }
    }
}

/**
 * Helper function to check rate limit
 *
 * @param string $endpoint
 * @param string $identifier
 * @param string $tier
 * @return array
 */
function rate_limit_check(string $endpoint, string $identifier = null, string $tier = 'default'): array {
    $identifier = $identifier ?? RateLimiter::getIdentifier();
    return RateLimiter::check($endpoint, $identifier, $tier);
}

/**
 * Helper function to enforce rate limit
 *
 * @param string $endpoint
 * @param string $identifier
 * @param string $tier
 * @throws Exception
 */
function rate_limit_enforce(string $endpoint, string $identifier = null, string $tier = 'default'): void {
    $identifier = $identifier ?? RateLimiter::getIdentifier();
    RateLimiter::enforce($endpoint, $identifier, $tier);
}
