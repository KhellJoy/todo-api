<?php
// Returns all tasks from the MySQL database.
require_once __DIR__ . "/db.php";

try {
    $statement = $pdo->query(
        "SELECT id, title, note, is_done, created_at FROM tasks ORDER BY created_at DESC"
    );

    echo json_encode([
        "status" => "success",
        "data" => $statement->fetchAll()
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "data" => [
            "message" => "Failed to load tasks",
            "details" => $exception->getMessage()
        ]
    ]);
}
