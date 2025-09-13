<?php
// Установка максимального времени выполнения
set_time_limit(300);

// Отображение всех ошибок
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Конфигурация базы данных
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'bloodbrood';

echo "<pre>";
echo "### bloodbrood - Setup Script ###\n\n";

// Шаг 1: Подключение к серверу MySQL
try {
    echo "Connecting to MySQL server... ";
    $pdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "OK\n";
} catch (PDOException $e) {
    die("ERROR: Не удалось подключиться к серверу MySQL: " . $e->getMessage() . "\n");
}

// Шаг 2: Создание базы данных
try {
    echo "Creating database '$dbName'... ";
    $pdo->exec("DROP DATABASE IF EXISTS $dbName");
    $pdo->exec("CREATE DATABASE $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "OK\n";
} catch (PDOException $e) {
    die("ERROR: Не удалось создать базу данных: " . $e->getMessage() . "\n");
}

// Шаг 3: Выбор базы данных
try {
    echo "Selecting database... ";
    $pdo->exec("USE $dbName");
    echo "OK\n";
} catch (PDOException $e) {
    die("ERROR: Не удалось выбрать базу данных: " . $e->getMessage() . "\n");
}

// Шаг 4: Импорт SQL-дампа
try {
    echo "Importing SQL dump... ";
    $sql = file_get_contents('bloodbrood_sql.sql');
    
    // Разделение запросов по разделителю
    $queries = explode(';', $sql);
    
    // Выполнение каждого запроса
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }
    
    echo "OK\n";
} catch (PDOException $e) {
    die("ERROR: Ошибка импорта SQL: " . $e->getMessage() . "\n");
}

// Шаг 5: Создание директории для загрузки файлов
try {
    echo "Creating uploads directory... ";
    if (!file_exists('uploads')) {
        mkdir('uploads', 0755);
        echo "OK\n";
    } else {
        echo "Already exists\n";
    }
} catch (Exception $e) {
    echo "WARNING: Не удалось создать директорию uploads: " . $e->getMessage() . "\n";
}

// Шаг 6: Проверка установки
try {
    echo "\nVerifying installation...\n";
    
    // Проверка наличия таблиц
    $tables = ['users', 'messages', 'attachments', 'links', 'user_sessions', 'system_logs', 'tags', 'message_tags'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✓ Table '$table' exists\n";
        } else {
            echo "✗ Table '$table' is missing\n";
        }
    }
    
    // Проверка наличия пользователей
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    echo "✓ Found $userCount users\n";
    
    echo "\nInstallation completed successfully!\n";
    echo "You can now log in with the following credentials:\n";
    echo "Username: admin\n";
    echo "Password: blood666admin\n";
    
} catch (PDOException $e) {
    echo "WARNING: Verification failed: " . $e->getMessage() . "\n";
}

echo "</pre>";

echo '<p><a href="index.php" style="color: #660000; text-decoration: none; font-family: \'Courier New\', monospace; font-weight: bold; font-size: 16px;">Перейти к bloodbrood</a></p>';
?> 