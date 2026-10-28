# Warga Cakna — Production Deployment Runbook

Smart Update Manager is inactive. Production releases are deployed manually through a controlled cPanel Terminal session after localhost tests and GitHub synchronization have passed.

## 1. Release readiness

On localhost:

1. Run the targeted and full regression tests.
2. Run PHP lint, Laravel Pint, Blade compilation and `git diff --check`.
3. Review the complete staged diff and defensive secret scan.
4. Commit the approved release and push it normally to `origin/main`.
5. Record the approved Git commit hash. Never use a force push for a production release.

## 2. Inspect production before changing it

Open cPanel Terminal and run:

```bash
cd /home3/wargacakna/dana.wargacakna.org

git branch --show-current
git status --short --untracked-files=normal
git rev-parse HEAD
git fetch --no-tags origin main
git rev-parse origin/main
git merge-base --is-ancestor HEAD origin/main
git diff --name-status HEAD..origin/main
git diff --name-only HEAD..origin/main -- database/migrations
```

Stop if:

- the branch is not `main`;
- source/configuration changes are present locally;
- `origin/main` is not a fast-forward descendant;
- the target commit is not the approved release;
- protected files such as `.env`, private uploads or payment proofs appear in the target diff;
- `composer.json` or `composer.lock` changed without a separately reviewed dependency procedure.

Runtime files under `storage/framework`, `storage/logs` and `bootstrap/cache` are not release source. Do not stage, delete or overwrite private production data while resolving a dirty tree.

## 3. Create the code backup

Store backups outside the Git repository:

```bash
umask 077
BACKUP_ROOT=/home3/wargacakna/deployment-backups/dana.wargacakna.org
TIMESTAMP="$(date -u '+%Y%m%dT%H%M%SZ')"
CURRENT_COMMIT="$(git rev-parse HEAD)"

mkdir -p -- "$BACKUP_ROOT"
chmod 0700 "$BACKUP_ROOT"

CODE_BACKUP="$BACKUP_ROOT/code-$TIMESTAMP-${CURRENT_COMMIT:0:12}.tar.gz"
git archive --format=tar.gz --output="$CODE_BACKUP" "$CURRENT_COMMIT"
chmod 0600 "$CODE_BACKUP"
test -s "$CODE_BACKUP"
tar -tzf "$CODE_BACKUP" >/dev/null
sha256sum "$CODE_BACKUP"
```

## 4. Create and verify the database backup

Never place the database password directly in a command, terminal history or process argument. Create a temporary option file outside the repository:

```bash
DB_OPTION_FILE="$(mktemp "$HOME/.warga-cakna-mysql.XXXXXX.cnf")"
chmod 0600 "$DB_OPTION_FILE"
trap 'rm -f -- "$DB_OPTION_FILE"' EXIT

vi "$DB_OPTION_FILE"
```

Enter the production values interactively using this structure, then save and close the editor:

```ini
[client]
user=DATABASE_USER
password=DATABASE_PASSWORD
host=DATABASE_HOST
port=3306
```

Do not copy real values into this documentation. Run the backup using the known production database name:

```bash
DB_NAME=PRODUCTION_DATABASE_NAME
DB_BACKUP="$BACKUP_ROOT/database-$TIMESTAMP.sql"

/bin/mysqldump \
  --defaults-extra-file="$DB_OPTION_FILE" \
  --single-transaction \
  --quick \
  --skip-lock-tables \
  --no-tablespaces \
  --result-file="$DB_BACKUP" \
  "$DB_NAME"

DUMP_EXIT=$?
rm -f -- "$DB_OPTION_FILE"
trap - EXIT

test "$DUMP_EXIT" -eq 0
chmod 0600 "$DB_BACKUP"
test -s "$DB_BACKUP"
head -n 20 "$DB_BACKUP" | grep -aEq '^-- (MySQL|MariaDB) dump'
tail -n 50 "$DB_BACKUP" | grep -aEq '^-- Dump completed on .+$'
sha256sum "$DB_BACKUP"
```

Stop if any backup or verification command fails. Never treat a partial SQL file as a successful backup.

## 5. Install the exact approved commit

Enter maintenance mode only immediately before installation. The trap attempts to restore service if a later command fails:

```bash
set -e
php artisan down --retry=60 --no-interaction
trap 'php artisan up --no-interaction' EXIT

git pull --ff-only origin main
git rev-parse HEAD
```

Confirm that `HEAD` is the approved release commit.

## 6. Run migrations only when required

If the reviewed `HEAD..origin/main` diff contained no new migration, do not run a migration merely as a routine step.

When approved migrations exist, preview them first:

```bash
php artisan migrate --pretend --force --no-interaction
```

Review the complete SQL. Stop on destructive, unexpected or unrelated statements. After approval:

```bash
php artisan migrate --force --no-interaction
php artisan migrate:status --no-interaction
```

Never run an automatic destructive rollback. A failed or partially applied migration requires an explicit recovery review.

## 7. Clear caches and verify

```bash
php artisan optimize:clear --no-interaction
php artisan route:list --except-vendor
php artisan migrate:status --no-interaction
php artisan about --only=environment

git rev-parse HEAD
git rev-parse origin/main
git status --short --untracked-files=normal

php artisan up --no-interaction
trap - EXIT
```

Verify the public application, admin login, payment receipt flow, Billplz callback readiness, Email Log, Favourite Contacts, vouchers and acknowledgements. Confirm that local `HEAD` exactly matches the approved `origin/main` commit and record the backup paths and SHA-256 checksums in the deployment record.
