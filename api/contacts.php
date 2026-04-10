<?php

header('Content-Type: application/json');
require __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $pdo->query('SELECT * FROM contacts ORDER BY name ASC');
        echo json_encode($stmt->fetchAll());

    } elseif ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $name    = trim($body['name']    ?? '');
        $email   = trim($body['email']   ?? '');
        $company = trim($body['company'] ?? '');
        $phone   = trim($body['phone']   ?? '');

        if ($name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Name is required']);
            exit;
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email address']);
            exit;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO contacts (name, email, company, phone) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email ?: null, $company ?: null, $phone ?: null]);
        $id = $pdo->lastInsertId();

        $row = $pdo->prepare('SELECT * FROM contacts WHERE id = ?');
        $row->execute([$id]);
        echo json_encode($row->fetch());

    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $body = json_decode(file_get_contents('php://input'), true);
            $id = $body['id'] ?? null;
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM contacts WHERE id = ?');
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['error' => 'Email already exists']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
}
