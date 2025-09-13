

CREATE DATABASE IF NOT EXISTS bloodbrood_v2
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE bloodbrood_v2;

-- Таблица пользователей с расширенными полями
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'moderator', 'admin', 'shadow') DEFAULT 'user',
    
    -- Профиль
    avatar_hash VARCHAR(64) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    blood_type ENUM('O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'unknown') DEFAULT 'unknown',
    
    -- Статистика
    reputation INT DEFAULT 0,
    karma_points INT DEFAULT 0,
    messages_count INT DEFAULT 0,
    secrets_revealed INT DEFAULT 0,
    
    -- Настройки приватности
    is_anonymous BOOLEAN DEFAULT FALSE,
    show_online_status BOOLEAN DEFAULT TRUE,
    allow_private_messages BOOLEAN DEFAULT TRUE,
    
    -- Статус
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    is_shadow_banned BOOLEAN DEFAULT FALSE,
    last_seen TIMESTAMP NULL DEFAULT NULL,
    
    -- Временные метки
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_reputation (reputation),
    INDEX idx_last_seen (last_seen)
) ENGINE=InnoDB;

-- Таблица сессий с отслеживанием
CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    device_fingerprint VARCHAR(64),
    
    -- Геолокация
    country_code VARCHAR(2),
    city VARCHAR(100),
    
    -- Активность
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    pages_visited INT DEFAULT 1,
    
    -- Безопасность
    is_suspicious BOOLEAN DEFAULT FALSE,
    risk_score DECIMAL(3,2) DEFAULT 0.00,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (session_token),
    INDEX idx_user_activity (user_id, last_activity)
) ENGINE=InnoDB;

-- Таблица сообщений с вложенной структурой
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    thread_id INT DEFAULT NULL,
    
    -- Контент
    content TEXT NOT NULL,
    content_type ENUM('text', 'markdown', 'encrypted', 'hidden') DEFAULT 'text',
    is_encrypted BOOLEAN DEFAULT FALSE,
    encryption_key VARCHAR(255) DEFAULT NULL,
    
    -- Метаданные
    views_count INT DEFAULT 0,
    unique_viewers INT DEFAULT 0,
    likes_count INT DEFAULT 0,
    reports_count INT DEFAULT 0,
    
    -- Статус
    is_pinned BOOLEAN DEFAULT FALSE,
    is_locked BOOLEAN DEFAULT FALSE,
    is_deleted BOOLEAN DEFAULT FALSE,
    is_hidden BOOLEAN DEFAULT FALSE,
    
    -- AI анализ
    sentiment_score DECIMAL(3,2) DEFAULT NULL,
    toxicity_score DECIMAL(3,2) DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (parent_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (thread_id) REFERENCES messages(id) ON DELETE CASCADE,
    INDEX idx_user_messages (user_id, created_at),
    INDEX idx_thread (thread_id),
    INDEX idx_parent (parent_id),
    FULLTEXT idx_content (content)
) ENGINE=InnoDB;

-- Таблица тегов с иерархией
CREATE TABLE tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT DEFAULT NULL,
    name VARCHAR(50) UNIQUE NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#8b0000',
    icon VARCHAR(50) DEFAULT NULL,
    
    -- Статистика
    usage_count INT DEFAULT 0,
    trending_score DECIMAL(5,2) DEFAULT 0.00,
    
    -- Ограничения
    min_reputation_required INT DEFAULT 0,
    is_system BOOLEAN DEFAULT FALSE,
    is_hidden BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (parent_id) REFERENCES tags(id) ON DELETE CASCADE,
    INDEX idx_slug (slug),
    INDEX idx_trending (trending_score DESC)
) ENGINE=InnoDB;

-- Связь сообщений и тегов
CREATE TABLE message_tags (
    message_id INT NOT NULL,
    tag_id INT NOT NULL,
    added_by INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (message_id, tag_id),
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Таблица реакций
CREATE TABLE reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message_id INT NOT NULL,
    reaction_type ENUM('like', 'blood', 'skull', 'eye', 'knife', 'shadow') NOT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_user_message_reaction (user_id, message_id, reaction_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    INDEX idx_message_reactions (message_id, reaction_type)
) ENGINE=InnoDB;

-- Таблица приватных сообщений
CREATE TABLE private_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    
    -- Контент
    content TEXT NOT NULL,
    is_encrypted BOOLEAN DEFAULT TRUE,
    
    -- Статус
    is_read BOOLEAN DEFAULT FALSE,
    is_deleted_by_sender BOOLEAN DEFAULT FALSE,
    is_deleted_by_recipient BOOLEAN DEFAULT FALSE,
    
    -- Метаданные
    read_at TIMESTAMP NULL DEFAULT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_recipient_unread (recipient_id, is_read),
    INDEX idx_conversation (sender_id, recipient_id, created_at)
) ENGINE=InnoDB;

