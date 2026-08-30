<?php
// config/db.php

// 1. AUTO-DETECT ENVIRONMENT
$whitelist = array('127.0.0.1', '::1', 'localhost');

if (in_array($_SERVER['SERVER_NAME'], $whitelist)) {
    // --- LOCALHOST SETTINGS (XAMPP) ---
    // Keep these exactly as they are for your computer
    if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
    if (!defined('DB_USER')) define('DB_USER', 'root');
    if (!defined('DB_PASS')) define('DB_PASS', '');
    if (!defined('DB_NAME')) define('DB_NAME', 'noveu');
    
    // Local Preference: Try 3307 first
    $primary_port = 3307;
    $fallback_port = 3306;

} else {
    // --- LIVE SERVER SETTINGS (InfinityFree) ---
    // ⚠️ YOU MUST EDIT THESE LINES WITH YOUR HOSTING DETAILS ⚠️
    if (!defined('DB_HOST')) define('DB_HOST', 'sql100.infinityfree.com'); // Example Host
    if (!defined('DB_USER')) define('DB_USER', 'if0_40464753');      // Your MySQL Username
    if (!defined('DB_PASS')) define('DB_PASS', 'NoveU1234');       // Your Hosting Password
    if (!defined('DB_NAME')) define('DB_NAME', 'if0_40464753_noveu');       // Your DB Name (usually has a prefix)
    
    // Live servers almost always use 3306
    $primary_port = 3306;
    $fallback_port = 3306; 
    
    // Turn off error display on live site for security
    error_reporting(0); 
}

// 2. SESSION START
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. DATABASE CONNECTION CLASS
class Database {
    private $connection;

    public function connect() {
        global $primary_port, $fallback_port; // Use variables defined above
        
        $this->connection = null;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            /* ATTEMPT 1: Primary Port (3307 on Local, 3306 on Live) */
            $dsn = "mysql:host=" . DB_HOST . ";port=" . $primary_port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch(PDOException $e) {

            /* ATTEMPT 2: Fallback Port (3306) */
            // This is mostly for your friend's XAMPP or if port config changes
            try {
                $dsn_fallback = "mysql:host=" . DB_HOST . ";port=" . $fallback_port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $this->connection = new PDO($dsn_fallback, DB_USER, DB_PASS, $options);
            } catch (PDOException $e2) {
                
                // If BOTH fail, kill the script
                // On live server, we hide specific details for security
                if (in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1'])) {
                    die("<h3>Connection Refused</h3>
                         <p>Tried Port " . $primary_port . " and " . $fallback_port . ".</p>
                         <p><strong>Details:</strong> " . $e2->getMessage() . "</p>");
                } else {
                    die("<h3>Service Unavailable</h3>
                         <p>Could not connect to the database. Please check configuration.</p>");
                }
            }
        }

        return $this->connection;
    }
}

$database = new Database();
$pdo = $database->connect();
?>