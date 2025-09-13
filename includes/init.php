<?php
// настройки сессий
require_once __DIR__ . '/session.php';

session_start();

// инициализация приложения
require_once __DIR__ . '/../config/database.php';

// проверка CSRF-токена
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['csrf_bypass'])) {
    if (!isset($_POST['csrf_token']) || !verifyToken($_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF защита: Недействительный токен. Пожалуйста, обновите страницу и попробуйте снова.');
    }
}

// заголовки безопасности
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; font-src \'self\' data:;');

// функция для генерации URL
function url($path = '') {
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    $app_path = dirname($_SERVER['SCRIPT_NAME']);
    
    // Если путь начинается со слеша, присоединяем его к базовому URL
    if (strpos($path, '/') === 0) {
        return $base_url . $path;
    }
    
    // Иначе присоединяем к пути приложения
    return $base_url . $app_path . '/' . $path;
}

// Функция для перенаправления
function redirect($path) {
    header('Location: ' . url($path));
    exit;
}

// Функция для форматирования даты
function formatDate($datetime, $format = 'd.m.Y H:i') {
    return date($format, strtotime($datetime));
}

// Функция для обрезки текста до определенной длины
function truncateText($text, $length = 100, $append = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    
    return mb_substr($text, 0, $length) . $append;
}

// Функция для определения типа файла по расширению
function getFileTypeIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $iconMap = [
        // Документы
        'pdf' => '📄',
        'doc' => '📝',
        'docx' => '📝',
        'txt' => '📄',
        'rtf' => '📄',
        'odt' => '📄',
        
        // Таблицы
        'xls' => '📊',
        'xlsx' => '📊',
        'csv' => '📊',
        
        // Презентации
        'ppt' => '📊',
        'pptx' => '📊',
        
        // Изображения
        'jpg' => '🖼️',
        'jpeg' => '🖼️',
        'png' => '🖼️',
        'gif' => '🖼️',
        'svg' => '🖼️',
        'webp' => '🖼️',
        'bmp' => '🖼️',
        
        // Архивы
        'zip' => '📦',
        'rar' => '📦',
        '7z' => '📦',
        'tar' => '📦',
        'gz' => '📦',
        
        // Аудио
        'mp3' => '🔊',
        'wav' => '🔊',
        'ogg' => '🔊',
        
        // Видео
        'mp4' => '🎬',
        'avi' => '🎬',
        'mov' => '🎬',
        'wmv' => '🎬',
        'webm' => '🎬',
        
        // Код
        'html' => '💻',
        'css' => '💻',
        'js' => '💻',
        'php' => '💻',
        'json' => '💻',
        'xml' => '💻'
    ];
    
    return $iconMap[$extension] ?? '📎';
}

// Функция для получения безопасного имени файла
function sanitizeFilename($filename) {
    // Удаление специальных символов
    $filename = preg_replace('/[^\w\-\.\s]/', '', $filename);
    // Замена пробелов на подчеркивания
    $filename = preg_replace('/\s+/', '_', $filename);
    // Удаление повторяющихся подчеркиваний
    $filename = preg_replace('/_+/', '_', $filename);
    
    return $filename;
}

// Проверка активности пользователя для обновления сессии
if (isLoggedIn()) {
    // Обновляем время последней активности
    $_SESSION['last_activity'] = time();
    
    // Проверка срока действия сессии
    if (isset($_SESSION['created_at']) && time() - $_SESSION['created_at'] > SESSION_LIFETIME) {
        // Сессия истекла, перенаправляем на страницу входа
        session_unset();
        session_destroy();
        redirect('auth.php?expired=1');
    }
} else {
    // Если пользователь не авторизован, но находится на защищенной странице
    $currentScript = basename($_SERVER['SCRIPT_NAME']);
    $publicPages = ['index.php', 'auth.php', 'setup.php'];
    
    if (!in_array($currentScript, $publicPages) && $currentScript !== 'api') {
        redirect('auth.php');
    }
}

// Функция определения скорости соединения
function getConnectionSpeed() {
    return isset($_SESSION['connection_speed']) ? $_SESSION['connection_speed'] : 'normal';
}

// Функция для получения правильного окончания (русский язык)
function getPlural($number, $one, $few, $many) {
    $number = abs((int)$number);
    $lastDigit = $number % 10;
    $lastTwoDigits = $number % 100;
    
    if ($lastTwoDigits >= 11 && $lastTwoDigits <= 14) {
        return $many;
    }
    
    if ($lastDigit == 1) {
        return $one;
    }
    
    if ($lastDigit >= 2 && $lastDigit <= 4) {
        return $few;
    }
    
    return $many;
} 