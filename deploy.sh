#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'
umask 077
export PATH='/usr/local/cpanel/3rdparty/lib/path-bin:/usr/local/bin:/usr/bin:/bin'
export GIT_CONFIG_COUNT='1'
export GIT_CONFIG_KEY_0='core.hooksPath'
export GIT_CONFIG_VALUE_0='/dev/null'
unset MYSQL_PWD MYSQL_HOME

readonly EXPECTED_BRANCH='main'
readonly REMOTE_NAME='origin'
readonly REMOTE_REF='refs/remotes/origin/main'
readonly MYSQLDUMP_BINARY='/bin/mysqldump'
readonly MINIMUM_FREE_KB='262144'

MODE='deploy'
if (( $# > 1 )); then
    printf 'Usage: bash deploy.sh [--self-check|--dry-run]\n' >&2
    exit 64
fi
if (( $# == 1 )); then
    case "$1" in
        --self-check) MODE='self-check' ;;
        --dry-run) MODE='dry-run' ;;
        *)
            printf 'Usage: bash deploy.sh [--self-check|--dry-run]\n' >&2
            exit 64
            ;;
    esac
fi

SCRIPT_DIRECTORY="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
APP_ROOT="$SCRIPT_DIRECTORY"
if [[ "$(pwd -P)" != "$APP_ROOT" ]]; then
    printf 'ERROR: Run this script from its application directory.\n' >&2
    exit 1
fi
if [[ ! -d "$APP_ROOT/.git" || ! -f "$APP_ROOT/artisan" || ! -f "$APP_ROOT/composer.json" ]]; then
    printf 'ERROR: The script is not inside the expected Laravel Git repository.\n' >&2
    exit 1
fi

DEPLOYMENT_ID="$(date -u '+%Y%m%dT%H%M%SZ')-$$"
STARTED_AT="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
FINISHED_AT=''
STATUS='failed'
FAILURE_CODE='unexpected_failure'
CURRENT_COMMIT=''
TARGET_COMMIT=''
INSTALLED_COMMIT=''
CODE_BACKUP_PATH=''
CODE_BACKUP_CHECKSUM=''
DATABASE_BACKUP_PATH=''
DATABASE_BACKUP_CHECKSUM=''
MAINTENANCE_ENTERED='false'
MAINTENANCE_ACTIVE='false'

HISTORY_DIRECTORY="$APP_ROOT/storage/app/private/update-deployments/history"
RUNTIME_DIRECTORY="$APP_ROOT/storage/app/private/update-deployments/runtime/$DEPLOYMENT_ID"
DATABASE_OPTION_FILE="$RUNTIME_DIRECTORY/mysql.cnf"
DATABASE_NAME_FILE="$RUNTIME_DIRECTORY/database-name"
LOCK_DIRECTORY="$APP_ROOT/storage/app/private/update-deployments/deploy.lock"
LOCK_ACQUIRED='false'
BACKUP_ROOT="$(dirname -- "$APP_ROOT")/deployment-backups/$(basename -- "$APP_ROOT")"

if ! mkdir -p -- "$HISTORY_DIRECTORY" || ! chmod 0700 "$HISTORY_DIRECTORY"; then
    printf 'ERROR: Private deployment history directory cannot be secured.\n' >&2
    exit 1
fi
PRIVATE_STORAGE_ROOT="$(cd -- "$APP_ROOT/storage/app/private" && pwd -P)"
RESOLVED_HISTORY_DIRECTORY="$(cd -- "$HISTORY_DIRECTORY" && pwd -P)"
if [[ -L "$HISTORY_DIRECTORY" || "$RESOLVED_HISTORY_DIRECTORY" != "$PRIVATE_STORAGE_ROOT/"* ]]; then
    printf 'ERROR: Deployment history directory must remain inside private storage.\n' >&2
    exit 1
fi

log() {
    printf '[%s] %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$1"
}

fail() {
    FAILURE_CODE="$1"
    STATUS='failed'
    printf 'ERROR [%s]: %s\n' "$1" "$2" >&2
    exit 1
}

json_escape() {
    local value="$1"
    value=${value//\\/\\\\}
    value=${value//\"/\\\"}
    value=${value//$'\r'/\\r}
    value=${value//$'\n'/\\n}
    value=${value//$'\t'/\\t}
    printf '%s' "$value"
}

write_history() {
    local temporary="$HISTORY_DIRECTORY/$DEPLOYMENT_ID.json.tmp"
    local destination="$HISTORY_DIRECTORY/$DEPLOYMENT_ID.json"

    printf '{\n' > "$temporary"
    printf '  "schema": 1,\n' >> "$temporary"
    printf '  "deployment_id": "%s",\n' "$(json_escape "$DEPLOYMENT_ID")" >> "$temporary"
    printf '  "mode": "%s",\n' "$(json_escape "$MODE")" >> "$temporary"
    printf '  "status": "%s",\n' "$(json_escape "$STATUS")" >> "$temporary"
    printf '  "started_at": "%s",\n' "$(json_escape "$STARTED_AT")" >> "$temporary"
    printf '  "finished_at": "%s",\n' "$(json_escape "$FINISHED_AT")" >> "$temporary"
    printf '  "current_commit": "%s",\n' "$(json_escape "$CURRENT_COMMIT")" >> "$temporary"
    printf '  "target_commit": "%s",\n' "$(json_escape "$TARGET_COMMIT")" >> "$temporary"
    printf '  "installed_commit": "%s",\n' "$(json_escape "$INSTALLED_COMMIT")" >> "$temporary"
    printf '  "code_backup_path": "%s",\n' "$(json_escape "$CODE_BACKUP_PATH")" >> "$temporary"
    printf '  "code_backup_checksum": "%s",\n' "$(json_escape "$CODE_BACKUP_CHECKSUM")" >> "$temporary"
    printf '  "database_backup_path": "%s",\n' "$(json_escape "$DATABASE_BACKUP_PATH")" >> "$temporary"
    printf '  "database_backup_checksum": "%s",\n' "$(json_escape "$DATABASE_BACKUP_CHECKSUM")" >> "$temporary"
    printf '  "maintenance_entered": %s,\n' "$MAINTENANCE_ENTERED" >> "$temporary"
    printf '  "failure_code": "%s"\n' "$(json_escape "$FAILURE_CODE")" >> "$temporary"
    printf '}\n' >> "$temporary"
    chmod 0600 "$temporary" 2>/dev/null || true
    mv -f -- "$temporary" "$destination"
}

cleanup_credentials() {
    rm -f -- "$DATABASE_OPTION_FILE" "$DATABASE_NAME_FILE"
    rmdir -- "$RUNTIME_DIRECTORY" 2>/dev/null || true
}

cleanup_lock() {
    if [[ "$LOCK_ACQUIRED" == 'true' ]]; then
        rmdir -- "$LOCK_DIRECTORY" 2>/dev/null || true
        LOCK_ACQUIRED='false'
    fi
}

on_exit() {
    local exit_code=$?
    trap - EXIT INT TERM HUP
    set +e

    cleanup_credentials
    if [[ "$MAINTENANCE_ACTIVE" == 'true' ]]; then
        log 'Attempting to leave Laravel maintenance mode.'
        if "$PHP_BINARY" artisan up --no-interaction; then
            MAINTENANCE_ACTIVE='false'
        elif (( exit_code == 0 )); then
            exit_code=1
            STATUS='failed'
            FAILURE_CODE='maintenance_exit_failed'
        fi
    fi
    cleanup_lock

    if (( exit_code != 0 )) && [[ "$STATUS" != 'failed' ]]; then
        STATUS='failed'
        FAILURE_CODE='unexpected_failure'
    fi
    FINISHED_AT="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
    if ! write_history; then
        printf 'ERROR: Deployment history could not be written.\n' >&2
        (( exit_code == 0 )) && exit_code=1
    fi
    exit "$exit_code"
}

trap on_exit EXIT
trap 'FAILURE_CODE="interrupted"; STATUS="failed"; exit 130' INT TERM HUP

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail 'missing_command' "Required command is unavailable: $1"
}

is_runtime_path() {
    case "$1" in
        storage/framework/*|storage/logs/*|bootstrap/cache/*) return 0 ;;
        *) return 1 ;;
    esac
}

assert_clean_source() {
    local -a paths=()
    local -a dirty=()
    local path

    mapfile -d '' -t paths < <("$GIT_BINARY" diff --name-only -z --no-ext-diff)
    for path in "${paths[@]}"; do
        is_runtime_path "$path" || dirty+=("$path")
    done

    paths=()
    mapfile -d '' -t paths < <("$GIT_BINARY" diff --cached --name-only -z --no-ext-diff)
    for path in "${paths[@]}"; do
        is_runtime_path "$path" || dirty+=("$path")
    done

    paths=()
    mapfile -d '' -t paths < <("$GIT_BINARY" ls-files --others --exclude-standard -z)
    for path in "${paths[@]}"; do
        is_runtime_path "$path" || dirty+=("$path")
    done

    # A malicious or accidental .gitignore rule must not hide new source or
    # configuration files from the deployment cleanliness check.
    paths=()
    mapfile -d '' -t paths < <("$GIT_BINARY" ls-files --others --ignored --exclude-standard -z -- app config database resources routes deploy.sh)
    for path in "${paths[@]}"; do
        is_runtime_path "$path" || dirty+=("$path")
    done

    if (( ${#dirty[@]} > 0 )); then
        printf 'Blocked source paths:\n' >&2
        printf '  %q\n' "${dirty[@]}" >&2
        fail 'dirty_working_tree' 'Source working tree is not clean.'
    fi
}

assert_safe_target_paths() {
    local -a paths=()
    local path lower
    mapfile -d '' -t paths < <("$GIT_BINARY" diff --name-only -z "$CURRENT_COMMIT" "$TARGET_COMMIT")

    for path in "${paths[@]}"; do
        lower=${path,,}
        if [[ "$path" == '.env' || ( "$path" == .env.* && "$path" != '.env.example' ) ]]; then
            fail 'protected_target_path' 'The target changes an environment file.'
        fi
        case "$path" in
            auth.json|.gitmodules|storage/*|public/storage/*|uploads/*|*/uploads/*)
                fail 'protected_target_path' 'The target changes a protected runtime or upload path.'
                ;;
        esac
        case "$lower" in
            *credential*|*private-key*|*payment-proof*|*secret*)
                fail 'protected_target_path' 'The target changes a protected private-data path.'
                ;;
        esac
    done

    if [[ -n "$("$GIT_BINARY" diff --name-only "$CURRENT_COMMIT" "$TARGET_COMMIT" -- composer.json composer.lock)" ]]; then
        fail 'dependency_change' 'Composer dependency changes require a separate reviewed deployment process.'
    fi
}

assert_disk_space() {
    local path="$1"
    local line available filesystem blocks used capacity mountpoint
    line="$(df -Pk -- "$path" | tail -n 1)" || fail 'disk_space_unknown' 'Available disk space could not be read.'
    IFS=' ' read -r filesystem blocks used available capacity mountpoint <<< "$line"
    [[ "$available" =~ ^[0-9]+$ ]] || fail 'disk_space_unknown' 'Available disk space could not be parsed.'
    (( available >= MINIMUM_FREE_KB )) || fail 'insufficient_disk_space' 'At least 256 MiB of free disk space is required.'
}

checksum_file() {
    local output checksum
    output="$(sha256sum -- "$1")" || return 1
    checksum=${output%% *}
    [[ "$checksum" =~ ^[a-f0-9]{64}$ ]] || return 1
    printf '%s' "$checksum"
}

reject_code_backup() {
    rm -f -- "$CODE_BACKUP_PATH"
    CODE_BACKUP_PATH=''
    fail "$1" "$2"
}

reject_database_backup() {
    rm -f -- "$DATABASE_BACKUP_PATH"
    DATABASE_BACKUP_PATH=''
    fail "$1" "$2"
}

prepare_database_credentials() {
    "$PHP_BINARY" artisan updates:write-database-options "$DEPLOYMENT_ID" --no-interaction \
        || fail 'database_configuration_failed' 'Private database backup options could not be prepared.'
    [[ -f "$DATABASE_OPTION_FILE" && ! -L "$DATABASE_OPTION_FILE" ]] \
        || fail 'database_configuration_failed' 'Private database option file was not created.'
    [[ -f "$DATABASE_NAME_FILE" && ! -L "$DATABASE_NAME_FILE" ]] \
        || fail 'database_configuration_failed' 'Private database name file was not created.'
    if [[ "$(stat -c '%a' "$DATABASE_OPTION_FILE")" != '600' ]]; then
        fail 'database_configuration_permissions' 'Private database option file permissions are not 0600.'
    fi
}

for command in git php tar gzip sha256sum date df grep head tail stat mkdir chmod mv rm rmdir dirname basename; do
    require_command "$command"
done

GIT_BINARY="$(command -v git)"
PHP_BINARY="$(command -v php)"
readonly GIT_BINARY PHP_BINARY

if ! mkdir -- "$LOCK_DIRECTORY" 2>/dev/null; then
    fail 'deployment_locked' 'Another deployment or deployment check is already running.'
fi
LOCK_ACQUIRED='true'

[[ -x "$MYSQLDUMP_BINARY" ]] || fail 'mysqldump_unavailable' 'The fixed /bin/mysqldump binary is unavailable or not executable.'
"$MYSQLDUMP_BINARY" --version >/dev/null 2>&1 || fail 'mysqldump_unavailable' 'mysqldump version check failed.'

REPOSITORY_ROOT="$("$GIT_BINARY" rev-parse --show-toplevel 2>/dev/null)" \
    || fail 'invalid_repository' 'The application directory is not a Git work tree.'
REPOSITORY_ROOT="$(cd -- "$REPOSITORY_ROOT" && pwd -P)"
[[ "$REPOSITORY_ROOT" == "$APP_ROOT" ]] || fail 'invalid_repository' 'Git repository root does not match the application directory.'

BRANCH="$("$GIT_BINARY" symbolic-ref --quiet --short HEAD 2>/dev/null)" \
    || fail 'detached_head' 'The repository must not use a detached HEAD.'
[[ "$BRANCH" == "$EXPECTED_BRANCH" ]] || fail 'wrong_branch' 'The current branch must be main.'

REMOTE_URL="$("$GIT_BINARY" remote get-url "$REMOTE_NAME" 2>/dev/null)" \
    || fail 'missing_remote' 'The origin remote is unavailable.'
if [[ ! "$REMOTE_URL" =~ ^git@github\.com: && ! "$REMOTE_URL" =~ ^https://github\.com/ && ! "$REMOTE_URL" =~ ^ssh://git@github\.com/ ]]; then
    fail 'unapproved_remote' 'The origin remote must be an approved GitHub URL.'
fi
unset REMOTE_URL

[[ -f "$APP_ROOT/.env" && ! -L "$APP_ROOT/.env" ]] || fail 'environment_file_invalid' 'The production .env must be a regular private file.'
assert_clean_source

"$PHP_BINARY" artisan help updates:write-database-options --no-interaction >/dev/null \
    || fail 'deployment_command_missing' 'Database option command is unavailable.'
"$PHP_BINARY" artisan help updates:post-deploy-check --no-interaction >/dev/null \
    || fail 'deployment_command_missing' 'Post-deployment check command is unavailable.'

if [[ "$MODE" == 'self-check' ]]; then
    STATUS='self_check_passed'
    FAILURE_CODE=''
    log 'Self-check passed. No fetch, backup, merge or migration was performed.'
    exit 0
fi

CURRENT_COMMIT="$("$GIT_BINARY" rev-parse --verify 'HEAD^{commit}')" \
    || fail 'current_commit_invalid' 'Current commit cannot be resolved.'
[[ "$CURRENT_COMMIT" =~ ^[a-f0-9]{40}$ ]] || fail 'current_commit_invalid' 'Current commit is invalid.'

log 'Fetching the controlled origin/main target.'
"$GIT_BINARY" fetch --no-tags "$REMOTE_NAME" "$EXPECTED_BRANCH" \
    || fail 'fetch_failed' 'GitHub fetch failed.'
TARGET_COMMIT="$("$GIT_BINARY" rev-parse --verify 'FETCH_HEAD^{commit}')" \
    || fail 'target_commit_invalid' 'Fetched target cannot be resolved.'
REMOTE_TARGET="$("$GIT_BINARY" rev-parse --verify "$REMOTE_REF^{commit}")" \
    || fail 'target_commit_invalid' 'origin/main cannot be resolved.'
[[ "$TARGET_COMMIT" =~ ^[a-f0-9]{40}$ && "$TARGET_COMMIT" == "$REMOTE_TARGET" ]] \
    || fail 'target_commit_mismatch' 'Fetched target does not match origin/main.'

if [[ "$CURRENT_COMMIT" == "$TARGET_COMMIT" ]]; then
    STATUS='no_update'
    FAILURE_CODE=''
    INSTALLED_COMMIT="$CURRENT_COMMIT"
    log 'No update is available.'
    exit 0
fi

"$GIT_BINARY" merge-base --is-ancestor "$CURRENT_COMMIT" "$TARGET_COMMIT" \
    || fail 'non_fast_forward_target' 'The target is not a fast-forward descendant of the current commit.'
assert_safe_target_paths
assert_clean_source

assert_disk_space "$APP_ROOT"
assert_disk_space "$(dirname -- "$APP_ROOT")"

prepare_database_credentials
IFS= read -r DATABASE_NAME < "$DATABASE_NAME_FILE" \
    || fail 'database_configuration_failed' 'Database name could not be read.'
[[ "$DATABASE_NAME" =~ ^[A-Za-z0-9_]+$ ]] \
    || fail 'database_configuration_failed' 'Database name is not deployment-safe.'

if [[ "$MODE" == 'dry-run' ]]; then
    cleanup_credentials
    STATUS='dry_run_passed'
    FAILURE_CODE=''
    log 'Dry run passed. No backup, maintenance mode, merge or migration was performed.'
    exit 0
fi

mkdir -p -- "$BACKUP_ROOT"
chmod 0700 "$BACKUP_ROOT" \
    || fail 'backup_location_permissions' 'The backup directory cannot be restricted to mode 0700.'
RESOLVED_BACKUP_ROOT="$(cd -- "$BACKUP_ROOT" && pwd -P)"
if [[ "$RESOLVED_BACKUP_ROOT" == "$APP_ROOT" || "$RESOLVED_BACKUP_ROOT" == "$APP_ROOT/"* ]]; then
    fail 'backup_location_invalid' 'The backup directory must be outside the repository.'
fi
assert_disk_space "$RESOLVED_BACKUP_ROOT"

SHORT_COMMIT="${CURRENT_COMMIT:0:12}"
CODE_BACKUP_PATH="$RESOLVED_BACKUP_ROOT/code-$DEPLOYMENT_ID-$SHORT_COMMIT.tar.gz"
DATABASE_BACKUP_PATH="$RESOLVED_BACKUP_ROOT/database-$DEPLOYMENT_ID-$SHORT_COMMIT.sql"

log 'Creating a committed-code archive outside the repository.'
"$GIT_BINARY" archive --format=tar.gz --output="$CODE_BACKUP_PATH" "$CURRENT_COMMIT" \
    || reject_code_backup 'code_backup_failed' 'Code backup could not be created.'
chmod 0600 "$CODE_BACKUP_PATH" \
    || reject_code_backup 'code_backup_permissions' 'Code backup permissions cannot be restricted to mode 0600.'
[[ -s "$CODE_BACKUP_PATH" ]] || reject_code_backup 'code_backup_empty' 'Code backup is empty.'
tar -tzf "$CODE_BACKUP_PATH" >/dev/null \
    || reject_code_backup 'code_backup_invalid' 'Code backup integrity check failed.'
CODE_BACKUP_CHECKSUM="$(checksum_file "$CODE_BACKUP_PATH")" \
    || reject_code_backup 'code_backup_checksum_failed' 'Code backup checksum failed.'

log 'Creating the verified MySQL backup.'
set +e
"$MYSQLDUMP_BINARY" \
    --defaults-extra-file="$DATABASE_OPTION_FILE" \
    --single-transaction \
    --quick \
    --skip-lock-tables \
    --no-tablespaces \
    --result-file="$DATABASE_BACKUP_PATH" \
    "$DATABASE_NAME"
MYSQLDUMP_EXIT=$?
set -e
cleanup_credentials

(( MYSQLDUMP_EXIT == 0 )) || reject_database_backup 'database_backup_failed' 'mysqldump exited unsuccessfully.'
chmod 0600 "$DATABASE_BACKUP_PATH" \
    || reject_database_backup 'database_backup_permissions' 'Database backup permissions cannot be restricted to mode 0600.'
[[ -s "$DATABASE_BACKUP_PATH" ]] || reject_database_backup 'database_backup_empty' 'Database backup is empty.'
head -n 20 "$DATABASE_BACKUP_PATH" | grep -aEq '^-- (MySQL|MariaDB) dump' \
    || reject_database_backup 'database_backup_invalid' 'Database backup header is invalid.'
grep -a -m 1 -q ';' "$DATABASE_BACKUP_PATH" \
    || reject_database_backup 'database_backup_invalid' 'Database backup contains no SQL statements.'
tail -n 50 "$DATABASE_BACKUP_PATH" | grep -aEq '^-- Dump completed on .+$' \
    || reject_database_backup 'database_backup_incomplete' 'Database backup completion marker is missing.'
DATABASE_BACKUP_CHECKSUM="$(checksum_file "$DATABASE_BACKUP_PATH")" \
    || reject_database_backup 'database_backup_checksum_failed' 'Database backup checksum failed.'

assert_clean_source
[[ "$("$GIT_BINARY" rev-parse HEAD)" == "$CURRENT_COMMIT" ]] \
    || fail 'head_changed' 'HEAD changed after preflight.'

log 'Entering Laravel maintenance mode.'
MAINTENANCE_ACTIVE='true'
"$PHP_BINARY" artisan down --retry=60 --no-interaction \
    || fail 'maintenance_entry_failed' 'Laravel maintenance mode could not be enabled.'
MAINTENANCE_ENTERED='true'

log 'Fast-forwarding to the exact fetched target.'
"$GIT_BINARY" merge --ff-only "$TARGET_COMMIT" \
    || fail 'merge_failed' 'Fast-forward merge failed. No reset or rollback was attempted.'
[[ "$("$GIT_BINARY" rev-parse HEAD)" == "$TARGET_COMMIT" ]] \
    || fail 'installed_commit_mismatch' 'Installed HEAD does not match the fetched target.'
INSTALLED_COMMIT="$TARGET_COMMIT"

log 'Running approved Laravel migrations.'
"$PHP_BINARY" artisan migrate --force --no-interaction \
    || fail 'migration_failed' 'Laravel migration failed. No destructive rollback was attempted.'

log 'Clearing and rebuilding Laravel caches.'
"$PHP_BINARY" artisan optimize:clear --no-interaction \
    || fail 'cache_clear_failed' 'Laravel cache clear failed.'
"$PHP_BINARY" artisan config:cache --no-interaction \
    || fail 'config_cache_failed' 'Laravel configuration cache rebuild failed.'
"$PHP_BINARY" artisan route:cache --no-interaction \
    || fail 'route_cache_failed' 'Laravel route cache rebuild failed.'
"$PHP_BINARY" artisan view:cache --no-interaction \
    || fail 'view_cache_failed' 'Laravel view cache rebuild failed.'

log 'Running critical post-deployment checks.'
"$PHP_BINARY" artisan updates:post-deploy-check --no-interaction \
    || fail 'post_deploy_check_failed' 'Critical post-deployment checks failed.'
[[ "$("$GIT_BINARY" rev-parse HEAD)" == "$TARGET_COMMIT" ]] \
    || fail 'installed_commit_mismatch' 'Post-install HEAD no longer matches the fetched target.'
assert_clean_source

log 'Leaving Laravel maintenance mode.'
"$PHP_BINARY" artisan up --no-interaction \
    || fail 'maintenance_exit_failed' 'Laravel maintenance mode could not be disabled.'
MAINTENANCE_ACTIVE='false'

STATUS='successful'
FAILURE_CODE=''
log "Deployment completed successfully at $TARGET_COMMIT."
