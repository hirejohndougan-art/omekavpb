#!/usr/bin/env bash
# Add an item (Phase 7): start a draft, add a file, describe it, refuse to publish without a
# title, publish with one, and the classic-form switch (remembered until switched back). Makes
# its own items and removes them, and leaves the form choice as it found it.
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
IMG=${IMG:-$(ls /home/claude/omeka-test/omeka/files/large/*.jpg | head -1)}
KEY=${VPB_API_KEY:-testkey}; CRED=${VPB_API_CRED:-testcred}
A="key_identity=$KEY&key_credential=$CRED"
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
db() { mysql -uroot omeka_test -N -e "$1" 2>/dev/null; }
token() { curl -s -b "$JAR" "$BASE/admin/vpb-add" | grep -o 'name="vpb_add_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
# Post an add-item action: post ACTION ITEM STEP [extra curl args]; prints the redirect URL.
post() { local action=$1 item=$2 step=$3; shift 3; curl -s -b "$JAR" -o /dev/null -w '%{redirect_url}' -X POST "$BASE/admin/vpb-add?item=$item&step=$step" -F "vpb_add_csrf=$(token)" -F "vpb_add_action=$action" -F "item=$item" "$@"; }
itemOf() { printf '%s' "$1" | grep -o 'item=[0-9]*' | head -1 | cut -d= -f2; }
cleanup() { for i in $ITEM $EMPTY; do [ -n "$i" ] && curl -s -o /dev/null -X DELETE "$BASE/api/items/$i?$A"; done; curl -s -b "$JAR" -o /dev/null "$BASE/admin/vpb-add?form=friendly"; }
trap cleanup EXIT

curl -s -b "$JAR" "$BASE/admin/vpb-add?form=friendly" -o /dev/null
curl -s -b "$JAR" "$BASE/admin/vpb-add" | grep -q 'vpb_add_action" value="start"' && check ok "the start screen shows" || check no "the start screen shows"

ITEM=$(itemOf "$(post start 0 0)")
[ -n "$ITEM" ] && check ok "start makes a draft item" || { check no "start makes a draft item"; exit 1; }
[ "$(db "SELECT is_public FROM resource WHERE id=$ITEM")" = 0 ] && [ "$(db "SELECT COUNT(*) FROM vpb_draft WHERE item_id=$ITEM")" = 1 ] && check ok "the new item is a staff-only draft" || check no "the new item is a staff-only draft"

post files $ITEM 1 -F "upload[]=@$IMG;type=image/jpeg" >/dev/null
[ "$(db "SELECT COUNT(*) FROM media WHERE item_id=$ITEM")" = 1 ] && check ok "the files step adds a file" || check no "the files step adds a file"

post describe $ITEM 2 -F title="VPBTEST add item" -F date=1912 -F creator="Test Person" >/dev/null
[ "$(db "SELECT COUNT(*) FROM value v WHERE v.resource_id=$ITEM AND v.property_id=1 AND v.value='VPBTEST add item'")" = 1 ] && check ok "the describe step saves the title" || check no "the describe step saves the title"
curl -s -b "$JAR" "$BASE/admin/vpb-add?item=$ITEM&step=3" | grep -q "Before you publish" && check ok "step 3 shows the checklist" || check no "step 3 shows the checklist"

EMPTY=$(itemOf "$(post start 0 0)")
post where $EMPTY 3 >/dev/null
post publish $EMPTY 3 >/dev/null
[ "$(db "SELECT is_public FROM resource WHERE id=$EMPTY")" = 0 ] && check ok "publish is refused without a title" || check no "publish is refused without a title"

post publish $ITEM 3 >/dev/null
[ "$(db "SELECT is_public FROM resource WHERE id=$ITEM")" = 1 ] && [ "$(db "SELECT COUNT(*) FROM vpb_draft WHERE item_id=$ITEM")" = 0 ] && check ok "publish with a title makes the item public" || check no "publish with a title makes the item public"

[ "$(curl -s -b "$JAR" -o /dev/null -w '%{redirect_url}' "$BASE/admin/vpb-add?form=classic")" = "$BASE/admin/item/add" ] && check ok "the classic form switch goes to Omeka's add form" || check no "the classic form switch goes to Omeka's add form"
[ "$(curl -s -b "$JAR" -o /dev/null -w '%{redirect_url}' "$BASE/admin/vpb-add")" = "$BASE/admin/item/add" ] && check ok "the choice is remembered on the next visit" || check no "the choice is remembered on the next visit"
[ "$(curl -s -b "$JAR" -o /dev/null -w '%{redirect_url}' "$BASE/admin/vpb-add?form=friendly")" = "$BASE/admin/vpb-add" ] && check ok "switching back returns to the simple form" || check no "switching back returns to the simple form"
curl -s -b "$JAR" "$BASE/admin/vpb-add" | grep -q 'vpb_add_action" value="start"' && check ok "the simple form is back" || check no "the simple form is back"

echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
