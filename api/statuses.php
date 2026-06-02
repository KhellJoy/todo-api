<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connection.php';

require_method(['GET']);

$pdo = get_pdo();
$statement = $pdo->query('SELECT status_id, status_name FROM task_statuses ORDER BY status_id ASC');

success_response($statement->fetchAll(), 'Task statuses fetched.');
