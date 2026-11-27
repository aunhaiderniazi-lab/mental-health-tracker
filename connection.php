<?php
// Database settings: configure these as environment variables outside the web root.
mysqli_report(MYSQLI_REPORT_OFF);

$host = getenv("MHT_DB_HOST") ?: "localhost";
$user = getenv("MHT_DB_USER") ?: "";
$pass = getenv("MHT_DB_PASS");
$db   = getenv("MHT_DB_NAME") ?: "mht";

if ($user === "" || $pass === false) {
    die("Database credentials are not configured. Set MHT_DB_USER and MHT_DB_PASS.");
}

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Database connection failed. Please try again later.");
}
$conn->set_charset("utf8mb4");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) . '">';
}

function csrf_valid(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}
?>
