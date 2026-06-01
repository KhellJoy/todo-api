<?php
// Deletes every task from the MySQL database.
require_once __DIR__ . "/db.php";

try {
    $pdo->exec("DELETE FROM tasks");

    echo json_encode([
        "status" => "success",
        "message" => "All tasks cleared",
        "data" => []
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "data" => [
            "message" => "Failed to clear tasks",
            "details" => $exception->getMessage()
        ]
    ]);
}
