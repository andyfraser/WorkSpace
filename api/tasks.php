<?php

header('Content-Type: application/json');
require __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $pdo->query('SELECT * FROM tasks ORDER BY created_at DESC');
        echo json_encode($stmt->fetchAll());

    } elseif ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $title = trim($body['title'] ?? '');
        $priority = $body['priority'] ?? 'Medium';

        if ($title === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Title is required']);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO tasks (title, priority) VALUES (?, ?)');
        $stmt->execute([$title, $priority]);
        $id = $pdo->lastInsertId();

        $row = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
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

        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ?');
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);

    } elseif ($method === 'PATCH') {
        $body = json_decode(file_get_contents('php://input'), true);
        $id = $body['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }

        if (isset($body['status'])) {
            $stmt = $pdo->prepare('UPDATE tasks SET status = ? WHERE id = ?');
            $stmt->execute([(int) $body['status'], $id]);
        } elseif (isset($body['title'])) {
            $title = trim($body['title']);
            if ($title === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Title cannot be empty']);
                exit;
            }
            $priority = $body['priority'] ?? null;
            if ($priority !== null) {
                $stmt = $pdo->prepare('UPDATE tasks SET title = ?, priority = ? WHERE id = ?');
                $stmt->execute([$title, $priority, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE tasks SET title = ? WHERE id = ?');
                $stmt->execute([$title, $id]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Nothing to update']);
            exit;
        }

        $row = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
        $row->execute([$id]);
        echo json_encode($row->fetch());

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
