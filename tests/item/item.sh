#!/usr/bin/env bash
# One item (Phase 6): tabs render, details save, visibility, draft keep and publish, history
# restore, duplicate, and delete (only with the confirmation tick). Makes its own item through
# the API and removes everything it made.
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
KEY=${VPB_API_KEY:-testkey}; CRED=${VPB_API_CRED:-testcred}
A="key_identity=$KEY&key_credential=$CRED"
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
db() { mysql -uroot omeka_test -N -e "$1" 2>/dev/null; }
page() { curl -s -b "$JAR" "$BASE/admin/vpb-item/$ITEM$1"; }
token() { page "?tab=$1" | grep -o 'name="vpb_item_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
# act TAB ACTION [extra curl args...]: POST one action; prints the redirect URL.
act() { local tab=$1 action=$2; shift 2; curl -s -b "$JAR" -o /dev/null -w '%{redirect_url}' -X POST "$BASE/admin/vpb-item/$ITEM?tab=$tab" --data-urlencode "vpb_item_csrf=$(token "$tab")" -d "vpb_action=$action" -d "tab=$tab" "$@"; }

ITEM=$(curl -s -X POST "$BASE/api/items?$A" -H 'Content-Type: application/json' -d '{"o:is_public":true,"dcterms:title":[{"type":"literal","property_id":1,"@value":"VPBTEST item one"}]}' | grep -o '"o:id":[0-9]*' | head -1 | cut -d: -f3)
[ -n "$ITEM" ] && check ok "test item made" || { check no "test item made"; exit 1; }
COPY=
cleanup() { [ -n "$COPY" ] && curl -s -o /dev/null -X DELETE "$BASE/api/items/$COPY?$A"; [ -n "$ITEM" ] && curl -s -o /dev/null -X DELETE "$BASE/api/items/$ITEM?$A"; }
trap cleanup EXIT

page "?tab=details" | grep -q "Save details" && check ok "details tab shows the form" || check no "details tab shows the form"
page "?tab=where" | grep -q 'name="is_public"' && check ok "where tab shows visibility" || check no "where tab shows visibility"
page "?tab=files" | grep -q "no files" && check ok "files tab says the item has no files" || check no "files tab says the item has no files"
page "?tab=history" | grep -q "No earlier versions" && check ok "history tab is empty before any change" || check no "history tab is empty before any change"
[ "$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' "$BASE/admin/vpb-item/$ITEM?tab=nope")" = 200 ] && check ok "an unknown tab falls back to details" || check no "an unknown tab falls back to details"

act details details -d title="VPBTEST item renamed" -d description="Changed here" >/dev/null
[ "$(db "SELECT COUNT(*) FROM value v WHERE v.resource_id=$ITEM AND v.property_id=1 AND v.value='VPBTEST item renamed'")" = 1 ] && check ok "save details changes the title" || check no "save details changes the title"
[ "$(db "SELECT COUNT(*) FROM vpb_revision WHERE item_id=$ITEM")" -ge 1 ] && check ok "the change keeps the earlier version" || check no "the change keeps the earlier version"

act details details -d title= >/dev/null
[ "$(db "SELECT COUNT(*) FROM value v WHERE v.resource_id=$ITEM AND v.property_id=1 AND v.value='VPBTEST item renamed'")" = 1 ] && check ok "a blank title keeps the old title" || check no "a blank title keeps the old title"

act where where >/dev/null
[ "$(db "SELECT is_public FROM resource WHERE id=$ITEM")" = 0 ] && check ok "unticked visibility makes it staff only" || check no "unticked visibility makes it staff only"
act where where -d is_public=1 >/dev/null
[ "$(db "SELECT is_public FROM resource WHERE id=$ITEM")" = 1 ] && check ok "ticked visibility makes it public" || check no "ticked visibility makes it public"

act where keep_draft >/dev/null
[ "$(db "SELECT COUNT(*) FROM vpb_draft WHERE item_id=$ITEM")" = 1 ] && [ "$(db "SELECT is_public FROM resource WHERE id=$ITEM")" = 0 ] && check ok "keep as draft hides it and marks it a draft" || check no "keep as draft hides it and marks it a draft"
page "?tab=details" | grep -q "Draft" && check ok "the screen shows the draft state" || check no "the screen shows the draft state"
act where publish >/dev/null
[ "$(db "SELECT COUNT(*) FROM vpb_draft WHERE item_id=$ITEM")" = 0 ] && [ "$(db "SELECT is_public FROM resource WHERE id=$ITEM")" = 1 ] && check ok "publish makes a draft public" || check no "publish makes a draft public"

REV=$(db "SELECT MIN(id) FROM vpb_revision WHERE item_id=$ITEM")
act history restore -d revision_id=$REV >/dev/null
[ "$(db "SELECT COUNT(*) FROM value v WHERE v.resource_id=$ITEM AND v.property_id=1 AND v.value='VPBTEST item one'")" = 1 ] && check ok "restore brings back an earlier title" || check no "restore brings back an earlier title"

REDIR=$(act details duplicate)
COPY=$(printf '%s' "$REDIR" | grep -o '/vpb-item/[0-9]*' | grep -o '[0-9]*$')
[ -n "$COPY" ] && [ "$(db "SELECT is_public FROM resource WHERE id=$COPY")" = 0 ] && check ok "duplicate makes a private copy and opens it" || check no "duplicate makes a private copy and opens it"

act details delete >/dev/null
[ "$(db "SELECT COUNT(*) FROM item WHERE id=$ITEM")" = 1 ] && check ok "delete without the tick deletes nothing" || check no "delete without the tick deletes nothing"
REDIR=$(act details delete -d confirm_delete=1)
[ "$(db "SELECT COUNT(*) FROM item WHERE id=$ITEM")" = 0 ] && check ok "delete with the tick deletes the item" || check no "delete with the tick deletes the item"
printf '%s' "$REDIR" | grep -q "vpb-items" && check ok "delete goes back to Items" || check no "delete goes back to Items"
ITEM=

echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
