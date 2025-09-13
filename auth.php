<?php
require_once 'includes/init.php';

// Обработка выхода
if (isset($_GET['logout'])) {
    // Уничтожаем сессию
    session_unset();
    session_destroy();
    
    // Перенаправляем на страницу входа
    redirect('auth.php?logged_out=1');
}

// Обработка авторизации
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Введите имя пользователя и пароль';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Обновляем время последнего входа
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Устанавливаем сессию
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['created_at'] = time();
                
                // Логируем вход
                logSystemAction('USER_LOGIN', 'User logged in');
                
                // Перенаправляем на форум
                redirect('forum.php');
            } else {
                $error = 'Неверное имя пользователя или пароль';
                logSystemAction('LOGIN_FAILED', "Failed login attempt for username: $username", null);
            }
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'Ошибка базы данных';
        }
    }
}

// Сообщения после перенаправления
if (isset($_GET['logged_out'])) {
    $success = 'Вы успешно вышли из системы';
} elseif (isset($_GET['expired'])) {
    $error = 'Время сессии истекло. Пожалуйста, войдите снова';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Авторизация - bloodbrood</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        body {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            cursor: none;
            position: relative;
            background: radial-gradient(ellipse at center, #0a0a0a 0%, #000000 100%);
            font-family: 'Courier New', monospace;
        }
        
        /* Кровавый фон */
        .blood-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: -2;
            background: radial-gradient(circle at var(--blood-x, 50%) var(--blood-y, 50%), 
                        rgba(139, 0, 0, 0.03) 0%, 
                        transparent 30%);
            transition: background 0.3s ease;
        }
        
        /* Улучшенный курсор, мб хуйня */
        .custom-cursor {
            position: fixed;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 0, 0, 0.8) 0%, rgba(139, 0, 0, 0.5) 70%, transparent 100%);
            transform: translate(-50%, -50%);
            pointer-events: none;
            z-index: 9999;
            transition: all 0.15s ease-out;
            filter: drop-shadow(0 0 6px rgba(255, 0, 0, 0.3));
            border: 1px solid rgba(255, 0, 0, 0.2);
        }
        
        /* Контейнер авторизации */
        .auth-container {
            max-width: 400px;
            width: 90%;
            background: rgba(10, 10, 10, 0.9);
            border: 1px solid rgba(139, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            position: relative;
            padding: 2rem;
            transition: all 0.3s ease;
            filter: blur(var(--container-blur, 1px)) brightness(var(--container-brightness, 0.8));
        }
        
        .auth-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                        rgba(139, 0, 0, 0.05) 0%, 
                        transparent 50%, 
                        rgba(139, 0, 0, 0.05) 100%);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .auth-container:hover::before {
            opacity: 1;
        }
        
        /* Заголовок */
        .auth-title {
            color: var(--text-muted);
            font-size: 1.8rem;
            text-align: center;
            margin-bottom: 2rem;
            font-weight: bold;
            text-shadow: 0 0 10px rgba(0, 0, 0, 0.8);
            letter-spacing: 0.1em;
            filter: blur(var(--title-blur, 1px)) brightness(var(--title-brightness, 0.7));
            transition: all 0.2s ease;
        }
        
        /* Поля ввода */
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-label {
            display: block;
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            opacity: 0.8;
            font-weight: 500;
            filter: blur(var(--label-blur, 0.5px)) brightness(var(--label-brightness, 0.8));
            transition: all 0.2s ease;
        }
        
        .form-input {
            width: 100%;
            padding: 0.8rem;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid rgba(139, 0, 0, 0.3);
            color: var(--text-primary);
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            outline: none;
            filter: blur(var(--input-blur, 0.5px)) brightness(var(--input-brightness, 0.9));
        }
        
        .form-input:focus {
            border-color: rgba(139, 0, 0, 0.6);
            box-shadow: 0 0 10px rgba(139, 0, 0, 0.2);
            background: rgba(0, 0, 0, 0.8);
        }
        
        .form-input::placeholder {
            color: rgba(160, 160, 160, 0.5);
            font-style: italic;
        }
        
        /* Кнопка */
        .auth-btn {
            width: 100%;
            padding: 1rem;
            background: rgba(139, 0, 0, 0.2);
            border: 1px solid rgba(139, 0, 0, 0.4);
            color: var(--text-primary);
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
            font-weight: bold;
            cursor: none;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-top: 1rem;
            filter: blur(var(--btn-blur, 0.5px)) brightness(var(--btn-brightness, 0.8));
        }
        
        .auth-btn:hover {
            background: rgba(139, 0, 0, 0.3);
            border-color: rgba(139, 0, 0, 0.6);
            box-shadow: 0 0 15px rgba(139, 0, 0, 0.3);
            transform: scale(1.02);
        }
        
        /* Сообщения */
        .message {
            padding: 0.8rem;
            margin-bottom: 1rem;
            border-radius: 0;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            text-align: center;
            filter: blur(var(--message-blur, 0.5px)) brightness(var(--message-brightness, 0.9));
            transition: all 0.2s ease;
        }
        
        .error {
            background: rgba(139, 0, 0, 0.1);
            border: 1px solid rgba(139, 0, 0, 0.3);
            color: #ff6b6b;
        }
        
        .success {
            background: rgba(0, 139, 0, 0.1);
            border: 1px solid rgba(0, 139, 0, 0.3);
            color: #6bff6b;
        }
        
        /* Ссылка назад */
        .back-link {
            position: absolute;
            top: 30px;
            left: 30px;
            color: var(--text-muted);
            text-decoration: none;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            opacity: 0.6;
            transition: all 0.3s ease;
            filter: blur(var(--link-blur, 1px)) brightness(var(--link-brightness, 0.7));
            text-shadow: 0 0 5px rgba(0, 0, 0, 0.8);
        }
        
        .back-link:hover {
            opacity: 1;
            color: var(--text-secondary);
            transform: scale(1.05);
        }
        
        /* Кровавые частицы */
        .blood-particle {
            position: absolute;
            background: radial-gradient(circle, rgba(255, 0, 0, 0.2) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: -1;
            animation: float 8s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { 
                transform: translateY(0px) rotate(0deg); 
                opacity: 0.1; 
            }
            50% { 
                transform: translateY(-10px) rotate(180deg); 
                opacity: 0.3; 
            }
        }
        
        /* Кровавые следы */
        .blood-trail {
            position: absolute;
            background: radial-gradient(circle, rgba(255, 0, 0, 0.3) 0%, rgba(139, 0, 0, 0.1) 50%, transparent 100%);
            border-radius: 50%;
            pointer-events: none;
            z-index: -1;
            animation: trailFade 2s ease-out forwards;
            filter: blur(0.5px);
        }
        
        @keyframes trailFade {
            0% { 
                opacity: 0.4; 
                transform: scale(1); 
            }
            100% { 
                opacity: 0; 
                transform: scale(0.2); 
            }
        }
        
        /* Анимация появления */
        @keyframes fadeIn {
            from { 
                opacity: 0; 
                filter: blur(4px); 
                transform: translateY(20px); 
            }
            to { 
                opacity: 1; 
                filter: blur(0px); 
                transform: translateY(0); 
            }
        }
        
        .auth-container {
            animation: fadeIn 2s ease-out;
        }
        
        /* Шум и зерно */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-image: 
                radial-gradient(circle at 20% 30%, transparent 0%, rgba(255, 0, 0, 0.005) 50%, transparent 100%),
                radial-gradient(circle at 80% 70%, transparent 0%, rgba(139, 0, 0, 0.005) 50%, transparent 100%);
            opacity: 0.4;
            z-index: -1;
            pointer-events: none;
            animation: noise 0.3s infinite;
        }
        
        @keyframes noise {
            0%, 100% { opacity: 0.4; }
            50% { opacity: 0.45; }
        }
        
        @media (max-width: 768px) {
            .auth-container {
                max-width: 350px;
                padding: 1.5rem;
            }
            
            .auth-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="blood-bg"></div>
    <div class="custom-cursor"></div>
    
    <a href="index.php" class="back-link">← назад</a>
    
    <div class="auth-container">
        <h1 class="auth-title">bloodbrood</h1>
        
        <?php if (isset($error)): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="auth.php">
            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
            
            <div class="form-group">
                <label for="username" class="form-label">имя пользователя</label>
                <input type="text" id="username" name="username" class="form-input" 
                       placeholder="введите имя пользователя..." required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">пароль</label>
                <input type="password" id="password" name="password" class="form-input" 
                       placeholder="введите пароль..." required>
            </div>
            
            <button type="submit" class="auth-btn">войти</button>
        </form>
    </div>

    <script>
        // курсор
        const cursor = document.querySelector('.custom-cursor');
        const bloodBg = document.querySelector('.blood-bg');
        const bloodTrails = [];
        const maxTrails = 10;
        
        let mouseX = 0, mouseY = 0;
        let cursorX = 0, cursorY = 0;
        
        function updateCursor() {
            cursorX += (mouseX - cursorX) * 0.4;
            cursorY += (mouseY - cursorY) * 0.4;
            
            cursor.style.left = cursorX + 'px';
            cursor.style.top = cursorY + 'px';
            
            const x = (cursorX / window.innerWidth) * 100;
            const y = (cursorY / window.innerHeight) * 100;
            bloodBg.style.setProperty('--blood-x', x + '%');
            bloodBg.style.setProperty('--blood-y', y + '%');
            
            updateElementSharpness();
            
            requestAnimationFrame(updateCursor);
        }
        updateCursor();
        
        // четкость элементов
        function updateElementSharpness() {
            const elements = [
                { el: document.querySelector('.auth-container'), prefix: 'container' },
                { el: document.querySelector('.auth-title'), prefix: 'title' },
                { el: document.querySelectorAll('.form-label'), prefix: 'label' },
                { el: document.querySelectorAll('.form-input'), prefix: 'input' },
                { el: document.querySelector('.auth-btn'), prefix: 'btn' },
                { el: document.querySelector('.message'), prefix: 'message' },
                { el: document.querySelector('.back-link'), prefix: 'link' }
            ];
            
            elements.forEach(item => {
                if (!item.el) return;
                
                const process = (element) => {
                    const rect = element.getBoundingClientRect();
                    const elementCenterX = rect.left + rect.width / 2;
                    const elementCenterY = rect.top + rect.height / 2;
                    
                    const distance = Math.sqrt(
                        Math.pow(cursorX - elementCenterX, 2) + 
                        Math.pow(cursorY - elementCenterY, 2)
                    );
                    
                    const maxDistance = 300;
                    const normalizedDistance = Math.min(distance / maxDistance, 1);
                    
                    const blurAmount = normalizedDistance * 2;
                    const brightness = 0.6 + (1 - normalizedDistance) * 0.5;
                    
                    element.style.setProperty(`--${item.prefix}-blur`, blurAmount + 'px');
                    element.style.setProperty(`--${item.prefix}-brightness`, brightness);
                };
                
                if (NodeList.prototype.isPrototypeOf(item.el)) {
                    item.el.forEach(process);
                } else {
                    process(item.el);
                }
            });
        }
        
        document.addEventListener('mousemove', (e) => {
            mouseX = e.clientX;
            mouseY = e.clientY;
            
            if (Math.random() > 0.95) {
                createBloodTrail(e.clientX, e.clientY);
            }
        });
        
        // создание следа
        function createBloodTrail(x, y) {
            const trail = document.createElement('div');
            trail.className = 'blood-trail';
            trail.style.left = x + 'px';
            trail.style.top = y + 'px';
            trail.style.width = Math.random() * 6 + 2 + 'px';
            trail.style.height = Math.random() * 6 + 2 + 'px';
            document.body.appendChild(trail);
            
            bloodTrails.push(trail);
            
            if (bloodTrails.length > maxTrails) {
                const oldTrail = bloodTrails.shift();
                oldTrail.remove();
            }
            
            setTimeout(() => {
                trail.remove();
                const index = bloodTrails.indexOf(trail);
                if (index > -1) {
                    bloodTrails.splice(index, 1);
                }
            }, 2000);
        }
        
        // создание частиц
        function createBloodParticles() {
            for (let i = 0; i < 2; i++) {
                const particle = document.createElement('div');
                particle.className = 'blood-particle';
                particle.style.left = Math.random() * window.innerWidth + 'px';
                particle.style.top = Math.random() * window.innerHeight + 'px';
                particle.style.width = Math.random() * 2 + 1 + 'px';
                particle.style.height = Math.random() * 2 + 1 + 'px';
                particle.style.animationDelay = Math.random() * 8 + 's';
                document.body.appendChild(particle);
                
                setTimeout(() => {
                    particle.remove();
                }, 16000);
            }
        }
        
        setInterval(createBloodParticles, 8000);
        
        // эффекты для полей
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', () => {
                cursor.style.width = '16px';
                cursor.style.height = '16px';
                cursor.style.filter = 'drop-shadow(0 0 10px rgba(255, 0, 0, 0.5))';
            });
            
            input.addEventListener('blur', () => {
                cursor.style.width = '12px';
                cursor.style.height = '12px';
                cursor.style.filter = 'drop-shadow(0 0 6px rgba(255, 0, 0, 0.3))';
            });
        });
        
        // эффект для кнопки
        document.querySelector('.auth-btn').addEventListener('mouseover', () => {
            cursor.style.background = 'radial-gradient(circle, rgba(139, 0, 0, 1) 0%, rgba(50, 0, 0, 0.8) 70%, transparent 100%)';
            cursor.style.filter = 'drop-shadow(0 0 12px rgba(139, 0, 0, 0.6))';
        });
        
        document.querySelector('.auth-btn').addEventListener('mouseout', () => {
            cursor.style.background = 'radial-gradient(circle, rgba(255, 0, 0, 0.8) 0%, rgba(139, 0, 0, 0.5) 70%, transparent 100%)';
            cursor.style.filter = 'drop-shadow(0 0 6px rgba(255, 0, 0, 0.3))';
        });
        
        // защита от инспектора
        document.addEventListener('contextmenu', e => e.preventDefault());
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F12' || 
                (e.ctrlKey && e.shiftKey && e.key === 'I') ||
                (e.ctrlKey && e.shiftKey && e.key === 'J') ||
                (e.ctrlKey && e.key === 'U')) {
                e.preventDefault();
            }
        });
        
        // начальный эффект
        window.addEventListener('load', () => {
            setTimeout(() => {
                createBloodTrail(window.innerWidth/2, window.innerHeight/2);
            }, 1000);
        });
    </script>
</body>
</html>