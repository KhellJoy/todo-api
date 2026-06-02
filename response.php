<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function success_response(mixed $data = null, string $message = 'OK', int $statusCode = 200): void
{
    json_response([
        'success' => true,
        'message' => $message,
        'data' => $data,
    ], $statusCode);
}

function error_response(string $message, int $statusCode = 400, array $errors = []): void
{
    json_response([
        'success' => false,
        'message' => $message,
        'errors' => $errors,
    ], $statusCode);
}

function require_method(array $allowedMethods): string
{
    $method = $_SERVER['REQUEST_METHOD'];

    if (!in_array($method, $allowedMethods, true)) {
        error_response('Method not allowed.', 405);
    }

    return $method;
}

function request_body(): array
{
    $rawBody = file_get_contents('php://input');

    if ($rawBody === false || trim($rawBody) === '') {
        return $_POST;
    }

    $decoded = json_decode($rawBody, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        error_response('Invalid JSON body.', 400);
    }

    return $decoded;
}

function input_text(array $input, string $field, int $maxLength = 255, bool $nullable = false): ?string
{
    if (!array_key_exists($field, $input) || $input[$field] === null) {
        return $nullable ? null : '';
    }

    if (is_array($input[$field]) || is_object($input[$field])) {
        error_response("$field must be a text value.", 422);
    }

    $value = trim(strip_tags((string) $input[$field]));

    if ($value === '' && $nullable) {
        return null;
    }

    if ($value === '') {
        error_response("$field is required.", 422);
    }

    if (strlen($value) > $maxLength) {
        error_response("$field must be {$maxLength} characters or fewer.", 422);
    }

    return $value;
}

function input_email(array $input, string $field): string
{
    $email = input_text($input, $field, 255);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_response("$field must be a valid email address.", 422);
    }

    return strtolower($email);
}

function input_int(array $input, string $field, bool $nullable = false, int $min = 1): ?int
{
    if (!array_key_exists($field, $input) || $input[$field] === null || $input[$field] === '') {
        return $nullable ? null : error_response("$field is required.", 422);
    }

    if (filter_var($input[$field], FILTER_VALIDATE_INT) === false) {
        error_response("$field must be an integer.", 422);
    }

    $value = (int) $input[$field];

    if ($value < $min) {
        error_response("$field must be at least {$min}.", 422);
    }

    return $value;
}

function input_is_active(mixed $value): int
{
    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        error_response('is_active must be 0 or 1.', 422);
    }

    $isActive = (int) $value;

    if (!in_array($isActive, [0, 1], true)) {
        error_response('is_active must be 0 or 1.', 422);
    }

    return $isActive;
}

function query_int(string $field, bool $nullable = true): ?int
{
    if (!isset($_GET[$field]) || $_GET[$field] === '') {
        return $nullable ? null : error_response("$field is required.", 422);
    }

    return input_int($_GET, $field);
}
