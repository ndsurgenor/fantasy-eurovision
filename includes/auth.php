<?php

function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login');
        exit;
    }
}

function requireAdmin(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header('Location: /admin/login');
        exit;
    }
    if (empty($_SESSION['is_admin'])) {
        header('Location: /');
        exit;
    }
}

function loginUser(array $user): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_regenerate_id(true);
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['name']     = $user['name'];
    $_SESSION['is_admin'] = (bool) $user['is_admin'];
}

function logoutUser(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function isLoggedIn(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isLoggedIn() && !empty($_SESSION['is_admin']);
}
