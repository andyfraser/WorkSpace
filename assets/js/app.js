$(function () {

    // ── Toast helper ─────────────────────────────────────────────────────────
    function showToast(message, type) {
        type = type || 'success';
        var colorClass = type === 'success' ? 'text-bg-success' : 'text-bg-danger';
        var $toast = $('<div class="toast align-items-center border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">' +
            '<div class="d-flex">' +
            '<div class="toast-body">' + $('<span>').text(message).html() + '</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
            '</div></div>');
        $toast.addClass(colorClass);
        $('#toast-container').append($toast);
        var toastEl = new bootstrap.Toast($toast[0], { delay: 3000 });
        toastEl.show();
        $toast[0].addEventListener('hidden.bs.toast', function () { $toast.remove(); });
    }

    // ── Task rendering ────────────────────────────────────────────────────────
    function priorityBadge(priority) {
        var p = priority || 'Medium';
        return '<span class="badge badge-priority-' + p + '">' + p + '</span>';
    }

    function calcParentPercent(subtasks) {
        if (!subtasks || !subtasks.length) return 0;
        var total = subtasks.reduce(function (sum, s) { return sum + (parseInt(s.percent) || 0); }, 0);
        return Math.round(total / subtasks.length);
    }

    function updateCardPercent($card, pct) {
        $card.attr('data-percent', pct);
        $card.find('> .card-body > .task-progress-wrap .task-progress-bar')
            .css('width', pct + '%').attr('aria-valuenow', pct);
        $card.find('> .card-body > .task-progress-wrap .task-percent-label').text(pct + '%');
    }

    function recalcParentPercent($parentCard) {
        var total = 0, count = 0;
        $parentCard.find('> .card-body > .subtask-list > .task-card').each(function () {
            total += parseInt($(this).attr('data-percent')) || 0;
            count++;
        });
        var pct = count > 0 ? Math.round(total / count) : 0;
        updateCardPercent($parentCard, pct);
    }

    // Build a task card HTML string.
    // subtasks: array of subtask objects (pass [] for leaf tasks)
    // isSubtask: true when this card will be nested inside a parent
    function buildTaskCard(task, subtasks, isSubtask) {
        subtasks  = subtasks  || [];
        isSubtask = !!isSubtask;
        var hasSubtasks  = subtasks.length > 0;
        var pct          = hasSubtasks ? calcParentPercent(subtasks) : (parseInt(task.percent) || 0);
        var completedCls = task.status == 1 ? ' completed' : '';
        var checked      = task.status == 1 ? ' checked' : '';
        var subtaskCls   = isSubtask ? ' subtask-card' : '';

        var h = '<div class="card task-card shadow-sm' + completedCls + subtaskCls + '"'
              + ' data-id="' + task.id + '" data-percent="' + pct + '">';
        h += '<div class="card-body">';

        // Header: priority badge + action buttons
        h += '<div class="d-flex justify-content-between align-items-start mb-2">';
        h += priorityBadge(task.priority);
        h += '<div class="d-flex gap-1">';
        h += '<button class="btn btn-sm btn-outline-secondary btn-edit-task" title="Edit">&#x270E;</button>';
        h += '<button class="btn btn-sm btn-outline-danger btn-delete-task" title="Delete">&#x2715;</button>';
        h += '</div></div>';

        // Title
        h += '<h6 class="card-title">' + $('<span>').text(task.title).html() + '</h6>';

        // Progress bar
        h += '<div class="task-progress-wrap d-flex align-items-center gap-2 mt-2">';
        h += '<div class="progress flex-grow-1" style="height:6px">';
        h += '<div class="progress-bar task-progress-bar" role="progressbar"'
           + ' style="width:' + pct + '%" aria-valuenow="' + pct + '"'
           + ' aria-valuemin="0" aria-valuemax="100"></div></div>';
        h += '<small class="text-muted task-percent-label">' + pct + '%</small>';
        h += '</div>';

        // Leaf-only: editable percent slider + complete checkbox
        if (!hasSubtasks) {
            h += '<input type="range" class="form-range task-percent-range mt-1"'
               + ' value="' + pct + '" min="0" max="100">';
            h += '<div class="form-check mt-1">';
            h += '<input class="form-check-input task-status-toggle" type="checkbox"'
               + ' title="Mark complete"' + checked + '>';
            h += '<label class="form-check-label text-muted small">Complete</label>';
            h += '</div>';
        }

        // Subtask container (always present so subtasks can be added later)
        h += '<div class="subtask-list mt-2">';
        subtasks.forEach(function (sub) {
            h += buildTaskCard(sub, [], true);
        });
        h += '</div>';

        // Add-subtask button for top-level tasks only
        if (!isSubtask) {
            h += '<button class="btn btn-sm btn-link p-0 mt-1 text-decoration-none small btn-add-subtask">'
               + '+ Add Subtask</button>';
        }

        h += '</div></div>';
        return h;
    }

    function prependTask(task) {
        $('#task-cards').prepend($(buildTaskCard(task, [], false)));
        updateEmptyState();
    }

    function addSubtaskToParent(task) {
        var $parentCard  = $('#task-cards .task-card[data-id="' + task.parent_id + '"]');
        var $body        = $parentCard.find('> .card-body');
        var $subtaskList = $body.find('> .subtask-list');
        var isFirst      = $subtaskList.children('.task-card').length === 0;

        // First subtask: remove leaf-only controls from parent
        if (isFirst) {
            $body.find('> .task-percent-range').remove();
            $body.find('> .form-check').remove();
        }

        $subtaskList.append(buildTaskCard(task, [], true));
        recalcParentPercent($parentCard);
    }

    function updateEmptyState() {
        $('#task-empty').toggle($('#task-cards > .task-card').length === 0);
    }

    // ── Percent slider (live preview + save on release) ───────────────────────
    $('#task-cards').on('input', '.task-percent-range', function () {
        var $card = $(this).closest('.task-card');
        var pct   = parseInt($(this).val());
        updateCardPercent($card, pct);
        var $parentCard = $card.closest('.subtask-list').closest('.task-card');
        if ($parentCard.length) recalcParentPercent($parentCard);
    });

    $('#task-cards').on('change', '.task-percent-range', function () {
        var $card = $(this).closest('.task-card');
        $.ajax({
            url: 'api/tasks.php',
            method: 'PATCH',
            contentType: 'application/json',
            data: JSON.stringify({ id: $card.data('id'), percent: parseInt($(this).val()) }),
            error: function () { showToast('Failed to save progress.', 'danger'); }
        });
    });

    // ── Add task (also used for subtasks) ─────────────────────────────────────
    var pendingParentId = null;

    $('#task-cards').on('click', '.btn-add-subtask', function () {
        pendingParentId = $(this).closest('.task-card').data('id');
        $('#modal-add-task-label').text('Add Subtask');
        new bootstrap.Modal(document.getElementById('modal-add-task')).show();
    });

    $('#modal-add-task').on('hidden.bs.modal', function () {
        pendingParentId = null;
        $('#modal-add-task-label').text('Add Task');
        $('#form-add-task')[0].reset();
    });

    $('#form-add-task').on('submit', function (e) {
        e.preventDefault();
        var title    = $('#task-title').val().trim();
        var priority = $('#task-priority').val();
        if (!title) return;

        var payload = { title: title, priority: priority };
        if (pendingParentId) payload.parent_id = pendingParentId;

        $.ajax({
            url: 'api/tasks.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function (task) {
                if (task.parent_id) {
                    addSubtaskToParent(task);
                } else {
                    prependTask(task);
                }
                var modal = bootstrap.Modal.getInstance(document.getElementById('modal-add-task'));
                if (modal) modal.hide();
                showToast(task.parent_id ? 'Subtask added.' : 'Task added.');
            },
            error: function () { showToast('Failed to add task.', 'danger'); }
        });
    });

    // ── Delete task ───────────────────────────────────────────────────────────
    $('#task-cards').on('click', '.btn-delete-task', function () {
        var $card        = $(this).closest('.task-card');
        var $subtaskList = $card.closest('.subtask-list');
        var $parentCard  = $subtaskList.closest('.task-card');
        var isSubtask    = $parentCard.length > 0;

        $.ajax({
            url: 'api/tasks.php?id=' + $card.data('id'),
            method: 'DELETE',
            success: function () {
                $card.remove();
                if (isSubtask) {
                    if ($subtaskList.children('.task-card').length === 0) {
                        // No subtasks left — restore leaf controls on parent
                        $parentCard.find('> .card-body > .task-progress-wrap').after(
                            '<input type="range" class="form-range task-percent-range mt-1" value="0" min="0" max="100">'
                        );
                        $parentCard.find('> .card-body > .btn-add-subtask').before(
                            '<div class="form-check mt-1">' +
                            '<input class="form-check-input task-status-toggle" type="checkbox" title="Mark complete">' +
                            '<label class="form-check-label text-muted small">Complete</label>' +
                            '</div>'
                        );
                        updateCardPercent($parentCard, 0);
                    } else {
                        recalcParentPercent($parentCard);
                    }
                } else {
                    updateEmptyState();
                }
                showToast('Task deleted.', 'danger');
            },
            error: function () { showToast('Failed to delete task.', 'danger'); }
        });
    });

    // ── Toggle task status ────────────────────────────────────────────────────
    $('#task-cards').on('change', '.task-status-toggle', function () {
        var $card     = $(this).closest('.task-card');
        var newStatus = this.checked ? 1 : 0;
        $.ajax({
            url: 'api/tasks.php',
            method: 'PATCH',
            contentType: 'application/json',
            data: JSON.stringify({ id: $card.data('id'), status: newStatus }),
            success: function () {
                $card.toggleClass('completed', newStatus === 1);
            },
            error: function () { showToast('Failed to update task.', 'danger'); }
        });
    });

    // ── Edit task — open modal ────────────────────────────────────────────────
    $('#task-cards').on('click', '.btn-edit-task', function () {
        var $card    = $(this).closest('.task-card');
        var $body    = $card.find('> .card-body');
        var isParent = $body.find('> .subtask-list > .task-card').length > 0;
        var pct      = parseInt($card.attr('data-percent')) || 0;

        $('#edit-task-id').val($card.data('id'));
        $('#edit-task-title').val($body.find('> .card-title').text());
        $('#edit-task-priority').val($body.find('> .d-flex > .badge').text().trim());
        $('#edit-task-percent').val(pct).prop('disabled', isParent);
        $('#edit-task-percent-display').text(pct + '%');
        $('#edit-percent-readonly-msg').toggle(isParent);

        new bootstrap.Modal(document.getElementById('modal-edit-task')).show();
    });

    // Live-update percent display while dragging in edit modal
    $('#edit-task-percent').on('input', function () {
        $('#edit-task-percent-display').text($(this).val() + '%');
    });

    // ── Edit task — submit ────────────────────────────────────────────────────
    $('#form-edit-task').on('submit', function (e) {
        e.preventDefault();
        var title    = $('#edit-task-title').val().trim();
        var priority = $('#edit-task-priority').val();
        if (!title) return;

        var payload = { id: $('#edit-task-id').val(), title: title, priority: priority };
        var $pctInput = $('#edit-task-percent');
        if (!$pctInput.prop('disabled')) {
            payload.percent = parseInt($pctInput.val()) || 0;
        }

        $.ajax({
            url: 'api/tasks.php',
            method: 'PATCH',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function (task) {
                var $card = $('#task-cards .task-card[data-id="' + task.id + '"]');
                var $body = $card.find('> .card-body');
                $body.find('> .d-flex > .badge')
                    .attr('class', 'badge badge-priority-' + task.priority)
                    .text(task.priority);
                $body.find('> .card-title').text(task.title);
                if ('percent' in payload) {
                    var pct = task.percent || 0;
                    $body.find('> .task-percent-range').val(pct);
                    updateCardPercent($card, pct);
                    var $parentCard = $card.closest('.subtask-list').closest('.task-card');
                    if ($parentCard.length) recalcParentPercent($parentCard);
                }
                var modal = bootstrap.Modal.getInstance(document.getElementById('modal-edit-task'));
                if (modal) modal.hide();
                showToast('Task updated.');
            },
            error: function () { showToast('Failed to update task.', 'danger'); }
        });
    });

    // Initial empty state
    updateEmptyState();

    // ── Contacts ─────────────────────────────────────────────────────────────
    var contactsLoaded = false;

    function buildContactRow(c) {
        return '<tr data-id="' + c.id + '">' +
            '<td>' + $('<span>').text(c.name).html()    + '</td>' +
            '<td>' + $('<span>').text(c.email   || '').html() + '</td>' +
            '<td>' + $('<span>').text(c.company || '').html() + '</td>' +
            '<td>' + $('<span>').text(c.phone   || '').html() + '</td>' +
            '<td>' +
            '<button class="btn btn-sm btn-outline-secondary btn-edit-contact me-1">Edit</button>' +
            '<button class="btn btn-sm btn-outline-danger btn-delete-contact">Delete</button>' +
            '</td>' +
            '</tr>';
    }

    function loadContacts() {
        $.getJSON('api/contacts.php', function (contacts) {
            var $tbody = $('#contact-tbody');
            $tbody.empty();
            if (contacts.length === 0) {
                $tbody.append('<tr><td colspan="5" class="text-center empty-state">No contacts yet.</td></tr>');
            } else {
                contacts.forEach(function (c) { $tbody.append(buildContactRow(c)); });
            }
        }).fail(function () { showToast('Failed to load contacts.', 'danger'); });
    }

    // Load contacts when tab first shown
    $('button[data-bs-target="#pane-contacts"]').on('shown.bs.tab', function () {
        if (!contactsLoaded) {
            loadContacts();
            contactsLoaded = true;
        }
    });

    // Add contact
    $('#form-add-contact').on('submit', function (e) {
        e.preventDefault();
        $('#alert-contact').hide().empty();

        var payload = {
            name:    $('#contact-name').val().trim(),
            email:   $('#contact-email').val().trim(),
            company: $('#contact-company').val().trim(),
            phone:   $('#contact-phone').val().trim()
        };

        $.ajax({
            url: 'api/contacts.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function () {
                loadContacts();
                contactsLoaded = true;
                var modal = bootstrap.Modal.getInstance(document.getElementById('modal-add-contact'));
                if (modal) modal.hide();
                $('#form-add-contact')[0].reset();
                showToast('Contact added.');
            },
            error: function (xhr) {
                var msg = 'Failed to add contact.';
                try { msg = JSON.parse(xhr.responseText).error || msg; } catch (_) {}
                $('#alert-contact').removeClass('d-none').show()
                    .html('<div class="alert alert-danger alert-dismissible fade show mb-0" role="alert">' +
                        $('<span>').text(msg).html() +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
            }
        });
    });

    // Delete contact (event delegation)
    $('#contact-tbody').on('click', '.btn-delete-contact', function () {
        var $row = $(this).closest('tr');
        $.ajax({
            url: 'api/contacts.php?id=' + $row.data('id'),
            method: 'DELETE',
            success: function () {
                loadContacts();
                showToast('Contact deleted.', 'danger');
            },
            error: function () { showToast('Failed to delete contact.', 'danger'); }
        });
    });

    // Edit contact — open modal (event delegation)
    $('#contact-tbody').on('click', '.btn-edit-contact', function () {
        var $row   = $(this).closest('tr');
        var $cells = $row.find('td');
        $('#edit-contact-id').val($row.data('id'));
        $('#edit-contact-name').val($cells.eq(0).text());
        $('#edit-contact-email').val($cells.eq(1).text());
        $('#edit-contact-company').val($cells.eq(2).text());
        $('#edit-contact-phone').val($cells.eq(3).text());
        $('#alert-edit-contact').hide().empty();
        new bootstrap.Modal(document.getElementById('modal-edit-contact')).show();
    });

    // Edit contact — submit
    $('#form-edit-contact').on('submit', function (e) {
        e.preventDefault();
        $('#alert-edit-contact').hide().empty();

        var payload = {
            id:      $('#edit-contact-id').val(),
            name:    $('#edit-contact-name').val().trim(),
            email:   $('#edit-contact-email').val().trim(),
            company: $('#edit-contact-company').val().trim(),
            phone:   $('#edit-contact-phone').val().trim()
        };

        $.ajax({
            url: 'api/contacts.php',
            method: 'PATCH',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function () {
                loadContacts();
                var modal = bootstrap.Modal.getInstance(document.getElementById('modal-edit-contact'));
                if (modal) modal.hide();
                showToast('Contact updated.');
            },
            error: function (xhr) {
                var msg = 'Failed to update contact.';
                try { msg = JSON.parse(xhr.responseText).error || msg; } catch (_) {}
                $('#alert-edit-contact').removeClass('d-none').show()
                    .html('<div class="alert alert-danger alert-dismissible fade show mb-0" role="alert">' +
                        $('<span>').text(msg).html() +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
            }
        });
    });

    // ── Search ────────────────────────────────────────────────────────────────
    $('#search-bar').on('keyup', function () {
        var term       = $(this).val().toLowerCase();
        var activePane = $('.tab-pane.active').attr('id');

        if (activePane === 'pane-tasks') {
            $('#task-cards > .task-card').each(function () {
                var title = $(this).find('> .card-body > .card-title').text().toLowerCase();
                $(this).toggle(title.indexOf(term) !== -1);
            });
        } else if (activePane === 'pane-contacts') {
            $('#contact-tbody tr').each(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(term) !== -1);
            });
        }
    });

    // Clear search on tab switch
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        $('#search-bar').val('');
        $('#task-cards > .task-card').show();
        $('#contact-tbody tr').show();
    });

});
