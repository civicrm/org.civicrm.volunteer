#!/usr/bin/env bash
#
# Run the CiviVolunteer JavaScript checks from any codebase.
#
# The JS checks are standalone plain-Node scripts in tests/js/ — no framework,
# no package.json, no database — so unlike tools/run-phpunit.sh there is
# nothing to configure. Every machine-specific input is absent by design; the
# only requirement is `node` on PATH. Run:
#
#   tools/run-js-tests.sh                 # every tests/js/*.test.js
#   node tests/js/foo.test.js             # one check while iterating
#
# Each script runs its assertions top to bottom and exits non-zero on the
# first failure. This runner prints PASS/FAIL per file and exits non-zero if
# any check failed.
#

set -u

ext_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ext_dir"

status=0
for test_file in tests/js/*.test.js; do
  output="$(node "$test_file" 2>&1)"
  if [ "$?" -eq 0 ]; then
    echo "PASS $test_file"
  else
    status=1
    echo "FAIL $test_file"
    printf '%s\n' "$output" | sed 's/^/    /'
  fi
done

exit "$status"
