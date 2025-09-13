<?php
// настройки сессий
ini_set('session.gc_maxlifetime', 86400); // сутки
session_set_cookie_params(86400);
// безопасность сессий
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
?> 