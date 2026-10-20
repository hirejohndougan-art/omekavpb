#!/usr/bin/env bash
# Simple and Expert mode (Phase 9): Simple shows plain words only; Expert adds the Omeka sidebar
# group, the dcterms code and ID on One item, and the standard-form link. The choice is per person,
# and the test leaves it on Simple. Makes its own item and removes it.
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
KEY=${VPB_API_KEY:-testkey}; CRED=${VPB_API_CRED:-testcred}
A="key_identity=$KEY&key_credential=$CRED"
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
ITEM=$(curl -s -X POST "$BASE/api/items?$A" -H 'Content-Type: application/json' -d '{"o:is_public":false,"dcterms:title":[{"type":"literal","property_id":1,"@value":"VPBTEST mode item"}]}' | grep -o '"o:id":[0-9]*' | head -1 | cut -d: -f3)
cleanup() { curl -s -o /dev/null -b "$JAR" "$BASE/admin/vpb-mode?to=simple"; [ -n "$ITEM" ] && curl -s -o /dev/null -X DELETE "$BASE/api/items/$ITEM?$A"; }
trap cleanup EXIT
[ -n "$ITEM" ] && check ok "test item made" || { check no "test item made"; exit 1; }

curl -s -b "$JAR" -o /dev/null "$BASE/admin/vpb-mode?to=simple"
S=$(curl -s -b "$JAR" "$BASE/admin/vpb-item/$ITEM")
printf '%s' "$S" | grep -q "Switch to Expert" && check ok "Simple offers Expert" || check no "Simple offers Expert"
printf '%s' "$S" | grep -q "Resource templates" && check no "Simple hides the Omeka screens" || check ok "Simple hides the Omeka screens"
printf '%s' "$S" | grep -q "standard Omeka form" && check no "Simple hides the standard-form link" || check ok "Simple hides the standard-form link"
printf '%s' "$S" | grep -q "dcterms:title" && check no "Simple hides the codes" || check ok "Simple hides the codes"

[ "$(curl -s -b "$JAR" -o /dev/null -w '%{redirect_url}' "$BASE/admin/vpb-mode?to=expert")" = "$BASE/admin/vpb-home" ] && check ok "switching to Expert goes Home" || check no "switching to Expert goes Home"
E=$(curl -s -b "$JAR" "$BASE/admin/vpb-item/$ITEM")
printf '%s' "$E" | grep -q "Switch to Simple" && check ok "Expert offers Simple" || check no "Expert offers Simple"
printf '%s' "$E" | grep -q "Resource templates" && check ok "Expert shows the Omeka screens" || check no "Expert shows the Omeka screens"
printf '%s' "$E" | grep -q "standard Omeka form" && check ok "Expert shows the standard-form link" || check no "Expert shows the standard-form link"
printf '%s' "$E" | grep -q "dcterms:title" && check ok "Expert shows the dcterms code" || check no "Expert shows the dcterms code"

echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
