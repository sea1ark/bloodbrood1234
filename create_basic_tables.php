<?php
// Отображение всех ошибок
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Конфигурация базы данных
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'bloodbrood';

echo "<pre>";
echo "### bloodbrood - Создание основных таблиц ###\n\n";

try {
    echo "Подключение к базе данных '$dbName'... ";
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "OK\n";
} catch (PDOException $e) {
    die("ОШИБКА: Не удалось подключиться к базе данных: " . $e->getMessage() . "\n");
}

// Создание основных таблиц
echo "\nСоздание основных таблиц:\n";

// Создание таблицы users
echo "Создание таблицы users... ";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'user') DEFAULT 'user',
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            avatar VARCHAR(255),
            bio TEXT,
            INDEX idx_username (username),
            INDEX idx_active (is_active)
        )
    ");
    echo "OK\n";
} catch (PDOException $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
}

// Создание таблицы messages
echo "Создание таблицы messages... ";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            message_type ENUM('text', 'file', 'link') DEFAULT 'text',
            is_encrypted BOOLEAN DEFAULT FALSE,
            parent_id INT NULL,
            comments_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_deleted BOOLEAN DEFAULT FALSE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_created_at (created_at),
            INDEX idx_user_id (user_id),
            INDEX idx_deleted (is_deleted),
            INDEX idx_parent_id (parent_id)
        )
    ");
    echo "OK\n";
} catch (PDOException $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
}

// Создание таблицы attachments
echo "Создание таблицы attachments... ";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attachments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            message_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            file_size INT NOT NULL,
            file_type VARCHAR(100),
            file_hash VARCHAR(64),
            is_encrypted BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
            INDEX idx_message_id (message_id),
            INDEX idx_filename (filename),
            INDEX idx_file_hash (file_hash)
        )
    ");
    echo "OK\n";
} catch (PDOException $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
}

// Проверка наличия пользователей
try {
    echo "\nПроверка пользователей... ";
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    echo "Найдено $userCount пользователей\n";
    
    if ($userCount == 0) {
        echo "Создаем администратора...\n";
        $passwordHash = password_hash('blood666admin', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $stmt->execute(['admin', $passwordHash, 'admin']);
        
        echo "Создаем обычного пользователя...\n";
        $passwordHash = password_hash('blood666user', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $stmt->execute(['blooduser', $passwordHash, 'user']);
        
        echo "Пользователи созданы успешно.\n";
    }
} catch (PDOException $e) {
    echo "ОШИБКА при проверке пользователей: " . $e->getMessage() . "\n";
}

echo "\nСоздание базовых таблиц завершено. Теперь запустите fix_tables.php для создания дополнительных таблиц.\n";
echo "</pre>";

echo '<p><a href="fix_tables.php" style="color: #660000; text-decoration: none; font-family: \'Courier New\', monospace; font-weight: bold; font-size: 16px;">Перейти к созданию дополнительных таблиц</a></p>';
?> 