<?php
/**
 * Dashboard Module - Uses API for backend-controlled data
 * NO direct database queries - all data via API with permission checks
 */

// Dashboard gets its data via API calls, not direct DB access
// This ensures all permissions are checked on backend

load_template(template_path('dashboard', 'template'), [
    'user' => current_user(),
    'permissions' => get_user_permissions(current_user()['id'])
]);
