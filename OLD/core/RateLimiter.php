<?php
/**
 * Rate Limiter
 * Prevents brute force attacks on authentication and API endpoints
 * 
 * @package Core
 * @version 2.0
 */

namespace Core;

class RateLimiter
{
    private $db;
    private $tableName = 'rate_limits';

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTableExists();
    }

    /**
     * Ensure rate_limits table exists
     */
    private function ensureTableExists()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS rate_limits (
                id SERIAL PRIMARY KEY,
                identifier VARCHAR(255) NOT NULL,
                action VARCHAR(50) NOT NULL,
                attempts INT DEFAULT 0,
                first_attempt_at TIMESTAMP DEFAULT NOW(),
                last_attempt_at TIMESTAMP DEFAULT NOW(),
                blocked_until TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT NOW(),
                UNIQUE(identifier, action)
            )
        ";

        try {
            $this->db->query($sql);
            $this->db->execute();
        } catch (\Exception $e) {
            // Table might already exist
        }
    }

    /**
     * Check if action is allowed
     *
     * @param string $identifier Unique identifier (IP, email, user_id)
     * @param string $action Action type (login, api, password_reset)
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $windowSeconds Time window in seconds
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => timestamp]
     */
    public function checkLimit($identifier, $action, $maxAttempts = 5, $windowSeconds = 300)
    {
        // Get current record
        $this->db->query("
            SELECT * FROM {$this->tableName}
            WHERE identifier = :identifier AND action = :action
        ");
        $this->db->bind(':identifier', $identifier);
        $this->db->bind(':action', $action);
        $record = $this->db->single();

        $now = time();

        // If blocked, check if block period has expired
        if ($record && $record['blocked_until']) {
            $blockedUntil = strtotime($record['blocked_until']);
            if ($blockedUntil > $now) {
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_at' => $blockedUntil,
                    'blocked' => true,
                    'message' => 'Too many attempts. Try again in ' . ceil(($blockedUntil - $now) / 60) . ' minutes.'
                ];
            } else {
                // Block expired, reset record
                $this->reset($identifier, $action);
                $record = null;
            }
        }

        // No record or expired window
        if (!$record) {
            return [
                'allowed' => true,
                'remaining' => $maxAttempts - 1,
                'reset_at' => $now + $windowSeconds,
                'blocked' => false
            ];
        }

        $firstAttempt = strtotime($record['first_attempt_at']);
        $attempts = (int) $record['attempts'];

        // Window expired, reset
        if (($now - $firstAttempt) > $windowSeconds) {
            $this->reset($identifier, $action);
            return [
                'allowed' => true,
                'remaining' => $maxAttempts - 1,
                'reset_at' => $now + $windowSeconds,
                'blocked' => false
            ];
        }

        // Check if limit exceeded
        if ($attempts >= $maxAttempts) {
            // Block for exponential backoff
            $blockDuration = $this->calculateBackoff($attempts, $windowSeconds);
            $this->block($identifier, $action, $blockDuration);

            return [
                'allowed' => false,
                'remaining' => 0,
                'reset_at' => $now + $blockDuration,
                'blocked' => true,
                'message' => 'Rate limit exceeded. Blocked for ' . ceil($blockDuration / 60) . ' minutes.'
            ];
        }

        return [
            'allowed' => true,
            'remaining' => $maxAttempts - $attempts - 1,
            'reset_at' => $firstAttempt + $windowSeconds,
            'blocked' => false
        ];
    }

    /**
     * Record an attempt
     * 
     * @param string $identifier
     * @param string $action
     * @param bool $success Whether attempt was successful
     */
    public function recordAttempt($identifier, $action, $success = false)
    {
        // If successful, reset counter
        if ($success) {
            $this->reset($identifier, $action);
            return;
        }

        // Check if record exists
        $this->db->query("
            SELECT id, attempts FROM {$this->tableName}
            WHERE identifier = :identifier AND action = :action
        ");
        $this->db->bind(':identifier', $identifier);
        $this->db->bind(':action', $action);
        $record = $this->db->single();

        if ($record) {
            // Increment attempts
            $this->db->query("
                UPDATE {$this->tableName}
                SET attempts = attempts + 1, last_attempt_at = NOW()
                WHERE identifier = :identifier AND action = :action
            ");
            $this->db->bind(':identifier', $identifier);
            $this->db->bind(':action', $action);
            $this->db->execute();
        } else {
            // Create new record
            $this->db->query("
                INSERT INTO {$this->tableName} (identifier, action, attempts, first_attempt_at, last_attempt_at)
                VALUES (:identifier, :action, 1, NOW(), NOW())
            ");
            $this->db->bind(':identifier', $identifier);
            $this->db->bind(':action', $action);
            $this->db->execute();
        }
    }

    /**
     * Block identifier for specified duration
     * 
     * @param string $identifier
     * @param string $action
     * @param int $duration Duration in seconds
     */
    private function block($identifier, $action, $duration)
    {
        $this->db->query("
            UPDATE {$this->tableName}
            SET blocked_until = NOW() + INTERVAL '{$duration} seconds'
            WHERE identifier = :identifier AND action = :action
        ");
        $this->db->bind(':identifier', $identifier);
        $this->db->bind(':action', $action);
        $this->db->execute();

        // Log security event
        Security::logSecurityEvent('rate_limit_block', [
            'identifier' => $identifier,
            'action' => $action,
            'duration' => $duration
        ]);
    }

    /**
     * Reset counter for identifier
     * 
     * @param string $identifier
     * @param string $action
     */
    public function reset($identifier, $action)
    {
        $this->db->query("
            DELETE FROM {$this->tableName}
            WHERE identifier = :identifier AND action = :action
        ");
        $this->db->bind(':identifier', $identifier);
        $this->db->bind(':action', $action);
        $this->db->execute();
    }

    /**
     * Calculate exponential backoff duration
     * 
     * @param int $attempts Number of failed attempts
     * @param int $baseWindow Base time window
     * @return int Backoff duration in seconds
     */
    private function calculateBackoff($attempts, $baseWindow)
    {
        // Exponential backoff: 2^(attempts - maxAttempts) * baseWindow
        // First block: 5 minutes
        // Second block: 15 minutes
        // Third block: 30 minutes
        // Fourth+ block: 60 minutes

        $multipliers = [1, 3, 6, 12, 12]; // Minutes
        $index = min($attempts - 5, count($multipliers) - 1);

        return $multipliers[$index] * 60; // Convert to seconds
    }

    /**
     * Get rate limit status
     * 
     * @param string $identifier
     * @param string $action
     * @return array|null Status or null if no record
     */
    public function getStatus($identifier, $action)
    {
        $this->db->query("
            SELECT * FROM {$this->tableName}
            WHERE identifier = :identifier AND action = :action
        ");
        $this->db->bind(':identifier', $identifier);
        $this->db->bind(':action', $action);

        return $this->db->single();
    }

    /**
     * Clean up old records (run as cron job)
     * 
     * @param int $olderThanDays Delete records older than X days
     * @return int Number of deleted records
     */
    public function cleanup($olderThanDays = 7)
    {
        $this->db->query("
            DELETE FROM {$this->tableName}
            WHERE created_at < NOW() - INTERVAL '{$olderThanDays} days'
            AND blocked_until IS NULL
        ");
        $this->db->execute();

        return $this->db->rowCount();
    }

    /**
     * Get statistics for monitoring
     * 
     * @return array Statistics
     */
    public function getStatistics()
    {
        // Total active limits
        $this->db->query("SELECT COUNT(*) as total FROM {$this->tableName}");
        $stats['total_records'] = $this->db->single()['total'];

        // Currently blocked
        $this->db->query("
            SELECT COUNT(*) as blocked FROM {$this->tableName}
            WHERE blocked_until > NOW()
        ");
        $stats['currently_blocked'] = $this->db->single()['blocked'];

        // Blocked in last 24 hours
        $this->db->query("
            SELECT COUNT(*) as blocked_24h FROM {$this->tableName}
            WHERE blocked_until IS NOT NULL
            AND last_attempt_at > NOW() - INTERVAL '24 hours'
        ");
        $stats['blocked_last_24h'] = $this->db->single()['blocked_24h'];

        // Top blocked identifiers
        $this->db->query("
            SELECT identifier, action, attempts, blocked_until
            FROM {$this->tableName}
            WHERE blocked_until > NOW()
            ORDER BY attempts DESC
            LIMIT 10
        ");
        $stats['top_blocked'] = $this->db->resultSet();

        return $stats;
    }
}
