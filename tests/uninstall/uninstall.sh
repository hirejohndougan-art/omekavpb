#!/usr/bin/env bash
# Uninstall (Phase 10): with VPB removed, the site still renders, Omeka's own search works, and
# items, drafts and pages survive. It runs on a throwaway copy of the test install with its own
# database and port (8081), so the shared test install is not touched. The copy is removed afterward.
SRC=${SRC:-/home/claude/omeka-test/omeka}
COPY=${COPY:-/home/claude/omeka-uninstall}
DB=omeka_uninstall
PORT=8081
BASE=http://127.0.0.1:$PORT
UJAR=/tmp/uninstall-jar.txt
PASS=Uninstall-test-123
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
db() { mysql -uroot "$DB" -N -e "$1" 2>/dev/null; }
cleanup() {
  [ -n "$PID" ] && kill "$PID" 2>/dev/null
  mysql -uroot -e "DROP DATABASE IF EXISTS $DB" 2>/dev/null
  rm -rf "$COPY"
}
trap cleanup EXIT

rm -rf "$COPY"; mysql -uroot -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB; GRANT ALL PRIVILEGES ON $DB.* TO 'omeka'@'localhost'; FLUSH PRIVILEGES"
mysqldump -uroot omeka_test 2>/dev/null | mysql -uroot "$DB"
cp -r "$SRC" "$COPY"
sed -i "s/^dbname   = .*/dbname   = \"$DB\"/" "$COPY/config/database.ini"
(cd "$COPY" && nohup php -S 127.0.0.1:$PORT index.php >/tmp/uninstall-server.log 2>&1 &)
sleep 2
PID=$(ps -eo pid,args | grep "php -S 127.0.0.1:$PORT" | grep -v grep | awk '{print $1}' | head -1)
# The copy has its own user table entries: give its global administrator a known password, then sign in.
HASH=$(php -r "echo password_hash('$PASS', PASSWORD_DEFAULT);")
db "UPDATE user SET password_hash='$HASH' WHERE email='test@example.com'" >/dev/null
rm -f "$UJAR"
curl -s -c "$UJAR" -b "$UJAR" "$BASE/login" -o /tmp/uninstall-login.html
LT=$(grep -o 'loginform_csrf" value="[^"]*"' /tmp/uninstall-login.html | sed 's/.*value="//;s/"$//')
curl -s -c "$UJAR" -b "$UJAR" -o /dev/null -X POST "$BASE/login" --data-urlencode "loginform_csrf=$LT" -d "email=test@example.com&password=$PASS&submit=Log+in"


ITEMS=$(db "SELECT COUNT(*) FROM item")
PAGES=$(db "SELECT COUNT(*) FROM site_page")
DRAFTS=$(db "SELECT COUNT(*) FROM vpb_draft")
[ "$ITEMS" -gt 0 ] && check ok "the copy has items before uninstall ($ITEMS)" || check no "the copy has items before uninstall"
[ "$(curl -s -o /dev/null -w '%{http_code}' -b "$UJAR" "$BASE/s/dhs")" = 200 ] && check ok "the site renders before uninstall" || check no "the site renders before uninstall"

TOKEN=$(curl -s -b "$UJAR" "$BASE/admin/module" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//')
curl -s -b "$UJAR" -o /dev/null -w '%{http_code}' -X POST "$BASE/admin/module/uninstall?id=VisualPageBuilder" -d "csrf=$TOKEN" -d submit=Confirm >/tmp/uninstall-status
[ "$(db "SELECT COUNT(*) FROM module WHERE id='VisualPageBuilder'")" = 0 ] && check ok "VPB is uninstalled" || check no "VPB is uninstalled"
[ "$(db "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB' AND table_name='vpb_draft'")" = 0 ] && check ok "its tables are dropped" || check no "its tables are dropped"

[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/s/dhs")" = 200 ] && check ok "the site still renders without VPB" || check no "the site still renders without VPB"
[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/s/dhs/item?fulltext_search=depot")" = 200 ] && check ok "Omeka's own search still works" || check no "Omeka's own search still works"
[ "$(db "SELECT COUNT(*) FROM item")" = "$ITEMS" ] && check ok "items survive" || check no "items survive"
[ "$(db "SELECT COUNT(*) FROM site_page")" = "$PAGES" ] && check ok "pages survive" || check no "pages survive"

rm -f "$UJAR"
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
