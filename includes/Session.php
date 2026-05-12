<?php
/**
 * Session Management Class
 * Handles user authentication and session management
 */

class Session {
    
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params(SESSION_LIFETIME);
            session_start();
        }
    }
    
    public static function destroy() {
        self::start();
        $_SESSION = [];
        session_destroy();
    }
    
    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    public static function get($key, $default = null) {
        self::start();
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }
    
    public static function has($key) {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    public static function remove($key) {
        self::start();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    public static function isLoggedIn() {
        return self::has('user_id') && self::has('username');
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ' . BASE_URL . '/public/login.php');
            exit;
        }
    }
    
    public static function getUserId() {
        return self::get('user_id');
    }
    
    public static function getUsername() {
        return self::get('username');
    }
    
    public static function getUserRole() {
        return self::get('role_id');
    }
    
    public static function getUserRoleName() {
        return self::get('role_name');
    }
    
    public static function isAdmin() {
        return self::getUserRole() == ROLE_ADMIN;
    }
    
    public static function isMechanic() {
        return self::getUserRole() == ROLE_MECHANIC;
    }
    
    public static function isFrontDesk() {
        return self::getUserRole() == ROLE_FRONTDESK;
    }
    
    public static function setUserData($user) {
        self::set('user_id', $user['user_id']);
        self::set('username', $user['username']);
        self::set('role_id', $user['role_id']);
        self::set('role_name', $user['role_name']);
    }
    
    public static function setFlashMessage($type, $message) {
        self::set('flash_message', ['type' => $type, 'message' => $message]);
    }
    
    public static function getFlashMessage() {
        $flash = self::get('flash_message');
        self::remove('flash_message');
        return $flash;
    }
    
    public static function regenerate() {
        self::start();
        session_regenerate_id(true);
    }
}
