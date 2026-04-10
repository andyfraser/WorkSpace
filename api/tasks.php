<?php

header('Content-Type: application/json');
require __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $pdo->query('SELECT * FROM tasks ORDER BY created_at DESC');
        echo json_encode($stmt->fetchAll());

    } elseif ($method === 'POST') {
        $body     = json_decode(file_get_contents('php://input'), true);
        $title    = trim($body['title'] ?? '');
        $priority = $body['priority'] ?? 'Medium';
        $parentId = isset($body['parent_id']) ? (int)$body['parent_id'] : null;

        if ($title === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Title is required']);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO tasks (title, priority, parent_id) VALUES (?, ?, ?)');
        $stmt->execute([$title, $priority, $parentId]);
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

        // Cascade: delete subtasks first
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE parent_id = ?');
        $stmt->execute([$id]);

        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ?');
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);

    } elseif ($method === 'PATCH') {
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = $body['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }

        $sets   = [];
        $params = [];

        if (array_key_exists('status', $body)) {
            $sets[]   = 'status = ?';
            $params[] = (int)$body['status'];
        }
        if (array_key_exists('title', $body)) {
            $title = trim($body['title']);
            if ($title === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Title cannot be empty']);
                exit;
            }
            $sets[]   = 'title = ?';
            $params[] = $title;
        }
        if (array_key_exists('priority', $body)) {
            $sets[]   = 'priority = ?';
            $params[] = $body['priority'];
        }
        if (array_key_exists('percent', $body)) {
            // Reject if this task has subtasks (percent is auto-calculated for parents)
            $chk = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE parent_id = ?');
            $chk->execute([$id]);
            if ($chk->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot set percent on a task with subtasks']);
                exit;
            }
            $sets[]   = 'percent = ?';
            $params[] = max(0, min(100, (int)$body['percent']));
        }

        if (empty($sets)) {
            http_response_code(400);
            echo json_encode(['error' => 'Nothing to update']);
            exit;
        }

        $params[] = $id;
        $stmt = $pdo->prepare('UPDATE tasks SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($params);

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
