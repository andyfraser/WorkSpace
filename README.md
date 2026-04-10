# WorkSpace Dashboard

A single-page productivity dashboard with a PHP/SQLite backend and a Bootstrap/jQuery frontend. Demonstrates a no-refresh UX for managing tasks and contacts.

**Stack:** PHP 8.2+, SQLite3, Bootstrap 5.3, jQuery 3.7

## Features

- **Task Manager** — add, delete, and mark tasks complete with Low/Medium/High priority badges
- **Contact Directory** — add and delete contacts with duplicate-email detection
- **Live search** — filters tasks or contacts depending on the active tab
- **No build step** — vendored assets, SQLite database created automatically on first request

## Getting started

To set up the demo (requires an internet connection for the initial install):

```bash
composer install        # installs Bootstrap & jQuery into assets/vendor/
composer serve          # starts the PHP built-in server at http://localhost:8000
```

Once `composer install` is complete, the `assets/vendor/` directory will be populated, and the site can be run offline using `composer serve`.

## Dependency management

Bootstrap and jQuery are vendored locally in `assets/vendor/` and managed via Composer using cross-platform PHP scripts:

```bash
composer update             # upgrade to latest versions within constraints
composer run sync-assets    # re-copy dist files to assets/vendor/
```

## Project structure

```
index.php           # server-renders initial task list; main HTML layout
api/
  db.php            # PDO connection + CREATE TABLE IF NOT EXISTS
  tasks.php         # JSON API: GET / POST / PATCH / DELETE tasks
  contacts.php      # JSON API: GET / POST / DELETE contacts
assets/
  css/style.css
  js/app.js         # all jQuery logic (AJAX, modals, search, toasts)
  vendor/           # Bootstrap & jQuery dist files (managed by Composer)
workspace.db        # SQLite database (auto-created, git-ignored)
```

## API endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `api/tasks.php` | List all tasks |
| POST | `api/tasks.php` | Create a task (`title`, `priority`) |
| PATCH | `api/tasks.php` | Update status or title (`id`, `status`\|`title`) |
| DELETE | `api/tasks.php` | Delete a task (`id`) |
| GET | `api/contacts.php` | List all contacts |
| POST | `api/contacts.php` | Create a contact (`name`, `email`, `company`, `phone`) |
| DELETE | `api/contacts.php` | Delete a contact (`id`) |

Duplicate emails return HTTP 409 with a JSON error message, surfaced as a Bootstrap Alert in the modal.
