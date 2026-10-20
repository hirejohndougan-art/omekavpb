#!/usr/bin/env bash
# Items list (Phase 6): bulk make staff only and public, add to an item set, and delete (only
# with the confirmation tick). Makes its own item and set through the API, and removes them.
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
KEY=${VPB_API_KEY:-testkey}; CRED=${VPB_API_CRED:-testcred}
A="key_identity=$KEY&key_credential=$CRED"
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
db() { mysql -uroot omeka_test -N -e "$1" 2>/dev/null; }
isPublic() { db "SELECT is_public FROM resource WHERE id=$1"; }
token() { curl -s -b "$JAR" "$BASE/admin/vpb-items" | grep -o 'name="vpb_items_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
bulk() { curl -s -b "$JAR" -o /dev/null -w '%{http_code}' -X POST "$BASE/admin/vpb-items?q=VPBTEST+items" --data-urlencode "vpb_items_csrf=$(token)" "$@"; }

ITEM=$(curl -s -X POST "$BASE/api/items?$A" -H 'Content-Type: application/json' -d '{"o:is_public":true,"dcterms:title":[{"type":"literal","property_id":1,"@value":"VPBTEST items one"}]}' | grep -o '"o:id":[0-9]*' | head -1 | cut -d: -f3)
SET=$(curl -s -X POST "$BASE/api/item_sets?$A" -H 'Content-Type: application/json' -d '{"o:is_public":true,"dcterms:title":[{"type":"literal","property_id":1,"@value":"VPBTEST items set"}]}' | grep -o '"o:id":[0-9]*' | head -1 | cut -d: -f3)
[ -n "$ITEM" ] && [ -n "$SET" ] && check ok "test item and set made" || { check no "test item and set made"; exit 1; }
cleanup() { curl -s -o /dev/null -X DELETE "$BASE/api/items/$ITEM?$A"; curl -s -o /dev/null -X DELETE "$BASE/api/item_sets/$SET?$A"; }
trap cleanup EXIT

LIST=$(curl -s -b "$JAR" "$BASE/admin/vpb-items?q=VPBTEST+items")
printf '%s' "$LIST" | grep -q "VPBTEST items one" && check ok "the search finds the test item" || check no "the search finds the test item"

bulk -d vpb_action=private -d "ids[]=$ITEM" >/dev/null
[ "$(isPublic $ITEM)" = 0 ] && check ok "make staff only" || check no "make staff only"
bulk -d vpb_action=public -d "ids[]=$ITEM" >/dev/null
[ "$(isPublic $ITEM)" = 1 ] && check ok "make public" || check no "make public"

bulk -d vpb_action=add_set -d item_set_id=$SET -d "ids[]=$ITEM" >/dev/null
[ "$(db "SELECT COUNT(*) FROM item_item_set WHERE item_id=$ITEM AND item_set_id=$SET")" = 1 ] && check ok "add to an item set" || check no "add to an item set"

bulk -d vpb_action=delete -d "ids[]=$ITEM" >/dev/null
[ "$(db "SELECT COUNT(*) FROM item WHERE id=$ITEM")" = 1 ] && check ok "delete without the tick deletes nothing" || check no "delete without the tick deletes nothing"

bulk -d vpb_action=delete -d confirm_delete=1 -d "ids[]=$ITEM" >/dev/null
[ "$(db "SELECT COUNT(*) FROM item WHERE id=$ITEM")" = 0 ] && check ok "delete with the tick deletes the item" || check no "delete with the tick deletes the item"
ITEM=

echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
