#!/usr/bin/env bash
# Search box on a long text facet (find[subject]=...): the search box shows for a long list,
# narrows the values to the ones that contain the text, keeps the search when a value is chosen,
# and offers "Clear search". Creates and deletes its own page on the test install.
BASE=${BASE:-http://127.0.0.1:8080}
KEY=${VPB_API_KEY:-testkey}; CRED=${VPB_API_CRED:-testcred}
A="key_identity=$KEY&key_credential=$CRED"
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
# A page left by an earlier run would block the slug: remove it first.
OLD=$(mysql -uroot omeka_test -N -e "SELECT id FROM site_page WHERE slug='vpbtest-find-check'" 2>/dev/null | head -1)
[ -n "$OLD" ] && curl -s -o /dev/null -X DELETE "$BASE/api/site_pages/$OLD?$A"
ID=$(curl -s -X POST "$BASE/api/site_pages?$A" -H 'Content-Type: application/json' -d '{"o:site":{"o:id":1},"o:title":"VPBTEST find check","o:slug":"vpbtest-find-check","o:is_public":true,"o:block":[{"o:layout":"facets","o:data":{"text_shown":1}},{"o:layout":"results"}]}' | grep -o '"o:id":[0-9]*' | head -1 | cut -d: -f2)
[ -n "$ID" ] && check ok "test page made" || { check no "test page made"; exit 1; }
trap 'curl -s -o /dev/null -X DELETE "$BASE/api/site_pages/$ID?$A"' EXIT
URL="$BASE/s/dhs/page/vpbtest-find-check"
P=$(curl -s "$URL")
printf '%s' "$P" | grep -q 'class="vpb-facet-find"' && check ok "the search box shows for a long list" || check no "the search box shows for a long list"
F=$(curl -s "$URL?find%5Bsubject%5D=rail")
printf '%s' "$F" | grep -q 'class="vpb-facet-label">Railroad<' && check ok "a search keeps the matching value" || check no "a search keeps the matching value"
printf '%s' "$F" | grep -q 'class="vpb-facet-label">Schools<' && check no "a search leaves out values that do not match" || check ok "a search leaves out values that do not match"
printf '%s' "$F" | grep -q 'value="rail"' && check ok "the search box keeps the typed text" || check no "the search box keeps the typed text"
printf '%s' "$F" | grep -q 'vpb-facet-clear\|>Clear search<' && check ok "Clear search is offered" || check no "Clear search is offered"
printf '%s' "$F" | grep -q 'find%5Bsubject%5D=rail' && check ok "value links keep the search" || check no "value links keep the search"
Z=$(curl -s "$URL?find%5Bsubject%5D=zzzz")
printf '%s' "$Z" | grep -q 'Clear search' && check ok "a search with no matches still shows the box and Clear search" || check no "a search with no matches still shows the box and Clear search"
echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
