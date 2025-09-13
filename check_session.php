<?php
// Отображение всех ошибок
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "### bloodbrood - Проверка настроек сессии ###\n\n";

// Импорт настроек сессии
require_once 'includes/session.php';

echo "Настройки сессии загружены...\n";

// Проверяем состояние сессии до запуска
echo "Активна ли сессия до session_start(): " . (session_status() === PHP_SESSION_ACTIVE ? "Да" : "Нет") . "\n";

// Запускаем сессию
session_start();

echo "Сессия запущена...\n";
echo "Активна ли сессия после session_start(): " . (session_status() === PHP_SESSION_ACTIVE ? "Да" : "Нет") . "\n";

// Импортируем настройки базы данных
echo "Загрузка настроек базы данных...\n";
require_once 'config/database.php';
echo "Настройки базы данных загружены.\n";

// Вывод информации о сессии
echo "\nИнформация о сессии:\n";
echo "ID сессии: " . session_id() . "\n";
echo "Имя сессии: " . session_name() . "\n";
echo "Параметры cookie: " . print_r(session_get_cookie_params(), true) . "\n";

echo "\nТекущие параметры сессии:\n";
$session_settings = [
    'session.gc_maxlifetime',
    'session.use_strict_mode',
    'session.use_only_cookies',
    'session.cookie_httponly',
    'session.cookie_secure'
];

foreach ($session_settings as $setting) {
    echo $setting . ": " . ini_get($setting) . "\n";
}

// Устанавливаем тестовое значение в сессию
$_SESSION['test_value'] = "bloodbrood session test";
echo "\nТестовое значение установлено в сессию: " . $_SESSION['test_value'] . "\n";

echo "\nПроверка завершена. Если вы видите это сообщение без ошибок, значит настройки сессии работают правильно.\n";
echo "</pre>";

echo '<p><a href="index.php" style="color: #660000; text-decoration: none; font-family: \'Courier New\', monospace; font-weight: bold; font-size: 16px;">Перейти к bloodbrood</a></p>';
?> 