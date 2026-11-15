---
paths:
  - '**'
---

# General

## Update repo, create one branch, point dev at devel.picstome.com
Before any development: (1) update the repository (git fetch origin --prune, checkout main, git pull --ff-only); (2) create ONE unique branch from the updated main for that task (feat/…, fix/…, chore/…), in the main working copy /root/projects/picstome — never a git worktree unless the maintainer explicitly asks; (3) ensure .env has APP_URL and ASSET_URL set to https://devel.picstome.com and that the local web server serves this working copy, so the maintainer can test the branch at devel.picstome.com; (4) do not commit, push, open a PR or deploy without asking the maintainer first.
