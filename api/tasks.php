<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connection.php';

$pdo = get_pdo();
$method = require_method(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
$action = strtolower(trim((string) ($_GET['action'] ?? '')));

function record_exists(PDO $pdo, string $table, string $idColumn, int $id): bool
{
    $statement = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$idColumn} = :id LIMIT 1");
    $statement->execute(['id' => $id]);
    return (bool) $statement->fetchColumn();
}

function validate_task_relationships(PDO $pdo, array $ids): void
{
    if (array_key_exists('user_id', $ids) && !record_exists($pdo, 'users', 'user_id', (int) $ids['user_id'])) {
        error_response('user_id does not exist.', 422);
    }

    if (array_key_exists('status_id', $ids) && !record_exists($pdo, 'task_statuses', 'status_id', (int) $ids['status_id'])) {
        error_response('status_id does not exist.', 422);
    }

    if (array_key_exists('priority_id', $ids) && !record_exists($pdo, 'task_priorities', 'priority_id', (int) $ids['priority_id'])) {
        error_response('priority_id does not exist.', 422);
    }
}

function task_select_sql(string $where = ''): string
{
    return
        'SELECT
            t.task_id,
            t.user_id,
            t.status_id,
            t.priority_id,
            t.title,
            t.details,
            t.deadline,
            t.created_at,
            t.updated_at,
            s.status_name,
            p.priority_name,
            p.priority_level,
            u.first_name,
            u.last_name,
            u.email
        FROM tasks t
        INNER JOIN users u ON u.user_id = t.user_id
        INNER JOIN task_statuses s ON s.status_id = t.status_id
        INNER JOIN task_priorities p ON p.priority_id = t.priority_id
        ' . $where . '
        ORDER BY t.task_id DESC';
}

function fetch_task(PDO $pdo, int $taskId): ?array
{
    $statement = $pdo->prepare(task_select_sql('WHERE t.task_id = :task_id'));
    $statement->execute(['task_id' => $taskId]);
    $task = $statement->fetch();

    return $task ?: null;
}

if ($method === 'GET') {
    $taskId = query_int('task_id');
    $userId = query_int('user_id');

    if ($taskId) {
        $task = fetch_task($pdo, $taskId);

        if (!$task) {
            error_response('Task not found.', 404);
        }

        success_response($task, 'Task fetched.');
    }

    if ($userId) {
        $statement = $pdo->prepare(task_select_sql('WHERE t.user_id = :user_id'));
        $statement->execute(['user_id' => $userId]);
        success_response($statement->fetchAll(), 'User tasks fetched.');
    }

    $statement = $pdo->query(task_select_sql());
    success_response($statement->fetchAll(), 'Tasks fetched.');
}

if ($method === 'POST') {
    $input = request_body();

    $userId = input_int($input, 'user_id');
    $statusId = input_int($input, 'status_id');
    $priorityId = input_int($input, 'priority_id');
    $title = input_text($input, 'title');
    $details = input_text($input, 'details', 255, true);
    $deadline = input_int($input, 'deadline', true, 0);

    validate_task_relationships($pdo, [
        'user_id' => $userId,
        'status_id' => $statusId,
        'priority_id' => $priorityId,
    ]);

    $statement = $pdo->prepare(
        'INSERT INTO tasks (user_id, status_id, priority_id, title, details, deadline)
         VALUES (:user_id, :status_id, :priority_id, :title, :details, :deadline)'
    );
    $statement->execute([
        'user_id' => $userId,
        'status_id' => $statusId,
        'priority_id' => $priorityId,
        'title' => $title,
        'details' => $details,
        'deadline' => $deadline,
    ]);

    success_response(fetch_task($pdo, (int) $pdo->lastInsertId()), 'Task created.', 201);
}

if ($method === 'PUT' || $method === 'PATCH') {
    $input = request_body();
    $taskId = query_int('task_id') ?? input_int($input, 'task_id');

    $existingTask = fetch_task($pdo, $taskId);
    if (!$existingTask) {
        error_response('Task not found.', 404);
    }

    if ($action === 'status') {
        $statusId = input_int($input, 'status_id');
        validate_task_relationships($pdo, ['status_id' => $statusId]);

        $statement = $pdo->prepare('UPDATE tasks SET status_id = :status_id WHERE task_id = :task_id');
        $statement->execute(['status_id' => $statusId, 'task_id' => $taskId]);
        success_response(fetch_task($pdo, $taskId), 'Task status updated.');
    }

    if ($action === 'priority') {
        $priorityId = input_int($input, 'priority_id');
        validate_task_relationships($pdo, ['priority_id' => $priorityId]);

        $statement = $pdo->prepare('UPDATE tasks SET priority_id = :priority_id WHERE task_id = :task_id');
        $statement->execute(['priority_id' => $priorityId, 'task_id' => $taskId]);
        success_response(fetch_task($pdo, $taskId), 'Task priority updated.');
    }

    $fields = [];
    $params = ['task_id' => $taskId];
    $relationshipIds = [];

    if (array_key_exists('user_id', $input)) {
        $fields[] = 'user_id = :user_id';
        $params['user_id'] = input_int($input, 'user_id');
        $relationshipIds['user_id'] = $params['user_id'];
    }

    if (array_key_exists('status_id', $input)) {
        $fields[] = 'status_id = :status_id';
        $params['status_id'] = input_int($input, 'status_id');
        $relationshipIds['status_id'] = $params['status_id'];
    }

    if (array_key_exists('priority_id', $input)) {
        $fields[] = 'priority_id = :priority_id';
        $params['priority_id'] = input_int($input, 'priority_id');
        $relationshipIds['priority_id'] = $params['priority_id'];
    }

    if (array_key_exists('title', $input)) {
        $fields[] = 'title = :title';
        $params['title'] = input_text($input, 'title');
    }

    if (array_key_exists('details', $input)) {
        $fields[] = 'details = :details';
        $params['details'] = input_text($input, 'details', 255, true);
    }

    if (array_key_exists('deadline', $input)) {
        $fields[] = 'deadline = :deadline';
        $params['deadline'] = input_int($input, 'deadline', true, 0);
    }

    if (!$fields) {
        error_response('No valid task fields were provided.', 422);
    }

    validate_task_relationships($pdo, $relationshipIds);

    $statement = $pdo->prepare('UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE task_id = :task_id');
    $statement->execute($params);

    success_response(fetch_task($pdo, $taskId), 'Task updated.');
}

if ($method === 'DELETE') {
    $input = request_body();
    $taskId = query_int('task_id') ?? input_int($input, 'task_id');

    $existingTask = fetch_task($pdo, $taskId);
    if (!$existingTask) {
        error_response('Task not found.', 404);
    }

    $statement = $pdo->prepare('DELETE FROM tasks WHERE task_id = :task_id');
    $statement->execute(['task_id' => $taskId]);

    success_response(['task_id' => $taskId], 'Task deleted.');
}
