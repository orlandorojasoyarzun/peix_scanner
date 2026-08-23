# Railway process manifest.
#
# Railway inspects this file at build time and provisions one dyno per
# process declared here. Each `web`, `worker`, `scheduler` runs in its own
# container sharing the same image (and therefore the same code, .env,
# storage volume and Postgres/Redis links).
#
#   web       — the HTTP entry point. In production we use Laravel Octane
#               over FrankenPHP, which gives us a real async PHP runtime
#               with HTTP/1.1, HTTP/2 and HTTP/3, in a single process. The
#               classic `php artisan serve` is dev-only (single-threaded,
#               no keep-alive, no concurrency) and is NOT suitable for
#               production. Override with OCTANE_SERVER=frankenphp in env.
#   worker    — the queue worker. Same image, same code, but bound to a
#               different container so a slow AI callback never blocks
#               HTTP. `--tries=3` lets a flaky OpenRouter call recover
#               once or twice before going to the failed_jobs table.
#   scheduler — runs `schedule:work` which dispatches the registered
#               cron jobs (scans:purge, deep-clean, etc.) once per minute.
#               In Railway this is just another container with no public
#               port; the alternative is to use Railway's "Cron Job"
#               trigger, which is cleaner but requires extra setup.
web: php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=${PORT:-8000}
worker: php artisan queue:work --tries=3 --backoff=10 --timeout=180
scheduler: php artisan schedule:work
