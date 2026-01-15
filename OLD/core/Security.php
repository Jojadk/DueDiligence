<?php
/**
 * Security Layer - Session Fingerprinting & Validation
 * Prevents session hijacking and enhances authentication security
 * 
 * @package Core
 * @version 2.0
 */

namespace Core;

class Security
{
    /**
     * Generate session fingerprint based on user agent and IP
     * 
     * @return string Fingerprint hash
     */
    public static function generateFingerprint()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $ipAddress = self::getClientIP();

        // Create fingerprint from semi-stable identifiers
        $fingerprint = hash('sha256', $userAgent . $ipAddress);

        return $fingerprint;
    }

    /**
     * Validate session fingerprint
     * 
     * @return bool True if valid
     */
    public static function validateFingerprint()
    {
        if (!isset($_SESSION['fingerprint'])) {
            return false;
        }

        $currentFingerprint = self::generateFingerprint();

        return hash_equals($_SESSION['fingerprint'], $currentFingerprint);
    }

    /**
     * Initialize session fingerprint
     */
    public static function initializeFingerprint()
    {
        $_SESSION['fingerprint'] = self::generateFingerprint();
        $_SESSION['created_at'] = time();
        $_SESSION['last_activity'] = time();
    }

    /**
     * Get client IP address (handles proxies)
     * 
     * @return string IP address
     */
    public static function getClientIP()
    {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Check session timeout (30 minutes default)
     * 
     * @param int $timeout Timeout in seconds
     * @return bool True if session is still valid
     */
    public static function checkSessionTimeout($timeout = 1800)
    {
        if (!isset($_SESSION['last_activity'])) {
            return false;
        }

        if ((time() - $_SESSION['last_activity']) > $timeout) {
            return false;
        }

        // Update last activity
        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Regenerate session ID (prevents fixation attacks)
     */
    public static function regenerateSession()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['fingerprint'] = self::generateFingerprint();
            $_SESSION['last_activity'] = time();
        }
    }

    /**
     * Generate CSRF token
     * 
     * @return string CSRF token
     */
    public static function generateCSRFToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token
     * 
     * @param string|null $token Token to validate
     * @return bool True if valid
     */
    public static function validateCSRFToken($token = null)
    {
        if ($token === null) {
            $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        if (!$token || !isset($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Sanitize input (XSS protection)
     * 
     * @param mixed $data Input data
     * @return mixed Sanitized data
     */
    public static function sanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }

        if (is_string($data)) {
            // Remove null bytes
            $data = str_replace("\0", '', $data);

            // Trim whitespace
            $data = trim($data);

            // Convert special characters to HTML entities
            $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $data;
    }

    /**
     * Sanitize output (additional XSS protection)
     * 
     * @param string $data Output data
     * @return string Sanitized data
     */
    public static function sanitizeOutput($data)
    {
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Validate file upload
     * 
     * @param array $file $_FILES array element
     * @param array $allowedTypes Allowed MIME types
     * @param int $maxSize Max file size in bytes
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880)
    {
        // Check for upload errors
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['valid' => false, 'error' => 'Invalid file upload'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Upload error code: ' . $file['error']];
        }

        // Check file size
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File too large (max ' . ($maxSize / 1024 / 1024) . 'MB)'];
        }

        // Check MIME type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
            return ['valid' => false, 'error' => 'Invalid file type'];
        }

        // Check for double extensions
        $filename = $file['name'];
        if (preg_match('/\.(php|phtml|php3|php4|php5|php7|phps|pht|phar|exe|sh|bat|cmd|com)$/i', $filename)) {
            return ['valid' => false, 'error' => 'Dangerous file extension'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Hash password securely
     * 
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    /**
     * Verify password
     * 
     * @param string $password Plain text password
     * @param string $hash Hashed password
     * @return bool True if password matches
     */
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if password needs rehash (algorithm changed)
     * 
     * @param string $hash Current hash
     * @return bool True if needs rehash
     */
    public static function needsRehash($hash)
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    /**
     * Generate secure random token
     * 
     * @param int $length Token length
     * @return string Random token
     */
    public static function generateToken($length = 32)
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Validate email format
     * 
     * @param string $email Email address
     * @return bool True if valid
     */
    public static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Prevent clickjacking (X-Frame-Options)
     */
    public static function preventClickjacking()
    {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    /**
     * Set secure session configuration
     */
    public static function configureSecureSession()
    {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
    }

    /**
     * Log security event
     * 
     * @param string $event Event type
     * @param array $context Additional context
     */
    public static function logSecurityEvent($event, $context = [])
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null,
            'context' => $context
        ];

        $logFile = __DIR__ . '/../logs/security.log';
        $logEntry = json_encode($logData) . "\n";

        error_log($logEntry, 3, $logFile);
    }
}
