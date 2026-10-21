
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirect($url) {
    header("Location: " . $url);
    exit;
}

function requireRole($role) {
    if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"])) {
        redirect("/chemistry_lab/admin-login.php");
    }

    if ($_SESSION["role"] !== $role) {
        http_response_code(403);
        exit("Access denied: You do not have permission to access this page.");
    }
}
?>
