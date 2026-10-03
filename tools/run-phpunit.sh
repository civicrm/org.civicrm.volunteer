#!/usr/bin/env bash
#
# Run the CiviVolunteer headless PHPUnit suite from any codebase.
#
# The extension is self-contained and directory-agnostic: it may sit wherever
# the codebase's civicrm.settings.php configures its extensions directory —
# inside or outside the codebase tree. Every machine-specific input is a
# dedicated CIVIVOLUNTEER_TEST_* variable, read from an .env file or the
# environment. Provide the credentials and run:
#
#   tools/run-phpunit.sh                     # whole suite
#   tools/run-phpunit.sh --filter SomeTest   # one test
#
# User-facing variables (env wins over .env):
#   CIVIVOLUNTEER_TEST_DB_USER       required. MySQL account for the test DB.
#   CIVIVOLUNTEER_TEST_DB_PASSWORD   required.
#   CIVIVOLUNTEER_TEST_DB_HOST       default 127.0.0.1 (TCP).
#   CIVIVOLUNTEER_TEST_DB_PORT       default 3306.
#   CIVIVOLUNTEER_TEST_DB            disposable database name;
#                                    default civivolunteer_phpunit. Must begin
#                                    with civivolunteer_test or civivolunteer_phpunit.
#   CIVIVOLUNTEER_TEST_SEED          mysqldump for seeding a missing database;
#                                    default <extension>/tests/phpunit/seed/civicrm-seed.sql.
#   CIVIVOLUNTEER_TEST_SITE_SETTINGS path to the codebase's civicrm.settings.php;
#                                    default: upward search from the working
#                                    directory, then from the extension, over
#                                    the standard Drupal/Backdrop/WordPress
#                                    layouts.
#   CIVICRM_CV                       cv binary; default: vendor/bin/cv found
#                                    above the working directory or the
#                                    extension, then `cv` on PATH.
#   CIVIVOLUNTEER_TEST_ENV_FILE      env file to source; default: nearest .env
#                                    above the working directory, then the
#                                    extension. Not required — exported
#                                    variables are enough.
#   PHPUNIT9_BIN                     explicit PHPUnit 9.6 binary or PHAR.
#
# The runner then exports the internal CIVICRM_DSN / CIVICRM_TEST_DB /
# CIVICRM_CV variables that tests/phpunit/bootstrap.php validates, seeds the
# disposable database if needed, resolves a PHPUnit 9 binary (never the
# codebase's vendor/bin/phpunit — see README.md, "Why a separate PHPUnit
# binary"), and applies the CIVI_SMTP_OUTBOUND_OPTION=2 workaround.
#
# Full documentation: tests/README.md in this extension; README.md in the
# extension root is the portable summary.

set -uo pipefail

# Abort with an actionable message. Deliberately not `set -e`: several guards
# below are `[ ... ] && [ ... ] && fail`, whose whole chain returns non-zero on
# the *success* path, which errexit would treat as a fatal error.
fail() {
  printf 'run-phpunit: %s\n' "$*" >&2
  exit 1
}

EXT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"

# Directory-agnostic discovery. No codebase-root assumption: the extension may
# live anywhere (its location is whatever civicrm.settings.php's extensionsDir
# points at), so candidates are searched upward from the working directory and
# then from the extension itself, and every resolution can be pinned by an
# environment variable.

# Print the first ancestor of $1 containing any of the exact paths given.
find_up() {
  find_dir="$1"; shift
  while [ "$find_dir" != "/" ]; do
    for find_rel in "$@"; do
      if [ -e "$find_dir/$find_rel" ]; then
        printf '%s/%s\n' "$find_dir" "$find_rel"
        return 0
      fi
    done
    find_dir="$(dirname -- "$find_dir")"
  done
  return 1
}

