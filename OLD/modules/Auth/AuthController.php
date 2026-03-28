<?php
namespace Modules\Auth;

use Core\Controller;
use Core\Database;
use Core\Auth;

class AuthController extends Controller
{
    // Auth module doesn't require authentication
    protected $requiresAuth = false;

    // Default action if none specified
    public function index()
    {
        $this->login();
    }

    public function login()
    {
        // If already logged in, redirect to Dashboard
        if (Auth::check()) {
            $this->redirect('?module=Dashboard');
        }

        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            // Validate CSRF token
            if (!\Core\Security::validateCSRFToken()) {
                \Core\Security::logSecurityEvent('csrf_validation_failed', ['username' => $username]);
                $error = 'Invalid request. Please try again.';
                $this->view('Auth/login', ['error' => $error, 'no_layout' => true, 'csrf_token' => \Core\Security::generateCSRFToken()]);
                return;
            }

            // Rate limiting check
            $rateLimiter = new \Core\RateLimiter();
            $identifier = \Core\Security::getClientIP() . ':' . $username;
            $limitCheck = $rateLimiter->checkLimit($identifier, 'login', 5, 300);

            if (!$limitCheck['allowed']) {
                \Core\Security::logSecurityEvent('rate_limit_exceeded', [
                    'username' => $username,
                    'identifier' => $identifier
                ]);
                $error = $limitCheck['message'];
                $this->view('Auth/login', ['error' => $error, 'no_layout' => true, 'csrf_token' => \Core\Security::generateCSRFToken()]);
                return;
            }

            $db = Database::getInstance();
            // Use 1 instead of TRUE for SMALLINT compatibility
            $db->query("SELECT * FROM users WHERE username = :username AND is_active = 1");
            $db->bind(':username', $username);
            $user = $db->single();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Check if OTP is enabled
                if ($user['otp_enabled']) {
                    $otpCode = $_POST['otp_code'] ?? '';
                    if (!empty($otpCode)) {
                        require_once CORE_DIR . '/TOTP.php';
                        if (\Core\TOTP::verify($user['otp_secret'], $otpCode)) {
                            // Success - reset rate limit
                            $rateLimiter->recordAttempt($identifier, 'login', true);

                            // Initialize session security
                            \Core\Security::regenerateSession();
                            \Core\Security::initializeFingerprint();
                            \Core\Security::logSecurityEvent('successful_login', ['user_id' => $user['id']]);

                            Auth::login($user);
                            $this->redirect('?module=Dashboard');
                        } else {
                            $rateLimiter->recordAttempt($identifier, 'login', false);
                            \Core\Security::logSecurityEvent('failed_2fa', ['username' => $username]);
                            $error = 'Invalid 2FA Code.';
                        }
                    } else {
                        $this->view('Auth/login', [
                            'otp_required' => true,
                            'username_val' => $username,
                            'password_val' => $password,
                            'error' => 'Please enter 2FA Code',
                            'no_layout' => true,
                            'csrf_token' => \Core\Security::generateCSRFToken()
                        ]);
                        return;
                    }
                } else {
                    // No OTP - direct login with security
                    $rateLimiter->recordAttempt($identifier, 'login', true);
                    \Core\Security::regenerateSession();
                    \Core\Security::initializeFingerprint();
                    \Core\Security::logSecurityEvent('successful_login', ['user_id' => $user['id']]);

                    Auth::login($user);
                    $this->redirect('?module=Dashboard');
                }
            } else {
                // Failed login
                $rateLimiter->recordAttempt($identifier, 'login', false);
                \Core\Security::logSecurityEvent('failed_login', ['username' => $username]);
                $error = 'Invalid credentials or account inactive.';
            }
        }

        // Pass error and CSRF token to view
        $this->view('Auth/login', [
            'error' => $error,
            'no_layout' => true,
            'csrf_token' => \Core\Security::generateCSRFToken()
        ]);
    }

    public function logout()
    {
        Auth::logout();
        $this->redirect('?module=Auth');
    }
}
