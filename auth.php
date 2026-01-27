<?php
// Simple auth helpers
if (session_status() === PHP_SESSION_NONE) session_start();

function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function login_user(array $user) {
    $_SESSION['user_id'] = $user['id_user'];
    $_SESSION['user_name'] = $user['nama'] ?? '';
    $_SESSION['user_email'] = $user['email'] ?? '';
}

function logout_user() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
