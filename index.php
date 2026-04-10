<?php
require __DIR__ . '/api/db.php';

$allTasks = $pdo->query('SELECT * FROM tasks ORDER BY created_at DESC')->fetchAll();

$parentTasks = array_values(array_filter($allTasks, fn($t) => $t['parent_id'] === null));
$childrenMap = [];
foreach ($allTasks as $t) {
    if ($t['parent_id'] !== null) {
        $childrenMap[(int)$t['parent_id']][] = $t;
    }
}

function priorityBadgeClass(string $p): string {
    return match($p) {
        'Low'  => 'badge-priority-Low',
        'High' => 'badge-priority-High',
        default => 'badge-priority-Medium',
    };
}

function calcParentPercent(array $children): int {
    if (empty($children)) return 0;
    return (int)round(array_sum(array_column($children, 'percent')) / count($children));
}

function renderTaskCard(array $task, array $subtasks = [], bool $isSubtask = false): string {
    $pct        = empty($subtasks) ? (int)($task['percent'] ?? 0) : calcParentPercent($subtasks);
    $doneClass  = $task['status'] == 1 ? ' completed' : '';
    $checked    = $task['status'] == 1 ? ' checked' : '';
    $subClass   = $isSubtask ? ' subtask-card' : '';

    $h  = '<div class="card task-card shadow-sm' . $doneClass . $subClass . '"';
    $h .= ' data-id="' . (int)$task['id'] . '" data-percent="' . $pct . '">';
    $h .= '<div class="card-body">';

    // Header: priority badge + action buttons
    $h .= '<div class="d-flex justify-content-between align-items-start mb-2">';
    $h .= '<span class="badge ' . priorityBadgeClass($task['priority'] ?? 'Medium') . '">'
        . htmlspecialchars($task['priority'] ?? 'Medium') . '</span>';
    $h .= '<div class="d-flex gap-1">';
    $h .= '<button class="btn btn-sm btn-outline-secondary btn-edit-task" title="Edit">&#x270E;</button>';
    $h .= '<button class="btn btn-sm btn-outline-danger btn-delete-task" title="Delete">&#x2715;</button>';
    $h .= '</div></div>';

    // Title
    $h .= '<h6 class="card-title">' . htmlspecialchars($task['title']) . '</h6>';

    // Progress bar
    $h .= '<div class="task-progress-wrap d-flex align-items-center gap-2 mt-2">';
    $h .= '<div class="progress flex-grow-1" style="height:6px">';
    $h .= '<div class="progress-bar task-progress-bar" role="progressbar"';
    $h .= ' style="width:' . $pct . '%" aria-valuenow="' . $pct . '"';
    $h .= ' aria-valuemin="0" aria-valuemax="100"></div></div>';
    $h .= '<small class="text-muted task-percent-label">' . $pct . '%</small>';
    $h .= '</div>';

    // Leaf-only: editable percent slider + complete checkbox
    if (empty($subtasks)) {
        $h .= '<input type="range" class="form-range task-percent-range mt-1"';
        $h .= ' value="' . $pct . '" min="0" max="100">';
        $h .= '<div class="form-check mt-1">';
        $h .= '<input class="form-check-input task-status-toggle" type="checkbox"';
        $h .= ' title="Mark complete"' . $checked . '>';
        $h .= '<label class="form-check-label text-muted small">Complete</label>';
        $h .= '</div>';
    }

    // Subtask container
    $h .= '<div class="subtask-list mt-2">';
    foreach ($subtasks as $sub) {
        $h .= renderTaskCard($sub, [], true);
    }
    $h .= '</div>';

    // Add-subtask button for top-level tasks only
    if (!$isSubtask) {
        $h .= '<button class="btn btn-sm btn-link p-0 mt-1 text-decoration-none small btn-add-subtask">';
        $h .= '+ Add Subtask</button>';
    }

    $h .= '</div></div>';
    return $h;
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
                <?php foreach ($parentTasks as $task):
                    echo renderTaskCard($task, $childrenMap[(int)$task['id']] ?? []);
                endforeach; ?>
            </div>
            <p id="task-empty" class="empty-state mt-3 <?= count($parentTasks) > 0 ? 'd-none' : '' ?>">
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

<!-- ── Edit Task Modal ── -->
<div class="modal fade" id="modal-edit-task" tabindex="-1" aria-labelledby="modal-edit-task-label" aria-hidden="true">
    <div class="modal-dialog">
        <form id="form-edit-task">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-edit-task-label">Edit Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit-task-id">
                    <div class="mb-3">
                        <label class="form-label" for="edit-task-title">Title <span class="text-danger">*</span></label>
                        <input id="edit-task-title" type="text" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit-task-priority">Priority</label>
                        <select id="edit-task-priority" class="form-select">
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Completion %</label>
                        <div class="d-flex align-items-center gap-2">
                            <input id="edit-task-percent" type="range" class="form-range flex-grow-1" min="0" max="100" value="0">
                            <span id="edit-task-percent-display" style="min-width:3em;text-align:right">0%</span>
                        </div>
                        <small id="edit-percent-readonly-msg" class="text-muted" style="display:none">
                            Auto-calculated from subtasks
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ── Edit Contact Modal ── -->
<div class="modal fade" id="modal-edit-contact" tabindex="-1" aria-labelledby="modal-edit-contact-label" aria-hidden="true">
    <div class="modal-dialog">
        <form id="form-edit-contact">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-edit-contact-label">Edit Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit-contact-id">
                    <div id="alert-edit-contact" style="display:none"></div>
                    <div class="mb-3">
                        <label class="form-label" for="edit-contact-name">Name <span class="text-danger">*</span></label>
                        <input id="edit-contact-name" type="text" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit-contact-email">Email</label>
                        <input id="edit-contact-email" type="email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit-contact-company">Company</label>
                        <input id="edit-contact-company" type="text" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit-contact-phone">Phone</label>
                        <input id="edit-contact-phone" type="tel" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
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
