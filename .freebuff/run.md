# Preview run doc — UT Asset Dashboard (worktree)

Plain PHP 8 + MySQL app (XAMPP-style). No Node, no build step.

## 1. Reproduce uncommitted artifacts

- `vendor/` (PHPMailer + composer autoload) is **committed** — no `composer install` needed.
- No `.env` file is used by the app; DB credentials are hardcoded in `koneksi.php`
  (`localhost`, user `root`, empty password, db `db_ut_assets`). Nothing to copy from the main checkout.
- Requires a running MySQL on `localhost:3306` with the `db_ut_assets` database
  (XAMPP's MySQL satisfies this; verify with `get_laporan_data.php`).

## 2. Run the server

From the worktree root (`23654ab6-16b0-42cd-9a8c-fa8c83303891`):

```bash
php -S 127.0.0.1:8090 -t .
```

Port: prefer `8090` (loopback). If taken, pick another free port in `8000-8100`
and update the registered preview URL accordingly. Log output goes to
`preview-23654ab6-16b0-42cd-9a8c-fa8c83303891.log` (redirect stdout/stderr when detaching).

Auth note: pages redirect to `login.html` unless `sessionStorage.isLoggedIn` is set
(client-side guard in `js/loader.js`); set it via the preview console before
opening the report page if you want to bypass login.
