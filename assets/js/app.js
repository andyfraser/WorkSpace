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

    function buildTaskCard(task) {
        var completedClass = task.status == 1 ? ' completed' : '';
        var checked = task.status == 1 ? ' checked' : '';
        return '<div class="card task-card shadow-sm' + completedClass + '" data-id="' + task.id + '">' +
            '<div class="card-body">' +
            '<div class="d-flex justify-content-between align-items-start mb-2">' +
            priorityBadge(task.priority) +
            '<button class="btn btn-sm btn-outline-danger btn-delete-task ms-2" title="Delete">&#x2715;</button>' +
            '</div>' +
            '<h6 class="card-title">' + $('<span>').text(task.title).html() + '</h6>' +
            '<div class="form-check mt-2">' +
            '<input class="form-check-input task-status-toggle" type="checkbox" title="Mark complete"' + checked + '>' +
            '<label class="form-check-label text-muted small">Complete</label>' +
            '</div>' +
            '</div></div>';
    }

    function prependTask(task) {
        var $card = $(buildTaskCard(task));
        $('#task-cards').prepend($card);
        updateEmptyState();
    }

    function updateEmptyState() {
        var $cards = $('#task-cards .task-card');
        if ($cards.length === 0) {
            $('#task-empty').show();
        } else {
            $('#task-empty').hide();
        }
    }

    // Add task
    $('#form-add-task').on('submit', function (e) {
        e.preventDefault();
        var title    = $('#task-title').val().trim();
        var priority = $('#task-priority').val();
        if (!title) return;

        $.ajax({
            url: 'api/tasks.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ title: title, priority: priority }),
            success: function (task) {
                prependTask(task);
                var modal = bootstrap.Modal.getInstance(document.getElementById('modal-add-task'));
                if (modal) modal.hide();
                $('#form-add-task')[0].reset();
                showToast('Task added.');
            },
            error: function () { showToast('Failed to add task.', 'danger'); }
        });
    });

    // Delete task (event delegation)
    $('#task-cards').on('click', '.btn-delete-task', function () {
        var $card = $(this).closest('.task-card');
        var id = $card.data('id');
        $.ajax({
            url: 'api/tasks.php?id=' + id,
            method: 'DELETE',
            success: function () {
                $card.remove();
                updateEmptyState();
                showToast('Task deleted.', 'danger');
            },
            error: function () { showToast('Failed to delete task.', 'danger'); }
        });
    });

    // Toggle task status (event delegation)
    $('#task-cards').on('change', '.task-status-toggle', function () {
        var $card = $(this).closest('.task-card');
        var id = $card.data('id');
        var newStatus = this.checked ? 1 : 0;
        $.ajax({
            url: 'api/tasks.php',
            method: 'PATCH',
            contentType: 'application/json',
            data: JSON.stringify({ id: id, status: newStatus }),
            success: function () {
                $card.toggleClass('completed', newStatus === 1);
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
            '<td><button class="btn btn-sm btn-outline-danger btn-delete-contact">Delete</button></td>' +
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
        var id = $row.data('id');
        $.ajax({
            url: 'api/contacts.php?id=' + id,
            method: 'DELETE',
            success: function () {
                loadContacts();
                showToast('Contact deleted.', 'danger');
            },
            error: function () { showToast('Failed to delete contact.', 'danger'); }
        });
    });

    // ── Search ────────────────────────────────────────────────────────────────
    $('#search-bar').on('keyup', function () {
        var term = $(this).val().toLowerCase();
        var activePane = $('.tab-pane.active').attr('id');

        if (activePane === 'pane-tasks') {
            $('#task-cards .task-card').each(function () {
                var title = $(this).find('.card-title').text().toLowerCase();
                $(this).toggle(title.indexOf(term) !== -1);
            });
        } else if (activePane === 'pane-contacts') {
            $('#contact-tbody tr').each(function () {
                var text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(term) !== -1);
            });
        }
    });

    // Clear search on tab switch
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        $('#search-bar').val('');
        // Re-show all hidden rows/cards
        $('#task-cards .task-card').show();
        $('#contact-tbody tr').show();
    });

});
