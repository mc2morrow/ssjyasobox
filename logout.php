<?php
// logout.php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Session.php';
require_once 'classes/Logger.php';

$session = new Session();
$logger = new Logger();

if ($session->isLoggedIn()) {
    $userId = $session->getUserId();
    $logger->logUserActivity($userId, 'LOGOUT', 'User logged out');
}

$session->destroySession();

header('Location: login.php?msg=logout');
exit;