# Print the first ancestor of $1 containing a regular file matching any glob.
find_up_glob() {
  find_dir="$1"; shift
  while [ "$find_dir" != "/" ]; do
    for find_pat in "$@"; do
      for find_f in "$find_dir/$find_pat"; do
        if [ -f "$find_f" ]; then
          printf '%s\n' "$find_f"
          return 0
        fi
      done
    done
    find_dir="$(dirname -- "$find_dir")"
  done
  return 1
}

# cv binary: explicit, then the codebase's own composer-installed vendor/bin/cv
# (searched above the working directory, then above the extension), and only
# then whatever `cv` is on PATH. The codebase's cv is preferred deliberately:
# it is the version installed against this CiviCRM, whereas a cv on PATH is
# global and may be built for a different core release.
resolve_cv() {
  if [ -n "${CIVICRM_CV:-}" ]; then
    printf '%s\n' "$CIVICRM_CV"
  elif find_up "$PWD" vendor/bin/cv; then :
  elif find_up "$EXT_DIR" vendor/bin/cv; then :
  else
    command -v cv
  fi
}

# The codebase's real civicrm.settings.php: explicit, then standard layouts
# searched upward from the working directory, then the extension. Patterns
# cover Drupal (composer web/ and classic docroot), Backdrop, WordPress
# (modern uploads/ and legacy plugin dir), Joomla (com_civicrm component),
# and Standalone (project root).
resolve_site_settings() {
  if [ -n "${CIVIVOLUNTEER_TEST_SITE_SETTINGS:-}" ]; then
    printf '%s\n' "$CIVIVOLUNTEER_TEST_SITE_SETTINGS"
  elif find_up "$PWD" \
      web/sites/default/civicrm.settings.php \
      sites/default/civicrm.settings.php \
      civicrm.settings.php; then :
  elif find_up "$EXT_DIR" \
      web/sites/default/civicrm.settings.php \
      sites/default/civicrm.settings.php \
      civicrm.settings.php; then :
  elif find_up_glob "$PWD" \
      'web/sites/*/civicrm.settings.php' \
      'sites/*/civicrm.settings.php' \
      'wp-content/uploads/civicrm/civicrm.settings.php' \
      'wp-content/plugins/civicrm/civicrm.settings.php' \
      'administrator/components/com_civicrm/civicrm.settings.php'; then :
  else
    find_up_glob "$EXT_DIR" \
      'web/sites/*/civicrm.settings.php' \
      'sites/*/civicrm.settings.php' \
      'wp-content/uploads/civicrm/civicrm.settings.php' \
      'wp-content/plugins/civicrm/civicrm.settings.php' \
      'administrator/components/com_civicrm/civicrm.settings.php'
  fi
}

ENV_FILE="${CIVIVOLUNTEER_TEST_ENV_FILE:-$(find_up "$PWD" .env || find_up "$EXT_DIR" .env || true)}"

# Caller-supplied overrides must survive sourcing .env.
DB_NAME_OVERRIDE="${CIVIVOLUNTEER_TEST_DB:-}"
SEED_OVERRIDE="${CIVIVOLUNTEER_TEST_SEED:-}"
PHPUNIT_OVERRIDE="${PHPUNIT9_BIN:-}"

if [ -f "$ENV_FILE" ]; then
  set -a
  # shellcheck disable=SC1090
  . "$ENV_FILE"
  set +a
fi

DB_NAME="${DB_NAME_OVERRIDE:-${CIVIVOLUNTEER_TEST_DB:-civivolunteer_phpunit}}"
DB_SEED="${SEED_OVERRIDE:-${CIVIVOLUNTEER_TEST_SEED:-$EXT_DIR/tests/phpunit/seed/civicrm-seed.sql}}"
DB_USER="${CIVIVOLUNTEER_TEST_DB_USER:-}"
DB_PASS="${CIVIVOLUNTEER_TEST_DB_PASSWORD:-}"
DB_HOST="${CIVIVOLUNTEER_TEST_DB_HOST:-127.0.0.1}"
DB_PORT="${CIVIVOLUNTEER_TEST_DB_PORT:-3306}"
# "localhost" makes PDO use the unix socket and ignore the port; force TCP.
[ "$DB_HOST" = "localhost" ] && DB_HOST="127.0.0.1"

