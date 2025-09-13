<?php
// проверка безопасности
if (!defined('ACCESS_ALLOWED')) {
    die('Прямой доступ запрещен');
}
?>
        </div>
    </main>
    
    <!-- Подвал сайта -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <div class="footer-logo">bloodbrood</div>
                    <div class="footer-description">Закрытый форум</div>
                </div>
                
                <div class="footer-links">
                    <a href="forum.php">Форум</a>
                    <a href="tags.php">Теги</a>
                    <a href="upload.php">Файлы</a>
                    <a href="links.php">Ссылки</a>
                </div>
                
                <div class="footer-info">
                    <div class="footer-stats">
                        <?php
                        // статистика сайта
                        try {
                            global $pdo;
                            $stats = [
                                'users' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
                                'messages' => $pdo->query("SELECT COUNT(*) FROM messages WHERE is_deleted = 0")->fetchColumn(),
                                'files' => $pdo->query("SELECT COUNT(*) FROM attachments")->fetchColumn()
                            ];
                        } catch (Exception $e) {
                            $stats = ['users' => '?', 'messages' => '?', 'files' => '?'];
                        }
                        ?>
                        <div>Пользователей: <?= $stats['users'] ?></div>
                        <div>Сообщений: <?= $stats['messages'] ?></div>
                        <div>Файлов: <?= $stats['files'] ?></div>
                    </div>
                </div>
            </div>
            
            <div class="footer-copyright">
                &copy; <?= date('Y') ?> bloodbrood. Все права защищены.
            </div>
        </div>
    </footer>
    
    <!-- Модальные окна -->
    <div class="modal-overlay" id="globalModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Заголовок</h3>
                <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                Содержимое модального окна загружается...
            </div>
            <div class="modal-footer" id="modalFooter">
                <button type="button" class="btn" data-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>

    <!-- Иконки SVG -->
    <svg xmlns="http://www.w3.org/2000/svg" style="display: none;">
        <symbol id="icon-lock" viewBox="0 0 24 24">
            <path d="M19 10h-1V7c0-4-3-7-7-7S4 3 4 7v3H3c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V12c0-1.1-.9-2-2-2zm-9 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm4-9H8V7c0-2.2 1.8-4 4-4s4 1.8 4 4v3z"/>
        </symbol>
        <symbol id="icon-message" viewBox="0 0 24 24">
            <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.2L4 17.2V4h16v12z"/>
        </symbol>
        <symbol id="icon-file" viewBox="0 0 24 24">
            <path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11z"/>
        </symbol>
        <symbol id="icon-link" viewBox="0 0 24 24">
            <path d="M3.9 12c0-1.7 1.4-3.1 3.1-3.1h4V7H7c-2.8 0-5 2.2-5 5s2.2 5 5 5h4v-1.9H7c-1.7 0-3.1-1.4-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.7 0 3.1 1.4 3.1 3.1s-1.4 3.1-3.1 3.1h-4V17h4c2.8 0 5-2.2 5-5s-2.2-5-5-5z"/>
        </symbol>
        <symbol id="icon-tag" viewBox="0 0 24 24">
            <path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58s1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/>
        </symbol>
        <symbol id="icon-user" viewBox="0 0 24 24">
            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
        </symbol>
        <symbol id="icon-admin" viewBox="0 0 24 24">
            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
        </symbol>
        <symbol id="icon-search" viewBox="0 0 24 24">
            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
        </symbol>
    </svg>
    
    <!-- Глобальные JavaScript функции -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Работа с модальными окнами
        const modal = document.getElementById('globalModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalBody = document.getElementById('modalBody');
        const modalFooter = document.getElementById('modalFooter');
        
        // Открытие модального окна
        window.openModal = function(title, content, footer = null) {
            modalTitle.textContent = title;
            modalBody.innerHTML = content;
            
            if (footer) {
                modalFooter.innerHTML = footer;
            } else {
                modalFooter.innerHTML = '<button type="button" class="btn" data-dismiss="modal">Закрыть</button>';
            }
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        };
        
        // Закрытие модального окна
        window.closeModal = function() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        };
        
        // Обработка событий для закрытия модального окна
        document.querySelectorAll('[data-dismiss="modal"]').forEach(button => {
            button.addEventListener('click', closeModal);
        });
        
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
        
        // Закрытие модального окна по Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeModal();
            }
        });
        
        // Динамическое добавление обработчиков для модальных окон
        document.querySelectorAll('[data-toggle="modal"]').forEach(button => {
            button.addEventListener('click', function() {
                const target = this.getAttribute('data-target');
                const title = this.getAttribute('data-title') || 'Информация';
                
                if (target === '#globalModal') {
                    const content = this.getAttribute('data-content') || '';
                    openModal(title, content);
                }
            });
        });
        
        // Расшифровка зашифрованных сообщений
        document.querySelectorAll('.decrypt-button').forEach(button => {
            button.addEventListener('click', function() {
                const messageId = this.getAttribute('data-id');
                const contentElement = document.getElementById('encrypted-content-' + messageId);
                
                fetch('api/decrypt.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: messageId }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        contentElement.innerHTML = data.content;
                        this.style.display = 'none';
                    } else {
                        alert('Ошибка: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                    alert('Произошла ошибка при расшифровке');
                });
            });
        });
    });
    </script>

    <!-- Дополнительные скрипты для текущей страницы -->
    <?php if (isset($additionalScripts)): ?>
        <script><?= $additionalScripts ?></script>
    <?php endif; ?>
</body>
</html> 