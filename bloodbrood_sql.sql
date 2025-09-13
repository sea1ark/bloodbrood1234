-- Create database
CREATE DATABASE IF NOT EXISTS bloodbrood CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bloodbrood;

-- Users table
CREATE TABLE users (
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
);

-- Chat messages table
CREATE TABLE messages (
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
    FOREIGN KEY (parent_id) REFERENCES messages(id) ON DELETE CASCADE,
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id),
    INDEX idx_deleted (is_deleted),
    INDEX idx_parent_id (parent_id)
);

-- File attachments table
CREATE TABLE attachments (
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
);

-- Links/URLs table
CREATE TABLE links (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message_id INT NOT NULL,
    url TEXT NOT NULL,
    title VARCHAR(500),
    description TEXT,
    is_safe BOOLEAN DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    INDEX idx_message_id (message_id)
);

-- User sessions table (for better security)
CREATE TABLE user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(128) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (session_token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at)
);

-- System logs table
CREATE TABLE system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
);

-- Tags table
CREATE TABLE tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    color VARCHAR(7) DEFAULT '#660000',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
);

-- Message tags relationship
CREATE TABLE message_tags (
    message_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (message_id, tag_id),
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    INDEX idx_message_id (message_id),
    INDEX idx_tag_id (tag_id)
);

-- Create default admin user
-- Password: blood666admin (will be hashed)
INSERT INTO users (username, password_hash, role) VALUES 
('admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Create a second user for testing
-- Password: blood666user (will be hashed)
INSERT INTO users (username, password_hash, role) VALUES 
('blooduser', '$2y$12$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'user');

-- Create some default tags
INSERT INTO tags (name, color) VALUES
('важное', '#cc0000'),
('личное', '#660066'),
('архив', '#333333'),
('ссылки', '#006600'),
('файлы', '#000066');

-- Create some sample encrypted content
INSERT INTO messages (user_id, content, message_type, is_encrypted) VALUES
(1, 'Система инициализирована', 'text', FALSE),
(1, 'Тестовое зашифрованное сообщение', 'text', TRUE);

-- Add some system logs
INSERT INTO system_logs (user_id, action, details) VALUES
(1, 'SYSTEM_INIT', 'Database initialized'),
(1, 'USER_CREATED', 'Admin user created');

-- Create triggers for automatic logging
DELIMITER //

CREATE TRIGGER log_user_login 
AFTER UPDATE ON users 
FOR EACH ROW 
BEGIN
    IF NEW.last_login != OLD.last_login THEN
        INSERT INTO system_logs (user_id, action, details) 
        VALUES (NEW.id, 'USER_LOGIN', CONCAT('User logged in: ', NEW.username));
    END IF;
END//

CREATE TRIGGER log_message_create 
AFTER INSERT ON messages 
FOR EACH ROW 
BEGIN
    INSERT INTO system_logs (user_id, action, details) 
    VALUES (NEW.user_id, 'MESSAGE_CREATED', CONCAT('Message type: ', NEW.message_type));
END//

CREATE TRIGGER log_file_upload 
AFTER INSERT ON attachments 
FOR EACH ROW 
BEGIN
    INSERT INTO system_logs (user_id, action, details) 
    VALUES ((SELECT user_id FROM messages WHERE id = NEW.message_id), 'FILE_UPLOADED', 
            CONCAT('File: ', NEW.original_filename, ' Size: ', NEW.file_size));
END//

-- Trigger to update comment count
CREATE TRIGGER update_comment_count
AFTER INSERT ON messages
FOR EACH ROW
BEGIN
    IF NEW.parent_id IS NOT NULL THEN
        UPDATE messages
        SET comments_count = comments_count + 1
        WHERE id = NEW.parent_id;
    END IF;
END//

-- Trigger to decrement comment count when a comment is deleted
CREATE TRIGGER update_comment_count_on_delete
AFTER UPDATE ON messages
FOR EACH ROW
BEGIN
    IF NEW.is_deleted = 1 AND OLD.is_deleted = 0 AND NEW.parent_id IS NOT NULL THEN
        UPDATE messages
        SET comments_count = comments_count - 1
        WHERE id = NEW.parent_id;
    END IF;
END//

DELIMITER ;

-- Optimize tables
OPTIMIZE TABLE users, messages, attachments, links, user_sessions, system_logs, tags, message_tags;