<?php
// includes/functions.php
function redirectLoggedInUser($session) {
    if (!$session->isLoggedIn()) {
        return false;
    }
    
    try {
        $user = new User();
        $userId = $session->getUserId();
        $userProfile = $user->getUserProfile($userId);
        
        if (!$userProfile) {
            $session->destroySession();
            return false;
        }
        
        // Redirect based on role
        if ($userProfile['user_role'] === 'admin') {
            header('Location: admin/dashboard.php');
        } else {
            header('Location: user/dashboard.php');
        }
        exit;
        
    } catch (Exception $e) {
        $session->destroySession();
        return false;
    }
}

// การใช้งานใน register.php
// require_once 'includes/functions.php';
// redirectLoggedInUser($session);