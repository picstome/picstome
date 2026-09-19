#!/usr/bin/env bash
# Read-only health check for the Picstome verification instance.
# Prints DOCTOR OK when the app is worth driving; never mutates anything.

set -u
cd "$(dirname "$0")/../../../.." || exit 1

fail=0

status=$(curl -s -o /tmp/picstome-doctor-login.html -w '%{http_code}' --max-time 15 https://app.picstome.com.test/login)
if [ "$status" = "200" ] && grep -q 'wire:submit="login"' /tmp/picstome-doctor-login.html; then
    echo "OK   site: https://app.picstome.com.test/login -> 200 (login form present)"
else
    echo "FAIL site: /login -> HTTP $status (Herd down or app erroring)"
    fail=1
fi
rm -f /tmp/picstome-doctor-login.html

if [ -f public/build/manifest.json ]; then
    echo "OK   assets: public/build/manifest.json exists (run 'npm run build' if page CSS/JS 404s)"
else
    echo "FAIL assets: public/build/manifest.json missing — run 'npm run build'"
    fail=1
fi

db=$(php artisan tinker --execute="
    \$u = App\Models\User::where('email', 'test@example.com')->first();
    echo 'user ' . (\$u ? (\$u->email_verified_at ? 'present+verified' : 'present+UNVERIFIED') : 'MISSING');
    echo ' | users ' . App\Models\User::count();
    echo ' | galleries ' . App\Models\Gallery::count();
    echo ' | contracts ' . App\Models\Contract::count();
    echo ' | pending_jobs ' . DB::table('jobs')->count();
" 2>/dev/null | grep -o 'user .*')
if echo "$db" | grep -q 'user present+verified'; then
    echo "OK   db: $db"
else
    echo "FAIL db: [$db] — seeded login test@example.com must exist and be verified"
    fail=1
fi

mailpit=$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 http://127.0.0.1:8025/api/v1/messages)
if [ "$mailpit" = "200" ]; then
    echo "OK   mailpit: reachable at http://127.0.0.1:8025"
else
    echo "WARN mailpit: HTTP $mailpit at 127.0.0.1:8025 (email evidence unavailable; start Mailpit to prove mail flows)"
fi

if [ "$fail" = "0" ]; then
    echo "DOCTOR OK"
    exit 0
fi
echo "DOCTOR FAILED"
exit 1
