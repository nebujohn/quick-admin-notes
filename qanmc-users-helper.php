<?php
// Helper to get all users for sharing UI
function qanmc_get_users_for_sharing() {
    // Only get users who can actually see the dashboard widget (Admins)
    $args = [
        'role__in' => ['Administrator'], // Simplified for now, or check caps
        'fields' => ['ID', 'display_name', 'user_email'],
        'orderby' => 'display_name',
        'order' => 'ASC',
        'number' => 100,
    ];
    // A more robust way to find users with specific capability is to iterate, 
    // but for performance on large sites, role filtering is better. 
    // Let's assume 'administrator' role is the target. 
    // Alternatively, we can use 'capability' => 'manage_options' (but complex query).
    
    // Let's use role for simplicity as per requirement "admins".
    $users = get_users($args);
    
    $result = [];
    $current_user_id = get_current_user_id();
    
    foreach ($users as $user) {
        if ($user->ID === $current_user_id) continue; // Don't list self
        
        $result[] = [
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email
        ];
    }
    return $result;
}
