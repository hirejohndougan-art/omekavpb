#!/usr/bin/env bash
# Search synonyms on the Settings screen (Phase 9): saved there, cleaned of lines that are not
# rules, and shown on the old Search screen with a link to Settings. The test puts back every
# VPB setting it changes.
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
db() { mysql -uroot omeka_test -N -e "$1" 2>/dev/null; }
token() { curl -s -b "$JAR" "$BASE/admin/vpb-settings" | grep -o 'name="vpb_settings_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }

SAVED=$(db "SELECT CONCAT(id, '|', HEX(value)) FROM setting WHERE id LIKE 'vpb\\_%'")
cleanup() {
  [ -n "$SAVED" ] || return
  IDS=$(printf '%s\n' "$SAVED" | cut -d'|' -f1 | sed "s/.*/'&'/" | paste -sd, -)
  printf '%s\n' "$SAVED" | while IFS='|' read -r key hex; do
    db "INSERT INTO setting (id, value) VALUES ('$key', UNHEX('$hex')) ON DUPLICATE KEY UPDATE value = UNHEX('$hex')"
  done
  db "DELETE FROM setting WHERE id LIKE 'vpb\\_%' AND id NOT IN ($IDS)"
}
trap cleanup EXIT

curl -s -b "$JAR" -o /dev/null -w '%{http_code}' -X POST "$BASE/admin/vpb-settings" -F "vpb_settings_csrf=$(token)" -F "kinds[]=images" \
  -F "vpb_new_item_status=private" -F "vpb_items_per_page=25" -F "synonyms=rr = railroad
not a rule
station = depot" >/dev/null
[ "$(db "SELECT HEX(JSON_UNQUOTE(value)) FROM setting WHERE id='vpb_search_synonyms'")" = "$(printf 'rr = railroad\nstation = depot' | od -An -tx1 | tr -d ' \n' | tr 'a-f' 'A-F')" ] && check ok "synonyms are saved on Settings, without the lines that are not rules" || check no "synonyms are saved on Settings, without the lines that are not rules"

curl -s -b "$JAR" "$BASE/admin/vpb-settings" | grep -q "rr = railroad" && check ok "Settings shows the synonyms" || check no "Settings shows the synonyms"

S=$(curl -s -b "$JAR" "$BASE/admin/vpb-search-settings")
printf '%s' "$S" | grep -q "Edit synonyms in Settings" && check ok "the Search screen links to Settings" || check no "the Search screen links to Settings"
printf '%s' "$S" | grep -q "<textarea" && check no "the Search screen no longer edits synonyms" || check ok "the Search screen no longer edits synonyms"

echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
