#!/usr/bin/env bash
# One item, Files tab (Phase 6): add a file, make it the main image, and refuse a file from
# another item or an empty upload. Makes its own item and removes it.
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
KEY=${VPB_API_KEY:-testkey}; CRED=${VPB_API_CRED:-testcred}
A="key_identity=$KEY&key_credential=$CRED"
IMG=${IMG:-$(ls /home/claude/omeka-test/omeka/files/large/*.jpg | head -1)}
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
db() { mysql -uroot omeka_test -N -e "$1" 2>/dev/null; }
page() { curl -s -b "$JAR" "$BASE/admin/vpb-item/$ITEM?tab=files"; }
token() { page | grep -o 'name="vpb_item_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }

ITEM=$(curl -s -X POST "$BASE/api/items?$A" -H 'Content-Type: application/json' -d '{"o:is_public":true,"dcterms:title":[{"type":"literal","property_id":1,"@value":"VPBTEST files item"}]}' | grep -o '"o:id":[0-9]*' | head -1 | cut -d: -f3)
[ -n "$ITEM" ] && check ok "test item made" || { check no "test item made"; exit 1; }
cleanup() { [ -n "$ITEM" ] && curl -s -o /dev/null -X DELETE "$BASE/api/items/$ITEM?$A"; }
trap cleanup EXIT

page | grep -q "Add a file" && check ok "files tab has the add form" || check no "files tab has the add form"

curl -s -b "$JAR" -o /dev/null -X POST "$BASE/admin/vpb-item/$ITEM?tab=files" -F "vpb_item_csrf=$(token)" -F vpb_action=upload -F tab=files -F "upload=@$IMG;type=image/jpeg"
MEDIA=$(db "SELECT id FROM media WHERE item_id=$ITEM ORDER BY id LIMIT 1")
[ -n "$MEDIA" ] && check ok "upload adds a file to the item" || check no "upload adds a file to the item"
page | grep -q "Remove" && check ok "the file is listed with a Remove button" || check no "the file is listed with a Remove button"

curl -s -b "$JAR" -o /dev/null -X POST "$BASE/admin/vpb-item/$ITEM?tab=files" -F "vpb_item_csrf=$(token)" -F vpb_action=upload -F tab=files -F "upload=@/dev/null;filename=empty.jpg"
[ "$(db "SELECT COUNT(*) FROM media WHERE item_id=$ITEM")" = 1 ] && check ok "an empty upload adds nothing" || check no "an empty upload adds nothing"

curl -s -b "$JAR" -o /dev/null -X POST "$BASE/admin/vpb-item/$ITEM?tab=files" --data-urlencode "vpb_item_csrf=$(token)" -d vpb_action=set_main -d tab=files -d media_id=999999999
[ "$(db "SELECT COALESCE(primary_media_id,0) FROM item WHERE id=$ITEM")" = 0 ] && check ok "a file not on this item cannot be the main image" || check no "a file not on this item cannot be the main image"

curl -s -b "$JAR" -o /dev/null -X POST "$BASE/admin/vpb-item/$ITEM?tab=files" --data-urlencode "vpb_item_csrf=$(token)" -d vpb_action=set_main -d tab=files -d media_id=$MEDIA
[ "$(db "SELECT primary_media_id FROM item WHERE id=$ITEM")" = "$MEDIA" ] && check ok "make main image sets the file" || check no "make main image sets the file"
page | grep -q "Main image" && check ok "the main file is marked" || check no "the main file is marked"

echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
