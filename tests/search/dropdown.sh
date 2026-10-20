#!/usr/bin/env bash
# Filters as a dropdown (text_style=dropdown): the dropdown lists the values not in use,
# a chosen value is added to the filters, and the form keeps the filters already in use.
# Creates and deletes its own page on the test install (BASE, default 127.0.0.1:8080).
BASE=${BASE:-http://127.0.0.1:8080}
KEY=${VPB_API_KEY:-testkey}; CRED=${VPB_API_CRED:-testcred}
A="key_identity=$KEY&key_credential=$CRED"
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
ID=$(curl -s -X POST "$BASE/api/site_pages?$A" -H 'Content-Type: application/json' -d '{"o:site":{"o:id":1},"o:title":"VPBTEST dropdown check","o:slug":"vpbtest-dropdown-check","o:is_public":true,"o:block":[{"o:layout":"facets","o:data":{"text_style":"dropdown"}},{"o:layout":"results"}]}' | grep -o '"o:id":[0-9]*' | head -1 | cut -d: -f2)
[ -n "$ID" ] && check ok "test page made" || { check no "test page made"; exit 1; }
trap 'curl -s -o /dev/null -X DELETE "$BASE/api/site_pages/$ID?$A"' EXIT
URL="$BASE/s/dhs/page/vpbtest-dropdown-check"
P=$(curl -s "$URL")
printf '%s' "$P" | grep -q '<select name="subject\[\]">' && check ok "subject is a dropdown" || check no "subject is a dropdown"
SUB=$(printf '%s' "$P" | grep -o 'value="[0-9]*">Railroad' | grep -o '[0-9]*' | head -1)
[ -n "$SUB" ] && check ok "Railroad is in the dropdown (id $SUB)" || check no "Railroad is in the dropdown"
F=$(curl -s "$URL?subject%5B%5D=$SUB")
printf '%s' "$F" | grep -q 'Filtered by' && check ok "a chosen value shows in Filtered by" || check no "a chosen value shows in Filtered by"
printf '%s' "$F" | grep -q "value=\"$SUB\">Railroad" && check no "a value in use is left out of the dropdown" || check ok "a value in use is left out of the dropdown"
printf '%s' "$F" | grep -q "<input type=\"hidden\" name=\"subject\[\]\" value=\"$SUB\"" && check ok "the dropdown form keeps the filter in use" || check no "the dropdown form keeps the filter in use"
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
