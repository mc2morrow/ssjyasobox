<?php
// config/database.php
class DatabaseConfig {
    const HOST = 'localhost';
    const USERNAME = 'root';
    const PASSWORD = 'Ktza947@test';
    const DATABASE = 'ssjbox_db';
    const CHARSET = 'utf8mb4';
    const OPTIONS = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
}
