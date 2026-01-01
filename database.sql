CREATE DATABASE IF NOT EXISTS hackcraft_db;
USE hackcraft_db;

-- Пользователи
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    level INT DEFAULT 1,
    experience INT DEFAULT 0,
    is_verified BOOLEAN DEFAULT FALSE,
    is_admin BOOLEAN DEFAULT FALSE,
    vip_level ENUM('none', 'vip', 'hacker') DEFAULT 'none',
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Достижения
CREATE TABLE achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_name VARCHAR(100) NOT NULL,
    description TEXT,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Статьи
CREATE TABLE articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content LONGTEXT NOT NULL,
    is_encrypted BOOLEAN DEFAULT FALSE,
    required_level INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    author_id INT,
    FOREIGN KEY (author_id) REFERENCES users(id)
);

-- Задания
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    command VARCHAR(100) NOT NULL,
    level_required INT NOT NULL,
    experience_reward INT NOT NULL,
    category VARCHAR(50) NOT NULL
);

-- Завершенные задания пользователей
CREATE TABLE user_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    task_id INT NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id)
);

-- Блокировки IP для админ-панели
CREATE TABLE blocked_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempts INT DEFAULT 1,
    blocked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Сервера Minecraft (для парсера)
CREATE TABLE minecraft_servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    port INT NOT NULL,
    name VARCHAR(255),
    version VARCHAR(50),
    players_online INT,
    max_players INT,
    last_scanned TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Вставка администраторов
INSERT INTO users (username, password, email, is_verified, is_admin) VALUES
('admin1', '$2y$10$YourHashedPasswordHere1', 'admin1@hackcraft.ru', TRUE, TRUE),
('admin2', '$2y$10$YourHashedPasswordHere2', 'admin2@hackcraft.ru', TRUE, TRUE);

-- Вставка начальных заданий
INSERT INTO tasks (title, description, command, level_required, experience_reward, category) VALUES
('Экспериментатор: SQL-инъекция', 'Узнайте название сервера, используя технику SQL-инъекции', '/steal_data', 1, 100, 'Уровень 1'),
('Экспериментатор: Обход файрвола', 'Пропустите защиту файрвола, решив несложную головоломку', '/bypass_firewall', 1, 100, 'Уровень 1'),
('Экспериментатор: Дешифровка', 'Получите ключ для дешифровки более сложного сообщения', '/decrypt_message', 1, 150, 'Уровень 1'),
('Искатель: Брутфорс пароля', 'Используя метод перебора, получите доступ к защищённому хранилищу', '/bruteforce_password', 2, 200, 'Уровень 2'),
('Мастер Хакинга: Бэкдор', 'Установите бэкдор на целевой сервер', '/create_backdoor', 3, 300, 'Уровень 3'),
('Эксперт: Перехват сессии', 'Перехватите сеанс другого игрока', '/hijack_session', 4, 400, 'Уровень 4');

-- Вставка начальных статей
INSERT INTO articles (title, description, content, is_encrypted, required_level, author_id) VALUES
('Введение в хакинг Minecraft', 'Основные понятия и принципы', 'Содержимое статьи о хакерстве в Minecraft...', FALSE, 1, 1),
('Секретные команды серверов', 'Зашифрованная статья для продвинутых', encrypt_text('Это секретная информация о скрытых командах Minecraft серверов...'), TRUE, 2, 1);