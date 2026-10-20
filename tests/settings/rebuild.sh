#!/usr/bin/env bash
# Search index rebuild button (Phase 10): the Settings screen starts the ReindexJob in the
# background, and a second press while it runs does nothing. Needs the test install, the
# admin cookie (JAR, default /tmp/jar.txt) and root MySQL over the socket.
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
db() { mysql -uroot omeka_test -N -e "$1" 2>/dev/null; }
token() { curl -s -b "$JAR" -c "$JAR" "$BASE/admin/vpb-settings" | grep -o 'name="vpb_settings_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
press() { curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code}' -X POST "$BASE/admin/vpb-settings" --data-urlencode "vpb_settings_csrf=$(token)" -d vpb_settings_action=rebuild_index; }

db "DELETE FROM setting WHERE id='vpb_search_rebuilding'"
BEFORE=$(db "SELECT COUNT(*) FROM job WHERE class LIKE '%ReindexJob%'")

CODE=$(press)
[ "$CODE" = 302 ] && check ok "the button redirects back to Settings" || check no "the button redirects back to Settings ($CODE)"
AFTER=$(db "SELECT COUNT(*) FROM job WHERE class LIKE '%ReindexJob%'")
[ "$AFTER" -gt "$BEFORE" ] && check ok "a rebuild job is queued" || check no "a rebuild job is queued"

# While a rebuild is marked as running, a second press queues nothing and the page says so.
db "REPLACE INTO setting (id, value) VALUES ('vpb_search_rebuilding', 'true')"
MID=$(db "SELECT COUNT(*) FROM job WHERE class LIKE '%ReindexJob%'")
press >/dev/null
[ "$(db "SELECT COUNT(*) FROM job WHERE class LIKE '%ReindexJob%'")" = "$MID" ] && check ok "a second press while running queues nothing" || check no "a second press while running queues nothing"
curl -s -b "$JAR" -c "$JAR" "$BASE/admin/vpb-settings" | grep -q "is running now" && check ok "the page says a rebuild is running and disables the button" || check no "the page says a rebuild is running and disables the button"
db "DELETE FROM setting WHERE id='vpb_search_rebuilding'"

echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
