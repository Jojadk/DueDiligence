<?php
namespace Core;

class Auth
{
    // Login user
    public static function login($user)
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_id'] = self::getRoleId($user['id']);
        $_SESSION['permissions'] = self::getPermissions($user['id']);
    }

    // Logout
    public static function logout()
    {
        $_SESSION = [];
        session_destroy();
    }

    // Check if logged in
    public static function check()
    {
        return isset($_SESSION['user_id']);
    }

    // Get current user ID
    public static function id()
    {
        return $_SESSION['user_id'] ?? null;
    }

    // Get current user data (cached or fresh)
    public static function user()
    {
        if (!self::check())
            return null;
        // In a real app we might fetch from DB to ensure it's fresh, 
        // but for speed we'll rely on session or fetch if needed.
        $db = Database::getInstance();
        $db->query("SELECT * FROM users WHERE id = :id");
        $db->bind(':id', $_SESSION['user_id']);
        return $db->single();
    }

    // Check permission
    public static function hasPermission($permissionSlug)
    {
        if (!self::check())
            return false;
        // Admin role (id 1) always has access? Or explicit permissions.
        // Let's assume Role 1 is Super Admin
        if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
            return true;
        }

        $perms = $_SESSION['permissions'] ?? [];
        return in_array($permissionSlug, $perms);
    }

    // Helper: Get Primary Role ID
    private static function getRoleId($userId)
    {
        $db = Database::getInstance();
        $db->query("SELECT role_id FROM user_roles WHERE user_id = :id LIMIT 1");
        $db->bind(':id', $userId);
        $row = $db->single();
        return $row ? $row['role_id'] : null;
    }

    // Helper: Get Permissions array
    private static function getPermissions($userId)
    {
        $db = Database::getInstance();
        // Join users -> user_roles -> roles -> role_permissions -> permissions
        $sql = "
            SELECT p.slug 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = :uid
        ";
        $db->query($sql);
        $db->bind(':uid', $userId);
        $rows = $db->resultSet();

        $perms = [];
        foreach ($rows as $r) {
            $perms[] = $r['slug'];
        }
        return $perms;
    }
}
