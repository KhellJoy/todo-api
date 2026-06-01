<?php
// Creates a new task from a JSON request body.
require_once __DIR__ . "/db.php";

$input = json_decode(file_get_contents("php://input"), true) ?: [];
$title = trim($input["title"] ?? "");
$note = trim($input["note"] ?? "");

if ($title === "") {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "data" => ["message" => "Title is required"]
    ]);
    exit;
}

try {
    $statement = $pdo->prepare("INSERT INTO tasks (title, note) VALUES (:title, :note)");
    $statement->execute([
        ":title" => $title,
        ":note" => $note,
    ]);

    $taskId = (int) $pdo->lastInsertId();
    $selectStatement = $pdo->prepare(
        "SELECT id, title, note, is_done, created_at FROM tasks WHERE id = :id"
    );
    $selectStatement->execute([":id" => $taskId]);

    echo json_encode([
        "status" => "success",
        "data" => $selectStatement->fetch()
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "data" => [
            "message" => "Failed to create task",
            "details" => $exception->getMessage()
        ]
    ]);
}
