<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connection.php';

require_method(['GET']);

$pdo = get_pdo();
$statement = $pdo->query(
    'SELECT priority_id, priority_name, priority_level
     FROM task_priorities
     ORDER BY priority_level ASC'
);

success_response($statement->fetchAll(), 'Task priorities fetched.');