[ -n "$DB_USER" ] || fail "CIVIVOLUNTEER_TEST_DB_USER is not set. Add the CIVIVOLUNTEER_TEST_DB_* variables to $ENV_FILE (or export them). See the extension README."
[ -n "$DB_PASS" ] || fail "CIVIVOLUNTEER_TEST_DB_PASSWORD is not set. Add the CIVIVOLUNTEER_TEST_DB_* variables to $ENV_FILE (or export them)."

# Mirror the guards in tests/phpunit/bootstrap.php so misconfiguration fails
# here, with an actionable message, instead of inside the bootstrap.
case "$DB_NAME" in
  civivolunteer_test|civivolunteer_test_*|civivolunteer_phpunit|civivolunteer_phpunit_*) ;;
  *) fail "CIVIVOLUNTEER_TEST_DB ('$DB_NAME') must begin with 'civivolunteer_test' or 'civivolunteer_phpunit'." ;;
esac
[ -n "${CIVICRM_DATABASE:-}" ] && [ "$DB_NAME" = "$CIVICRM_DATABASE" ] \
  && fail "CIVIVOLUNTEER_TEST_DB must not equal CIVICRM_DATABASE ('$DB_NAME'); the suite drops and rebuilds it."
command -v mysql >/dev/null 2>&1 || fail "mysql client not found on PATH."
command -v php  >/dev/null 2>&1 || fail "php CLI not found on PATH."
php "$EXT_DIR/tools/check-php-test-discovery.php" "$EXT_DIR/tests/phpunit" \
  || fail "PHP test discovery source check failed."

