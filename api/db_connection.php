<?php
declare(strict_types=1);

require_once __DIR__ . '/response.php';

define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST'));
define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME'));
define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER'));
define('DB_PASS', $_ENV['DB_PASS'] ?? getenv('DB_PASS'));

function get_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=3306;dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        initialize_database($pdo);

        return $pdo;
    } catch (PDOException $error) {
        error_response('Database connection failed.', 500, [$error->getMessage()]);
    }
}

function initialize_database(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(255) NOT NULL,
            last_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            is_active INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS task_statuses (
            status_id INT AUTO_INCREMENT PRIMARY KEY,
            status_name VARCHAR(255) NOT NULL UNIQUE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS task_priorities (
            priority_id INT AUTO_INCREMENT PRIMARY KEY,
            priority_name VARCHAR(255) NOT NULL UNIQUE,
            priority_level INT NOT NULL UNIQUE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS tasks (
            task_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            status_id INT NOT NULL,
            priority_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            details VARCHAR(255) NULL,
            deadline INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            CONSTRAINT fk_tasks_user
                FOREIGN KEY (user_id)
                REFERENCES users(user_id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,

            CONSTRAINT fk_tasks_status
                FOREIGN KEY (status_id)
                REFERENCES task_statuses(status_id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE,

            CONSTRAINT fk_tasks_priority
                FOREIGN KEY (priority_id)
                REFERENCES task_priorities(priority_id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
        )'
    );

    $statusStatement = $pdo->prepare('INSERT IGNORE INTO task_statuses (status_name) VALUES (?)');
    foreach (['pending', 'completed', 'cancelled'] as $status) {
        $statusStatement->execute([$status]);
    }

    $priorityStatement = $pdo->prepare(
        'INSERT IGNORE INTO task_priorities (priority_name, priority_level) VALUES (?, ?)'
    );
    foreach ([['low', 1], ['medium', 2], ['high', 3]] as $priority) {
        $priorityStatement->execute($priority);
    }
}