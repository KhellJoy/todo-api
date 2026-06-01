<?php
// Deletes every completed task from the MySQL database.
require_once __DIR__ . "/db.php";

try {
    $statement = $pdo->prepare("DELETE FROM tasks WHERE is_done = 1");
    $statement->execute();

    echo json_encode([
        "status" => "success",
        "message" => "Completed tasks cleared",
        "data" => ["deleted" => $statement->rowCount()]
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "data" => [
            "message" => "Failed to clear completed tasks",
            "details" => $exception->getMessage()
        ]
    ]);
}
