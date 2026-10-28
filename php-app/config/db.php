<?php
// config/db.php  -- safe to commit: contains NO real passwords.

// 1. ENVIRONMENT
$whitelist = array('127.0.0.1', '::1', 'localhost');
$server_name = $_SERVER['SERVER_NAME'] ?? 'localhost';
$is_local = in_array($server_name, $whitelist);

if ($is_local) {
    // --- LOCALHOST SETTINGS (XAMPP) ---
    if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
    if (!defined('DB_USER')) define('DB_USER', 'root');
    if (!defined('DB_PASS')) define('DB_PASS', '');
    if (!defined('DB_NAME')) define('DB_NAME', 'noveu');

    // Local preference: try 3307 first, then 3306
    $primary_port = 3307;
    $fallback_port = 3306;
} else {
    // --- ANY OTHER SERVER ---
    // Real credentials live in config/db_live.php, which is listed in
    // .gitignore and is NEVER uploaded to GitHub. Copy db_live.example.php
    // to db_live.php on the server and fill it in.
    $live = __DIR__ . '/db_live.php';
    if (!file_exists($live)) {
        die("<h3>Service Unavailable</h3><p>Database is not configured.</p>");
    }
    require $live;   // defines DB_HOST, DB_USER, DB_PASS, DB_NAME, $primary_port, $fallback_port
    error_reporting(0); // hide errors on a live site
}

// 2. SESSION START
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. DATABASE CONNECTION CLASS
class Database {
    private $connection;

    public function connect() {
        global $primary_port, $fallback_port;

        $this->connection = null;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            /* ATTEMPT 1: primary port */
            $dsn = "mysql:host=" . DB_HOST . ";port=" . $primary_port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);

        } catch (PDOException $e) {

            /* ATTEMPT 2: fallback port */
            try {
                $dsn_fallback = "mysql:host=" . DB_HOST . ";port=" . $fallback_port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $this->connection = new PDO($dsn_fallback, DB_USER, DB_PASS, $options);
            } catch (PDOException $e2) {

                $name = $_SERVER['SERVER_NAME'] ?? 'localhost';
                if (in_array($name, ['localhost', '127.0.0.1'])) {
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
