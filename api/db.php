<?php

$dbPath = __DIR__ . '/../workspace.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    title      TEXT NOT NULL,
    status     INTEGER DEFAULT 0,
    priority   TEXT,
    parent_id  INTEGER REFERENCES tasks(id),
    percent    INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Migrate existing tables: add new columns if missing
$cols = array_column($pdo->query('PRAGMA table_info(tasks)')->fetchAll(), 'name');
if (!in_array('parent_id', $cols)) {
    $pdo->exec('ALTER TABLE tasks ADD COLUMN parent_id INTEGER REFERENCES tasks(id)');
}
if (!in_array('percent', $cols)) {
    $pdo->exec('ALTER TABLE tasks ADD COLUMN percent INTEGER DEFAULT 0');
}

$pdo->exec("CREATE TABLE IF NOT EXISTS contacts (
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    name    TEXT NOT NULL,
    email   TEXT UNIQUE,
    company TEXT,
    phone   TEXT
)");
