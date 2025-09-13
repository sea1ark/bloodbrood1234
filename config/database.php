<?php
// настройки БД
define('DB_HOST', 'localhost');
define('DB_NAME', 'bloodbrood');
define('DB_USER', 'root');
define('DB_PASS', ''); // для xampp пустой пароль
define('DB_CHARSET', 'utf8mb4');

// безопасность
define('ENCRYPTION_KEY', hash('sha256', 'my-secret-key-123')); // TODO: поменять в продакшене
define('SESSION_LIFETIME', 86400); // сутки
define('TOKEN_LIFETIME', 3600); // час для токенов

// загрузка файлов
define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100мб
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('ALLOWED_EXTENSIONS', [
    'txt', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'zip', 'rar', '7z', 'tar', 'gz',
    'png', 'jpg', 'jpeg', 'gif', 'bmp', 'svg', 'webp',
    'mp3', 'wav', 'ogg', 'mp4', 'avi', 'mov', 'webm',
    'css', 'js', 'html', 'php', 'json', 'xml', 'csv'
]);

// разрешенные домены для ссылок
define('URL_WHITELIST', [
    'github.com', 'gitlab.com', 'bitbucket.org',
    'youtube.com', 'youtu.be', 'vimeo.com',
    'drive.google.com', 'docs.google.com',
    'dropbox.com', 'mega.nz'
]);

// подключение к БД
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    // если БД не существует, создаем
    if ($e->getCode() == 1049) {
        try {
            $rootPdo = new PDO("mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // импортируем структуру
            $sql = file_get_contents(__DIR__ . '/../bloodbrood_sql.sql');
            $pdo->exec($sql);
            
        } catch (PDOException $e2) {
            error_log('DB setup error: ' . $e2->getMessage());
            die('Ошибка настройки БД');
        }
    } else {
        error_log('DB connection error: ' . $e->getMessage());
        die('Ошибка подключения к БД');
    }
}

// функции авторизации
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: auth.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        logSystemAction('SECURITY_VIOLATION', 'Non-admin tried to access admin area: ' . $_SESSION['username']);
        http_response_code(403);
        die('Доступ запрещен.');
    }
}

// утилиты
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function encrypt($data, $key) {
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decrypt($data, $key) {
    $data = base64_decode($data);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'только что';
    if ($time < 3600) return floor($time/60) . ' мин. назад';
    if ($time < 86400) return floor($time/3600) . ' ч. назад';
    if ($time < 2592000) return floor($time/86400) . ' дн. назад';
    
    return date('d.m.Y в H:i', strtotime($datetime));
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

// логирование действий
function logSystemAction($action, $details = '', $userId = null) {
    global $pdo;
    
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $details, $ip]);
    } catch (Exception $e) {
        error_log('Error logging action: ' . $e->getMessage());
    }
}

// проверка безопасности URL
function isUrlSafe($url) {
    $host = parse_url($url, PHP_URL_HOST);
    
    if (!$host) {
        return false;
    }
    
    // проверяем по белому списку
    foreach (URL_WHITELIST as $allowedDomain) {
        if (strpos($host, $allowedDomain) !== false) {
            return true;
        }
    }
    
    return false;
}

// создаем папку для загрузок
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// логируем доступ к страницам
if (isset($_SESSION['user_id']) && 
    (!isset($_SESSION['last_activity']) || time() - $_SESSION['last_activity'] > 300)) {
    try {
        logSystemAction('PAGE_ACCESS', $_SERVER['REQUEST_URI']);
        $_SESSION['last_activity'] = time();
    } catch (Exception $e) {
        error_log('Error logging page access: ' . $e->getMessage());
    }
} 