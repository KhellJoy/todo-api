<?php
// Updates a task's done state from a JSON request body.
require_once __DIR__ . "/db.php";

$input = json_decode(file_get_contents("php://input"), true) ?: [];
$taskId = (int) ($input["id"] ?? 0);
$isDone = (int) ($input["is_done"] ?? 0);

if ($taskId <= 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "data" => ["message" => "Valid task id is required"]
    ]);
    exit;
}

try {
    $statement = $pdo->prepare("UPDATE tasks SET is_done = :is_done WHERE id = :id");
    $statement->execute([
        ":id" => $taskId,
        ":is_done" => $isDone ? 1 : 0,
    ]);

    echo json_encode([
        "status" => "success",
        "message" => "Task updated",
        "data" => ["id" => $taskId, "is_done" => $isDone ? 1 : 0]
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "data" => [
            "message" => "Failed to update task",
            "details" => $exception->getMessage()
        ]
    ]);
}