-- Таблица файлов
CREATE TABLE files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message_id INT DEFAULT NULL,
    
    -- Файл
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes BIGINT NOT NULL,
    hash_sha256 VARCHAR(64) UNIQUE NOT NULL,
    
    -- Метаданные
    width INT DEFAULT NULL,
    height INT DEFAULT NULL,
    duration_seconds INT DEFAULT NULL,
    
    -- Безопасность
    is_encrypted BOOLEAN DEFAULT FALSE,
    is_scanned BOOLEAN DEFAULT FALSE,
    is_safe BOOLEAN DEFAULT TRUE,
    virus_scan_result TEXT DEFAULT NULL,
    
    -- Статистика
    download_count INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL,
    INDEX idx_hash (hash_sha256),
    INDEX idx_user_files (user_id, created_at)
) ENGINE=InnoDB;

-- Таблица секретных комнат
CREATE TABLE secret_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    
    -- Доступ
    password_hash VARCHAR(255) DEFAULT NULL,
    required_reputation INT DEFAULT 100,
    max_members INT DEFAULT 13,
    current_members INT DEFAULT 0,
    
    -- Настройки
    is_anonymous_only BOOLEAN DEFAULT TRUE,
    auto_delete_messages_hours INT DEFAULT 24,
    
    -- Статус
    is_active BOOLEAN DEFAULT TRUE,
    is_hidden BOOLEAN DEFAULT TRUE,
    
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_code (code),
    INDEX idx_active_hidden (is_active, is_hidden)
) ENGINE=InnoDB;

-- Члены секретных комнат
CREATE TABLE secret_room_members (
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    
    -- Роль в комнате
    role ENUM('member', 'guardian', 'keeper') DEFAULT 'member',
    
    -- Статистика
    messages_sent INT DEFAULT 0,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (room_id, user_id),
    FOREIGN KEY (room_id) REFERENCES secret_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Таблица событий (аудит)
CREATE TABLE events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    
    -- Событие
    event_type VARCHAR(50) NOT NULL,
    event_category ENUM('auth', 'message', 'file', 'secret', 'moderation', 'system') NOT NULL,
    description TEXT,
    
    -- Контекст
    ip_address VARCHAR(45),
    user_agent TEXT,
    related_id INT DEFAULT NULL,
    related_type VARCHAR(50) DEFAULT NULL,
    
    -- Данные
    old_data JSON DEFAULT NULL,
    new_data JSON DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_events (user_id, created_at),
    INDEX idx_event_type (event_type, created_at),
    INDEX idx_related (related_type, related_id)
) ENGINE=InnoDB;

-- Таблица достижений
CREATE TABLE achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    
    -- Условия
    required_action VARCHAR(100),
    required_count INT DEFAULT 1,
    
    -- Награды
    karma_reward INT DEFAULT 0,
    badge_icon VARCHAR(50),
    
    -- Редкость
    rarity ENUM('common', 'rare', 'epic', 'legendary', 'cursed') DEFAULT 'common',
    
    is_hidden BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Достижения пользователей
CREATE TABLE user_achievements (
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    
    progress INT DEFAULT 0,
    is_completed BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    
    PRIMARY KEY (user_id, achievement_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
    INDEX idx_completed (user_id, is_completed)
) ENGINE=InnoDB;

-- Таблица банов и ограничений
CREATE TABLE bans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    banned_by INT NOT NULL,
    
    -- Тип бана
    ban_type ENUM('temporary', 'permanent', 'shadow', 'ip') NOT NULL,
    
    -- Детали
    reason TEXT NOT NULL,
    evidence TEXT,
    ip_address VARCHAR(45) DEFAULT NULL,
    
    -- Время
    expires_at TIMESTAMP NULL DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    lifted_at TIMESTAMP NULL DEFAULT NULL,
    lifted_by INT DEFAULT NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (banned_by) REFERENCES users(id),
    FOREIGN KEY (lifted_by) REFERENCES users(id),
    INDEX idx_active_bans (user_id, expires_at),
    INDEX idx_ip_bans (ip_address)
) ENGINE=InnoDB;

