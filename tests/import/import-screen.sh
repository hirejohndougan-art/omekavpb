#!/usr/bin/env bash
# Import screen (Phase 8): without CSV Import active it says so and links to Modules.
# Needs the test install and the admin cookie (JAR, default /tmp/jar.txt).
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
P=$(curl -s -b "$JAR" "$BASE/admin/vpb-import")
state=$(mysql -uroot omeka_test -N -e "SELECT is_active FROM module WHERE id='CSVImport'" 2>/dev/null)
if [ -z "$state" ] || [ "$state" = 0 ]; then
  printf '%s' "$P" | grep -q "not turned on" && check ok "the Import screen says CSV Import is not on" || check no "the Import screen says CSV Import is not on"
  printf '%s' "$P" | grep -q "Go to the Modules page" && check ok "it links to the Modules page" || check no "it links to the Modules page"
else
  printf '%s' "$P" | grep -q "Choose a spreadsheet" && check ok "the Import screen opens when CSV Import is on" || check no "the Import screen opens when CSV Import is on"
fi
printf '%s' "$P" | grep -q 'aria-current="page"' && check ok "the library menu marks Import as the current screen" || check no "the library menu marks Import as the current screen"
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
