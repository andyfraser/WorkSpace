<?php
require __DIR__ . '/api/db.php';

$taskStmt = $pdo->query('SELECT * FROM tasks ORDER BY created_at DESC');
$tasks = $taskStmt->fetchAll();

function priorityBadgeClass(string $p): string {
    return match($p) {
        'Low'  => 'badge-priority-Low',
        'High' => 'badge-priority-High',
        default => 'badge-priority-Medium',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkSpace Dashboard</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-dark bg-dark px-3">
    <span class="navbar-brand">WorkSpace</span>
    <div class="d-flex align-items-center gap-2 ms-auto">
        <input id="search-bar" type="search" class="form-control form-control-sm" placeholder="Search..." style="width:220px">
    </div>
</nav>

<div class="container-fluid py-4 px-4">

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pane-tasks" role="tab">
                Task Manager
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-contacts" role="tab">
                Contact Directory
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- ── Task Manager pane ── -->
        <div class="tab-pane fade show active" id="pane-tasks" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Tasks</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-add-task">
                    + Add Task
                </button>
            </div>

            <div id="task-cards">
                <?php foreach ($tasks as $task): ?>
                <?php $completedClass = $task['status'] == 1 ? ' completed' : ''; ?>
                <div class="card task-card shadow-sm<?= $completedClass ?>" data-id="<?= $task['id'] ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge <?= priorityBadgeClass($task['priority'] ?? 'Medium') ?>">
                                <?= htmlspecialchars($task['priority'] ?? 'Medium') ?>
                            </span>
                            <button class="btn btn-sm btn-outline-danger btn-delete-task ms-2" title="Delete">&#x2715;</button>
                        </div>
                        <h6 class="card-title"><?= htmlspecialchars($task['title']) ?></h6>
                        <div class="form-check mt-2">
                            <input class="form-check-input task-status-toggle" type="checkbox" title="Mark complete"
                                <?= $task['status'] == 1 ? 'checked' : '' ?>>
                            <label class="form-check-label text-muted small">Complete</label>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <p id="task-empty" class="empty-state mt-3 <?= count($tasks) > 0 ? 'd-none' : '' ?>">
                No tasks yet. Add one above.
            </p>
        </div>

        <!-- ── Contact Directory pane ── -->
        <div class="tab-pane fade" id="pane-contacts" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Contacts</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-add-contact">
                    + Add Contact
                </button>
            </div>

            <div id="alert-contact" style="display:none"></div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Company</th>
                            <th>Phone</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="contact-tbody">
                        <tr><td colspan="5" class="text-center empty-state">Switch to this tab to load contacts.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- .tab-content -->
</div><!-- .container-fluid -->

<!-- Toast container -->
<div id="toast-container"></div>

<!-- ── Add Task Modal ── -->
<div class="modal fade" id="modal-add-task" tabindex="-1" aria-labelledby="modal-add-task-label" aria-hidden="true">
    <div class="modal-dialog">
        <form id="form-add-task">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-add-task-label">Add Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="task-title">Title <span class="text-danger">*</span></label>
                        <input id="task-title" type="text" class="form-control" required placeholder="e.g. Review proposal">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="task-priority">Priority</label>
                        <select id="task-priority" class="form-select">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Task</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ── Add Contact Modal ── -->
<div class="modal fade" id="modal-add-contact" tabindex="-1" aria-labelledby="modal-add-contact-label" aria-hidden="true">
    <div class="modal-dialog">
        <form id="form-add-contact">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-add-contact-label">Add Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="contact-name">Name <span class="text-danger">*</span></label>
                        <input id="contact-name" type="text" class="form-control" required placeholder="Full name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="contact-email">Email</label>
                        <input id="contact-email" type="email" class="form-control" placeholder="name@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="contact-company">Company</label>
                        <input id="contact-company" type="text" class="form-control" placeholder="Acme Corp">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="contact-phone">Phone</label>
                        <input id="contact-phone" type="tel" class="form-control" placeholder="+1 555 000 0000">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Contact</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="assets/vendor/jquery.min.js"></script>
<script src="assets/vendor/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
