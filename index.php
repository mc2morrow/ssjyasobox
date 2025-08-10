<?php
// index.php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Logger.php';
require_once 'classes/Encryption.php';
require_once 'classes/Session.php';

$session = new Session();

// Check if user is already logged in
if ($session->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Redirect to login page
header('Location: login.php');
exit;