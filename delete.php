<?php
// Deletes one task from a JSON request body.
require_once __DIR__ . "/db.php";

$input = json_decode(file_get_contents("php://input"), true) ?: [];
$taskId = (int) ($input["id"] ?? 0);

if ($taskId <= 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "data" => ["message" => "Valid task id is required"]
    ]);
    exit;
}

try {
    $statement = $pdo->prepare("DELETE FROM tasks WHERE id = :id");
    $statement->execute([":id" => $taskId]);

    echo json_encode([
        "status" => "success",
        "message" => "Task deleted",
        "data" => ["id" => $taskId]
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "data" => [
            "message" => "Failed to delete task",
            "details" => $exception->getMessage()
        ]
    ]);
}
