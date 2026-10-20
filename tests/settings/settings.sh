#!/usr/bin/env bash
# Settings screen (Phase 9): saves the values, refuses an empty file-kind choice, and
# writes Omeka's media type whitelist for the kinds chosen. Puts the install's own
# values back at the end. Needs the test install, the admin cookie (JAR, default /tmp/jar.txt)
# and root MySQL over the socket.
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
db() { mysql -uroot omeka_test -N -e "$1" 2>/dev/null; }
token() { curl -s -b "$JAR" "$BASE/admin/vpb-settings" | grep -o 'name="vpb_settings_csrf" value="[^"]*"' | sed 's/.*value="//;s/"$//'; }
save() { curl -s -b "$JAR" -o /dev/null -w '%{http_code}' -X POST "$BASE/admin/vpb-settings" --data-urlencode "vpb_settings_csrf=$(token)" "$@"; }
val() { db "SELECT value FROM setting WHERE id='$1'"; }

# Keep the install's values to put back.
ORIG_ORG=$(val vpb_org_name); ORIG_WL=$(val media_type_whitelist); ORIG_KINDS=$(val vpb_file_kinds)
restore() {
  if [ -n "$ORIG_ORG" ]; then db "REPLACE INTO setting (id, value) VALUES ('vpb_org_name', '$(printf '%s' "$ORIG_ORG" | sed "s/'/''/g")')"; else db "DELETE FROM setting WHERE id='vpb_org_name'"; fi
  if [ -n "$ORIG_WL" ]; then db "REPLACE INTO setting (id, value) VALUES ('media_type_whitelist', '$(printf '%s' "$ORIG_WL" | sed "s/'/''/g")')"; fi
  if [ -n "$ORIG_KINDS" ]; then db "REPLACE INTO setting (id, value) VALUES ('vpb_file_kinds', '$(printf '%s' "$ORIG_KINDS" | sed "s/'/''/g")')"; else db "DELETE FROM setting WHERE id='vpb_file_kinds'"; fi
}
trap restore EXIT

CODE=$(save --data-urlencode "vpb_org_name=Test Historical Society" -d vpb_contact_email=help@example.org \
  -d vpb_new_item_status=public -d vpb_items_per_page=40 -d vpb_show_cite=1 -d kinds[]=images -d kinds[]=documents)
[ "$CODE" = 302 ] && check ok "save redirects back to the screen" || check no "save redirects back to the screen ($CODE)"
[ "$(val vpb_org_name)" = '"Test Historical Society"' ] && check ok "organization name saved" || check no "organization name saved"
[ "$(val vpb_items_per_page)" = '40' ] && check ok "items per page saved" || check no "items per page saved"
[ "$(val vpb_new_item_status)" = '"public"' ] && check ok "new item status saved" || check no "new item status saved"
[ "$(val vpb_show_download)" = 'false' ] && check ok "an unticked button is saved as off" || check no "an unticked button is saved as off"
WL=$(val media_type_whitelist)
if printf '%s' "$WL" | grep -q 'png' && printf '%s' "$WL" | grep -q 'pdf' && ! printf '%s' "$WL" | grep -q 'audio'; then
  check ok "the whitelist follows the kinds chosen"; else check no "the whitelist follows the kinds chosen"; fi

BEFORE=$(val vpb_file_kinds)
save -d vpb_org_name=Kept >/dev/null
[ "$(val vpb_file_kinds)" = "$BEFORE" ] && check ok "no kind chosen is refused and the kinds stay" || check no "no kind chosen is refused and the kinds stay"

save -d kinds[]=images -d kinds[]=documents -d kinds[]=audio -d kinds[]=video >/dev/null
printf '%s' "$(val media_type_whitelist)" | grep -q 'audio' && check ok "all kinds restores Omeka's own list" || check no "all kinds restores Omeka's own list"
save -d vpb_org_name= -d kinds[]=images >/dev/null
[ "$(val vpb_org_name)" = '""' ] && check ok "a blank organization name saves as blank" || check no "a blank organization name saves as blank"

echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
