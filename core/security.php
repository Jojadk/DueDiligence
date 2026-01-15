<?php
/**
 * DueDiligence v2.0 - Security Functions
 *
 * CSRF protection, input validation, authentication, etc.
 */

// ============================================================================
// CSRF PROTECTION
// ============================================================================

/**
 * Generate CSRF token
 */
function csrf_token(): string {
    start_session();

    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }

    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Validate CSRF token
 */
function csrf_validate(string $token = null): bool {
    start_session();

    if ($token === null) {
        $token = $_POST[CSRF_TOKEN_NAME] ?? $_GET[CSRF_TOKEN_NAME] ?? '';
    }

    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }

    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Require valid CSRF token
 */
function csrf_require(): void {
    if (!csrf_validate()) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
}

/**
 * Generate CSRF hidden input field
 */
function csrf_field(): string {
    $token = csrf_token();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . $token . '">';
}

// ============================================================================
// AUTHENTICATION
// ============================================================================

/**
 * Hash password
 */
function password_hash_secure(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password
 */
function password_verify_secure(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

/**
 * Login user
 */
function login(string $username, string $password): array {
    // Check rate limiting
    if (!check_rate_limit('login', $_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
        return [
            'success' => false,
            'error' => 'For mange login forsøg. Prøv igen senere.'
        ];
    }

    // Find user
    $user = db_fetch(
        "SELECT * FROM users WHERE username = :username AND active = true",
        ['username' => $username]
    );

    if (!$user) {
        log_activity('login_failed', 'user', 0);
        return [
            'success' => false,
            'error' => 'Ugyldigt brugernavn eller adgangskode'
        ];
    }

    // Verify password
    if (!password_verify_secure($password, $user['password_hash'])) {
        log_activity('login_failed', 'user', $user['id']);
        return [
            'success' => false,
            'error' => 'Ugyldigt brugernavn eller adgangskode'
        ];
    }

    // Update last login
    db_update('users', [
        'last_login_at' => date('Y-m-d H:i:s')
    ], 'id = :id', ['id' => $user['id']]);

    // Set session
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['login_time'] = time();

    log_activity('login', 'user', $user['id'], $user['id']);

    return [
        'success' => true,
        'user' => $user
    ];
}

/**
 * Logout user
 */
function logout(): void {
    start_session();

    if (isset($_SESSION['user_id'])) {
        log_activity('logout', 'user', $_SESSION['user_id'], $_SESSION['user_id']);
    }

    $_SESSION = [];
    session_destroy();

    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
}

/**
 * Check if user has permission
 */
function has_permission(string $permission): bool {
    $user = current_user();
    if (!$user) return false;

    // Check if user has permission through roles
    $sql = "SELECT COUNT(*) FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = :user_id AND p.name = :permission";

    $count = db_value($sql, [
        'user_id' => $user['id'],
        'permission' => $permission
    ]);

    return $count > 0;
}

/**
 * Require permission
 */
function require_permission(string $permission): void {
    if (!has_permission($permission)) {
        http_response_code(403);
        die('Adgang nægtet: Du har ikke tilladelse til denne handling');
    }
}

// ============================================================================
// RATE LIMITING
// ============================================================================

/**
 * Check rate limit
 */
function check_rate_limit(string $action, string $identifier, int $maxAttempts = null, int $timeWindow = null): bool {
    $maxAttempts = $maxAttempts ?? MAX_LOGIN_ATTEMPTS;
    $timeWindow = $timeWindow ?? LOGIN_TIMEOUT;

    // Clean up old entries
    db_delete('rate_limits', 'reset_at < NOW()');

    // Check current attempts
    $sql = "SELECT attempts FROM rate_limits
            WHERE identifier = :identifier
            AND endpoint = :action
            AND reset_at > NOW()";

    $attempts = db_value($sql, [
        'identifier' => $identifier,
        'action' => $action
    ]);

    if ($attempts && $attempts >= $maxAttempts) {
        return false;
    }

    // Increment or create entry
    if ($attempts) {
        $sql = "UPDATE rate_limits
                SET attempts = attempts + 1
                WHERE identifier = :identifier AND endpoint = :action";
        db()->prepare($sql)->execute([
            'identifier' => $identifier,
            'action' => $action
        ]);
    } else {
        db_insert('rate_limits', [
            'identifier' => $identifier,
            'endpoint' => $action,
            'attempts' => 1,
            'reset_at' => date('Y-m-d H:i:s', time() + $timeWindow)
        ]);
    }

    return true;
}

// ============================================================================
// INPUT SANITIZATION
// ============================================================================

/**
 * Sanitize string input
 */
function sanitize_string(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize integer input
 */
function sanitize_int($input): int {
    return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

/**
 * Sanitize float input
 */
function sanitize_float($input): float {
    return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

/**
 * Sanitize email input
 */
function sanitize_email(string $input): string {
    return filter_var($input, FILTER_SANITIZE_EMAIL);
}

/**
 * Sanitize URL input
 */
function sanitize_url(string $input): string {
    return filter_var($input, FILTER_SANITIZE_URL);
}

/**
 * Sanitize HTML (allow safe tags)
 */
function sanitize_html(string $input, array $allowedTags = []): string {
    if (empty($allowedTags)) {
        $allowedTags = ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a'];
    }

    return strip_tags($input, '<' . implode('><', $allowedTags) . '>');
}

/**
 * Sanitize filename
 */
function sanitize_filename(string $filename): string {
    // Remove any path information
    $filename = basename($filename);

    // Remove any non-alphanumeric characters except dot, dash, underscore
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

    // Remove multiple dots (except the one before extension)
    $parts = explode('.', $filename);
    $ext = array_pop($parts);
    $name = implode('_', $parts);

    return $name . '.' . $ext;
}

// ============================================================================
// FILE UPLOAD VALIDATION
// ============================================================================

/**
 * Validate uploaded file
 */
function validate_upload(array $file): array {
    $errors = [];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'Filen er for stor';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = 'Filen blev kun delvist uploadet';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = 'Ingen fil blev uploadet';
                break;
            default:
                $errors[] = 'Upload fejl';
        }
        return $errors;
    }

    // Check file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        $errors[] = 'Filen må maksimalt være ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . ' MB';
    }

    // Check file extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        $errors[] = 'Filtype ikke tilladt. Tilladte typer: ' . implode(', ', ALLOWED_EXTENSIONS);
    }

    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimeTypes = [
        'image/jpeg', 'image/png', 'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];

    if (!in_array($mimeType, $allowedMimeTypes)) {
        $errors[] = 'Ugyldig filtype';
    }

    return $errors;
}

/**
 * Save uploaded file securely
 */
function save_upload(array $file, string $destination, string $newName = null): array {
    // Validate file
    $errors = validate_upload($file);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // Create destination directory if not exists
    ensure_dir(dirname($destination));

    // Generate new filename if not provided
    if ($newName === null) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $newName = bin2hex(random_bytes(16)) . '.' . $ext;
    } else {
        $newName = sanitize_filename($newName);
    }

    $fullPath = $destination . '/' . $newName;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        return ['success' => false, 'errors' => ['Kunne ikke gemme filen']];
    }

    // Set proper permissions
    chmod($fullPath, 0644);

    return [
        'success' => true,
        'filename' => $newName,
        'path' => $fullPath,
        'size' => $file['size'],
        'mime_type' => mime_content_type($fullPath)
    ];
}

// ============================================================================
// XSS PROTECTION
// ============================================================================

/**
 * Escape output for HTML context
 */
function esc_html(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Escape output for attribute context
 */
function esc_attr(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Escape output for JavaScript context
 */
function esc_js(string $text): string {
    return json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/**
 * Escape output for URL context
 */
function esc_url(string $url): string {
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

// ============================================================================
// SQL INJECTION PROTECTION
// ============================================================================

/**
 * Validate table/column names (prevent SQL injection in dynamic queries)
 */
function validate_identifier(string $identifier): bool {
    // Only allow alphanumeric and underscore
    return preg_match('/^[a-zA-Z0-9_]+$/', $identifier) === 1;
}

/**
 * Escape identifier for SQL (table/column names)
 */
function escape_identifier(string $identifier): string {
    if (!validate_identifier($identifier)) {
        throw new InvalidArgumentException('Invalid identifier: ' . $identifier);
    }

    // PostgreSQL uses double quotes for identifiers
    return '"' . $identifier . '"';
}

// ============================================================================
// SECURITY HEADERS
// ============================================================================

/**
 * Set security headers
 */
function set_security_headers(): void {
    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN');

    // XSS Protection
    header('X-XSS-Protection: 1; mode=block');

    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');

    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
}

// ============================================================================
// SESSION SECURITY
// ============================================================================

/**
 * Secure session configuration
 */
function configure_secure_session(): void {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1'); // Only if using HTTPS
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
}

/**
 * Check session timeout
 */
function check_session_timeout(): void {
    start_session();

    if (isset($_SESSION['login_time'])) {
        $elapsed = time() - $_SESSION['login_time'];

        if ($elapsed > SESSION_LIFETIME) {
            logout();
            redirect('/?module=auth&action=login&timeout=1');
        }

        // Update login time on activity
        $_SESSION['login_time'] = time();
    }
}

// Initialize security
configure_secure_session();