-- Таблица настроек системы
CREATE TABLE system_settings (
    key_name VARCHAR(100) PRIMARY KEY,
    value_data TEXT,
    value_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT DEFAULT NULL,
    
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Вставка начальных данных

-- Системные теги
INSERT INTO tags (name, slug, color, is_system, description) VALUES
('секрет', 'secret', '#8b0000', TRUE, 'Скрытая информация'),
('кровь', 'blood', '#dc143c', TRUE, 'Связано с кровью'),
('тьма', 'darkness', '#1a1a1a', TRUE, 'Темные темы'),
('запрещено', 'forbidden', '#ff0000', TRUE, 'Запрещенный контент'),
('шифр', 'cipher', '#4b0082', TRUE, 'Зашифрованные сообщения'),
('ритуал', 'ritual', '#800020', TRUE, 'Ритуальные практики'),
('предупреждение', 'warning', '#ff4500', TRUE, 'Важные предупреждения');

-- Базовые достижения
INSERT INTO achievements (code, name, description, required_action, required_count, karma_reward, rarity) VALUES
('first_blood', 'Первая кровь', 'Опубликовать первое сообщение', 'post_message', 1, 10, 'common'),
('night_owl', 'Ночная сова', 'Быть активным после полуночи', 'midnight_activity', 1, 20, 'common'),
('secret_keeper', 'Хранитель секретов', 'Отправить зашифрованное сообщение', 'encrypted_message', 1, 30, 'rare'),
('shadow_walker', 'Теневой ходок', 'Найти секретную комнату', 'find_secret_room', 1, 50, 'epic'),
('blood_moon', 'Кровавая луна', 'Быть активным 30 дней подряд', 'daily_activity', 30, 100, 'legendary'),
('forbidden_knowledge', 'Запретное знание', '???', 'unknown', 1, 666, 'cursed');

-- Системные настройки
INSERT INTO system_settings (key_name, value_data, value_type, description) VALUES
('maintenance_mode', 'false', 'boolean', 'Режим обслуживания'),
('max_upload_size', '10485760', 'integer', 'Максимальный размер загрузки в байтах'),
('encryption_enabled', 'true', 'boolean', 'Включено ли шифрование'),
('shadow_ban_threshold', '5', 'integer', 'Количество жалоб для теневого бана'),
('secret_room_lifetime_hours', '168', 'integer', 'Время жизни секретной комнаты в часах');

-- Создание представлений для аналитики

CREATE VIEW user_statistics AS
SELECT 
    u.id,
    u.username,
    u.reputation,
    u.karma_points,
    COUNT(DISTINCT m.id) as total_messages,
    COUNT(DISTINCT pm.id) as private_messages_sent,
    COUNT(DISTINCT ua.achievement_id) as achievements_unlocked,
    COUNT(DISTINCT srm.room_id) as secret_rooms_joined
FROM users u
LEFT JOIN messages m ON u.id = m.user_id AND m.is_deleted = FALSE
LEFT JOIN private_messages pm ON u.id = pm.sender_id
LEFT JOIN user_achievements ua ON u.id = ua.user_id AND ua.is_completed = TRUE
LEFT JOIN secret_room_members srm ON u.id = srm.user_id
GROUP BY u.id;

-- Триггеры для автоматизации

DELIMITER $$

-- Обновление статистики пользователя при новом сообщении
CREATE TRIGGER after_message_insert
AFTER INSERT ON messages
FOR EACH ROW
BEGIN
    UPDATE users 
    SET messages_count = messages_count + 1 
    WHERE id = NEW.user_id;
    
    -- Проверка достижения "Первая кровь"
    INSERT INTO user_achievements (user_id, achievement_id, progress, is_completed, completed_at)
    SELECT NEW.user_id, id, 1, TRUE, NOW()
    FROM achievements
    WHERE code = 'first_blood'
    ON DUPLICATE KEY UPDATE
        progress = progress + 1,
        is_completed = TRUE,
        completed_at = NOW();
END$$

-- Автоматическое удаление старых сообщений в секретных комнатах
CREATE EVENT IF NOT EXISTS delete_old_secret_messages
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    DELETE m FROM messages m
    INNER JOIN secret_rooms sr ON m.thread_id = sr.id
    WHERE sr.auto_delete_messages_hours IS NOT NULL
    AND m.created_at < DATE_SUB(NOW(), INTERVAL sr.auto_delete_messages_hours HOUR);
END$$

DELIMITER ; 