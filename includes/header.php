<?php
// проверка безопасности
if (!defined('ACCESS_ALLOWED')) {
    die('Прямой доступ запрещен');
}

// текущая страница для меню
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>bloodbrood</title>
    
    <!-- шрифты -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- стили -->
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    
    <!-- мета-теги -->
    <meta name="description" content="bloodbrood - закрытый форум">
    <meta name="keywords" content="bloodbrood, форум, сообщения, теги">
    <meta name="author" content="bloodbrood">
    <meta name="theme-color" content="#000000">
    
    <!-- мета-теги для соцсетей -->
    <meta property="og:title" content="<?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>bloodbrood">
    <meta property="og:description" content="bloodbrood - закрытый форум">
    <meta property="og:type" content="website">
    <meta property="og:image" content="assets/img/og-image.jpg">
    
    <!-- запрет индексации -->
    <meta name="robots" content="noindex, nofollow">
    
    <!-- дополнительные стили -->
    <?php if (isset($additionalStyles)): ?>
        <style><?= $additionalStyles ?></style>
    <?php endif; ?>
</head>
<body>
    <!-- шапка сайта -->
    <header class="header">
        <div class="container header-inner">
            <a href="forum.php" class="logo-text">bloodbrood</a>
            
            <?php if (isLoggedIn()): ?>
                <nav class="nav-menu">
                    <a href="forum.php" class="<?= $currentPage === 'forum.php' ? 'active' : '' ?>">Форум</a>
                    <a href="tags.php" class="<?= $currentPage === 'tags.php' ? 'active' : '' ?>">Теги</a>
                    <a href="upload.php" class="<?= $currentPage === 'upload.php' ? 'active' : '' ?>">Файлы</a>
                    <a href="links.php" class="<?= $currentPage === 'links.php' ? 'active' : '' ?>">Ссылки</a>
                    <a href="profile.php" class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">Профиль</a>
                    <?php if (isAdmin()): ?>
                        <a href="admin.php" class="<?= $currentPage === 'admin.php' ? 'active' : '' ?>">Админ</a>
                    <?php endif; ?>
                </nav>
                
                <div class="user-menu">
                    <span class="username">
                        <?= htmlspecialchars($_SESSION['username']) ?>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <span class="admin-badge">admin</span>
                        <?php endif; ?>
                    </span>
                    <a href="auth.php?logout=1" class="btn btn-sm">Выход</a>
                </div>
            <?php endif; ?>
        </div>
    </header>
    
    <!-- Основное содержимое -->
    <main class="main-content">
        <div class="container fade-in">
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($pageTitle) && $currentPage !== 'forum.php' && $currentPage !== 'index.php'): ?>
                <h1 class="title"><?= htmlspecialchars($pageTitle) ?></h1>
            <?php endif; ?> 