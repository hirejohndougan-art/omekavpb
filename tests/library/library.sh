#!/usr/bin/env bash
# Files and Item sets (Phase 8): the file list by kind with links to items; item sets as cards
# with a See-its-items link; making a set (and refusing one with no name). Makes and removes
# its own item set. Needs the test install, the admin cookie (JAR, default /tmp/jar.txt) and
# root MySQL.
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
KEY=${VPB_API_KEY:-testkey}; CRED=${VPB_API_CRED:-testcred}
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
db() { mysql -uroot omeka_test -N -e "$1" 2>/dev/null; }
get() { curl -s -b "$JAR" "$BASE/admin/$1"; }
token() { get vpb-item-sets | grep -o 'name="vpb_library_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
post() { curl -s -b "$JAR" -o /dev/null -w '%{http_code}' -X POST "$BASE/admin/vpb-item-sets" --data-urlencode "vpb_library_csrf=$(token)" "$@"; }

FILES=$(get vpb-files)
printf '%s' "$FILES" | grep -q "Its item" && check ok "the files screen lists files with their items" || check no "the files screen lists files with their items"
IMG=$(get "vpb-files?kind=images")
printf '%s' "$IMG" | grep -q 'aria-pressed="true">Images' && check ok "the Images kind is chosen" || check no "the Images kind is chosen"
[ "$(db "SELECT COUNT(*) FROM media WHERE media_type LIKE 'image/%'")" -gt 0 ] && printf '%s' "$IMG" | grep -q "Its item" && check ok "images are listed" || check no "images are listed"
AUD=$(get "vpb-files?kind=audio")
printf '%s' "$AUD" | grep -q "audio/" && check ok "the Audio kind lists audio files" || check no "the Audio kind lists audio files"
printf '%s' "$AUD" | grep -q "image/" && check no "the Audio kind leaves out images" || check ok "the Audio kind leaves out images"

db "DELETE FROM item_set WHERE id IN (SELECT id FROM resource WHERE title='VPBTEST set')" >/dev/null
CODE=$(post -d title="VPBTEST set" -d description="made by the test" -d is_public=1)
[ "$CODE" = 302 ] && check ok "making a set redirects back" || check no "making a set redirects back ($CODE)"
SETS=$(get vpb-item-sets)
printf '%s' "$SETS" | grep -q "VPBTEST set" && check ok "the new set shows as a card" || check no "the new set shows as a card"
ID=$(db "SELECT MAX(id) FROM item_set")
printf '%s' "$SETS" | grep -q "item_set_id=$ID" && check ok "its See-its-items link filters by the set" || check no "its See-its-items link filters by the set"

BEFORE=$(db "SELECT COUNT(*) FROM item_set")
post -d title= >/dev/null
[ "$(db "SELECT COUNT(*) FROM item_set")" = "$BEFORE" ] && check ok "a set with no name is refused" || check no "a set with no name is refused"

curl -s -o /dev/null -X DELETE "$BASE/api/item_sets/$ID?key_identity=$KEY&key_credential=$CRED"
[ "$(db "SELECT COUNT(*) FROM item_set WHERE id=$ID")" = 0 ] && check ok "the test set is removed" || check no "the test set is removed"

echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
