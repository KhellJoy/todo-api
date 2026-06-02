<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connection.php';

$pdo = get_pdo();
$method = require_method(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
$action = strtolower(trim((string) ($_GET['action'] ?? '')));

function public_user(array $user): array
{
    unset($user['password_hash']);
    return $user;
}

function fetch_user(PDO $pdo, int $userId): ?array
{
    $statement = $pdo->prepare(
        'SELECT user_id, first_name, last_name, email, is_active, created_at, updated_at
         FROM users
         WHERE user_id = :user_id'
    );
    $statement->execute(['user_id' => $userId]);
    $user = $statement->fetch();

    return $user ?: null;
}

function fetch_user_with_password(PDO $pdo, string $email): ?array
{
    $statement = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();

    return $user ?: null;
}

function register_user(PDO $pdo, array $input): void
{
    $firstName = input_text($input, 'first_name');
    $lastName = input_text($input, 'last_name');
    $email = input_email($input, 'email');
    $password = input_text($input, 'password');
    $isActive = array_key_exists('is_active', $input)
        ? input_is_active($input['is_active'])
        : 1;

    $statement = $pdo->prepare(
        'INSERT INTO users (first_name, last_name, email, password_hash, is_active)
         VALUES (:first_name, :last_name, :email, :password_hash, :is_active)'
    );

    try {
        $statement->execute([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'is_active' => $isActive,
        ]);
    } catch (PDOException $error) {
        if ($error->getCode() === '23000') {
            error_response('Email is already registered.', 409);
        }

        throw $error;
    }

    $user = fetch_user($pdo, (int) $pdo->lastInsertId());
    success_response($user, 'User registered.', 201);
}

function login_user(PDO $pdo, array $input): void
{
    $email = input_email($input, 'email');
    $password = input_text($input, 'password');
    $user = fetch_user_with_password($pdo, $email);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        error_response('Invalid email or password.', 401);
    }

    if ((int) $user['is_active'] !== 1) {
        error_response('User account is inactive.', 403);
    }

    success_response(public_user($user), 'Login successful.');
}

if ($method === 'GET') {
    $userId = query_int('user_id');

    if ($userId) {
        $user = fetch_user($pdo, $userId);

        if (!$user) {
            error_response('User not found.', 404);
        }

        success_response($user, 'User fetched.');
    }

    $statement = $pdo->query(
        'SELECT user_id, first_name, last_name, email, is_active, created_at, updated_at
         FROM users
         ORDER BY user_id DESC'
    );
    success_response($statement->fetchAll(), 'Users fetched.');
}

if ($method === 'POST') {
    $input = request_body();

    if ($action === 'login') {
        login_user($pdo, $input);
    }

    register_user($pdo, $input);
}

if ($method === 'PUT' || $method === 'PATCH') {
    $input = request_body();
    $userId = query_int('user_id') ?? input_int($input, 'user_id');

    $existingUser = fetch_user($pdo, $userId);
    if (!$existingUser) {
        error_response('User not found.', 404);
    }

    if ($action === 'activate' || $action === 'deactivate') {
        $isActive = $action === 'activate' ? 1 : 0;
        $statement = $pdo->prepare('UPDATE users SET is_active = :is_active WHERE user_id = :user_id');
        $statement->execute(['is_active' => $isActive, 'user_id' => $userId]);
        success_response(fetch_user($pdo, $userId), 'User status updated.');
    }

    $fields = [];
    $params = ['user_id' => $userId];

    if (array_key_exists('first_name', $input)) {
        $fields[] = 'first_name = :first_name';
        $params['first_name'] = input_text($input, 'first_name');
    }

    if (array_key_exists('last_name', $input)) {
        $fields[] = 'last_name = :last_name';
        $params['last_name'] = input_text($input, 'last_name');
    }

    if (array_key_exists('email', $input)) {
        $fields[] = 'email = :email';
        $params['email'] = input_email($input, 'email');
    }

    if (array_key_exists('password', $input)) {
        $fields[] = 'password_hash = :password_hash';
        $params['password_hash'] = password_hash(input_text($input, 'password'), PASSWORD_DEFAULT);
    }

    if (array_key_exists('is_active', $input)) {
        $fields[] = 'is_active = :is_active';
        $params['is_active'] = input_is_active($input['is_active']);
    }

    if (!$fields) {
        error_response('No valid user fields were provided.', 422);
    }

    $statement = $pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE user_id = :user_id');

    try {
        $statement->execute($params);
    } catch (PDOException $error) {
        if ($error->getCode() === '23000') {
            error_response('Email is already registered.', 409);
        }

        throw $error;
    }

    success_response(fetch_user($pdo, $userId), 'User updated.');
}

if ($method === 'DELETE') {
    $input = request_body();
    $userId = query_int('user_id') ?? input_int($input, 'user_id');

    $existingUser = fetch_user($pdo, $userId);
    if (!$existingUser) {
        error_response('User not found.', 404);
    }

    $statement = $pdo->prepare('DELETE FROM users WHERE user_id = :user_id');
    $statement->execute(['user_id' => $userId]);

    success_response(['user_id' => $userId], 'User deleted.');
}