export MYSQL_PWD="$DB_PASS"
MYSQL=(mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" --batch --skip-column-names)

# Seed the disposable database if it does not exist or holds no CiviCRM schema.
# The headless installer replaces all contents on every run, so any bootable
# CiviCRM 6.16 schema is an acceptable seed.
if ! "${MYSQL[@]}" -N -e "SELECT 1 FROM information_schema.schemata WHERE schema_name = '$DB_NAME';" | grep -q 1 \
   || ! "${MYSQL[@]}" -N -e "SELECT 1 FROM information_schema.tables WHERE table_schema = '$DB_NAME' AND table_name = 'civicrm_domain';" | grep -q 1; then
  [ -f "$DB_SEED" ] || fail "test database '$DB_NAME' is missing or empty, and no seed dump exists at
  $DB_SEED
Create one from any bootable CiviCRM 6.16 database, for example:
  mkdir -p \"\$(dirname \"\$DB_SEED\")\"
  mysqldump -h $DB_HOST -P $DB_PORT -u $DB_USER -p <some_civicrm_db> > $DB_SEED
or point CIVIVOLUNTEER_TEST_SEED at an existing dump. See .env.example and the README."
  echo "run-phpunit: creating and seeding $DB_NAME from $DB_SEED" >&2
  "${MYSQL[@]}" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  "${MYSQL[@]}" "$DB_NAME" < "$DB_SEED"
fi
unset MYSQL_PWD

# rawurlencode the password: it may contain characters that corrupt a DSN.
DB_PASS_ENC="$(DB_PASS="$DB_PASS" php -r 'echo rawurlencode(getenv("DB_PASS"));')"
export CIVICRM_DSN="mysql://$DB_USER:$DB_PASS_ENC@$DB_HOST:$DB_PORT/$DB_NAME?new_link=true"
export CIVICRM_TEST_DB="$DB_NAME"
CV_BIN="$(resolve_cv)" || fail "cv not found. Install it into the codebase (composer require civicrm/cv), put a PHAR on PATH, or set CIVICRM_CV."
[ -x "$CV_BIN" ] || fail "cv at '$CV_BIN' is not executable."
SITE_SETTINGS="$(resolve_site_settings)" || fail "the codebase's civicrm.settings.php was not found in any standard location. Set CIVIVOLUNTEER_TEST_SITE_SETTINGS to its path."
[ -f "$SITE_SETTINGS" ] || fail "civicrm.settings.php at '$SITE_SETTINGS' (from CIVIVOLUNTEER_TEST_SITE_SETTINGS or discovery) does not exist."
export CIVICRM_CV="$CV_BIN"
# Used by tests/phpunit/test-settings.php to locate the codebase's settings.
export CIVIVOLUNTEER_TEST_SITE_SETTINGS="$SITE_SETTINGS"

# civicrm.settings.php may pin mailing_backend from CIVI_SMTP_* env without a
# guard; CRM_Utils_Mail reads that key unguarded
# (VolunteerUtil.getSupportingData -> can_send_email), which fatals as an
# "Undefined array key" test error. 2 = mail disabled: correct for headless
# tests regardless of what the codebase's .env configures.
export CIVI_SMTP_OUTBOUND_OPTION=2

# Resolve a PHPUnit 9 binary. Order: explicit override, PATH, cached PHAR,
# download the pinned PHAR (sha256-verified) into tools/.cache/.
PHPUNIT_VERSION=9.6.36
PHPUNIT_SHA256=d9552a130747f02f9d7fc2427b143189c638e273c502c8faa88ab6b04c5f2662
PHPUNIT_PHAR="$EXT_DIR/tools/.cache/phpunit-$PHPUNIT_VERSION.phar"

if [ -n "$PHPUNIT_OVERRIDE" ]; then
  PHPUNIT="$PHPUNIT_OVERRIDE"
elif command -v phpunit9 >/dev/null 2>&1; then
  PHPUNIT="$(command -v phpunit9)"
elif [ -f "$PHPUNIT_PHAR" ]; then
  PHPUNIT="$PHPUNIT_PHAR"
else
  command -v curl >/dev/null 2>&1 || command -v wget >/dev/null 2>&1 \
    || fail "no phpunit9 on PATH and neither curl nor wget to fetch the PHPUnit 9 PHAR. Install PHPUnit 9.6 and set PHPUNIT9_BIN."
  echo "run-phpunit: downloading PHPUnit $PHPUNIT_VERSION PHAR" >&2
  mkdir -p "$(dirname -- "$PHPUNIT_PHAR")"
  if command -v curl >/dev/null 2>&1; then
    curl -fsSL -o "$PHPUNIT_PHAR" "https://phar.phpunit.de/phpunit-$PHPUNIT_VERSION.phar"
  else
    wget -q -O "$PHPUNIT_PHAR" "https://phar.phpunit.de/phpunit-$PHPUNIT_VERSION.phar"
  fi
  checksum_ok() {
    if command -v sha256sum >/dev/null 2>&1; then
      printf '%s  %s\n' "$PHPUNIT_SHA256" "$PHPUNIT_PHAR" | sha256sum --check - >/dev/null
    elif command -v shasum >/dev/null 2>&1; then
      printf '%s  %s\n' "$PHPUNIT_SHA256" "$PHPUNIT_PHAR" | shasum -a 256 --check - >/dev/null
    else
      return 1
    fi
  }
  if ! checksum_ok; then
    rm -f "$PHPUNIT_PHAR"
    fail "PHPUnit PHAR checksum mismatch (expected $PHPUNIT_SHA256). Deleted; retry or set PHPUNIT9_BIN."
  fi
  chmod +x "$PHPUNIT_PHAR"
  PHPUNIT="$PHPUNIT_PHAR"
fi

echo "run-phpunit: db $DB_USER@[$DB_HOST]:$DB_PORT/$DB_NAME  phpunit $PHPUNIT  cv $CIVICRM_CV  settings $CIVIVOLUNTEER_TEST_SITE_SETTINGS  env ${ENV_FILE:-none}" >&2

# Work from the extension so run caches (.phpunit.result.cache) land where
# they are gitignored, whatever directory the suite was invoked from.
cd "$EXT_DIR"
exec "$PHPUNIT" -c "$EXT_DIR/phpunit.xml.dist" "$@"
