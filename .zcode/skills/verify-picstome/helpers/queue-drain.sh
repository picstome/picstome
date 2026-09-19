#!/usr/bin/env bash
# Runs queued jobs (photo processing, PDFs, emails, disk deletions) until the
# queue is empty, then exits. Self-terminating; safe to re-run.

set -u
cd "$(dirname "$0")/../../../.." || exit 1

php artisan queue:work --stop-when-empty --max-time=120 --sleep=0
