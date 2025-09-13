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
echo "### bloodbrood - Database Check ###\n\n";

// Проверка подключения к MySQL
try {
    echo "Проверка подключения к MySQL... ";
    $pdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "OK\n";
} catch (PDOException $e) {
    die("ОШИБКА: Не удалось подключиться к серверу MySQL: " . $e->getMessage() . "\n");
}

// Проверка существования базы данных
try {
    echo "Проверка существования базы данных '$dbName'... ";
    $stmt = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbName'");
    if ($stmt->fetchColumn() > 0) {
        echo "OK (База данных существует)\n";
    } else {
        echo "ОШИБКА (База данных не существует)\n";
        echo "Создаем базу данных... ";
        $pdo->exec("CREATE DATABASE $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "OK\n";
    }
} catch (PDOException $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
}

// Подключение к базе данных
try {
    echo "Подключение к базе данных '$dbName'... ";
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "OK\n";
} catch (PDOException $e) {
    die("ОШИБКА: Не удалось подключиться к базе данных: " . $e->getMessage() . "\n");
}

// Проверка таблиц
$requiredTables = ['users', 'messages', 'attachments', 'links', 'user_sessions', 'system_logs', 'tags', 'message_tags'];
echo "\nПроверка таблиц:\n";

$tablesMissing = false;
foreach ($requiredTables as $table) {
    echo "Таблица '$table'... ";
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() > 0) {
        echo "OK\n";
    } else {
        echo "ОТСУТСТВУЕТ\n";
        $tablesMissing = true;
    }
}

// Если таблицы отсутствуют, предлагаем создать структуру
if ($tablesMissing) {
    echo "\nНекоторые таблицы отсутствуют. Импортируем структуру базы данных...\n";
    if (file_exists('bloodbrood_sql.sql')) {
        try {
            $sql = file_get_contents('bloodbrood_sql.sql');
            $pdo->exec($sql);
            echo "Структура базы данных успешно импортирована.\n";
        } catch (PDOException $e) {
            echo "ОШИБКА при импорте структуры: " . $e->getMessage() . "\n";
        }
    } else {
        echo "ОШИБКА: Файл 'bloodbrood_sql.sql' не найден.\n";
    }
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

echo "\nПроверка завершена. Данные для входа:\n";
echo "Администратор: admin / blood666admin\n";
echo "Пользователь: blooduser / blood666user\n";
echo "</pre>";

echo '<p><a href="index.php" style="color: #660000; text-decoration: none; font-family: \'Courier New\', monospace; font-weight: bold; font-size: 16px;">Перейти к bloodbrood</a></p>';
?> 