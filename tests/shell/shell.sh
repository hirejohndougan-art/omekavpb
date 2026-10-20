#!/usr/bin/env bash
# Friendly admin shell (Phase 6): signing in lands on Home, the sidebar marks the current screen,
# the top-bar search finds items, the help panel links to the glossary, and other admin pages are
# not redirected. Makes its own item
# through the API and removes it.
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
KEY=${VPB_API_KEY:-testkey}; CRED=${VPB_API_CRED:-testcred}
A="key_identity=$KEY&key_credential=$CRED"
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }

ITEM=$(curl -s -X POST "$BASE/api/items?$A" -H 'Content-Type: application/json' -d '{"o:is_public":true,"dcterms:title":[{"type":"literal","property_id":1,"@value":"VPBTEST shell zebra"}]}' | grep -o '"o:id":[0-9]*' | head -1 | cut -d: -f3)
cleanup() { [ -n "$ITEM" ] && curl -s -o /dev/null -X DELETE "$BASE/api/items/$ITEM?$A"; }
trap cleanup EXIT

[ "$(curl -s -b "$JAR" -o /dev/null -w '%{redirect_url}' "$BASE/admin")" = "$BASE/admin/vpb-home" ] && check ok "signing in lands on Home" || check no "signing in lands on Home"
[ "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$BASE/admin/vpb-files")" = 200 ] && check ok "other admin pages are not redirected" || check no "other admin pages are not redirected"
[ "$(curl -s -o /dev/null -w '%{redirect_url}' "$BASE/admin")" = "$BASE/login" ] && check ok "signed-out visitors still go to sign in" || check no "signed-out visitors still go to sign in"

LIST=$(curl -s -b "$JAR" "$BASE/admin/vpb-items")
printf '%s' "$LIST" | grep -q 'vpb-topbar-search' && check ok "the top bar has a search box" || check no "the top bar has a search box"
printf '%s' "$LIST" | grep -q 'Words used here' && check ok "the help panel links to the glossary" || check no "the help panel links to the glossary"

[ "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$BASE/admin/vpb-glossary")" = 200 ] && check ok "the glossary opens" || check no "the glossary opens"
curl -s -b "$JAR" "$BASE/admin/vpb-glossary" | grep -q 'Staff only' && check ok "the glossary explains staff only" || check no "the glossary explains staff only"

curl -s -b "$JAR" "$BASE/admin/vpb-items?q=VPBTEST+shell+zebra" | grep -q 'VPBTEST shell zebra' && check ok "the top-bar search finds an item" || check no "the top-bar search finds an item"

SIDE=$(curl -s -b "$JAR" "$BASE/admin/vpb-items")
printf '%s' "$SIDE" | grep -q 'class="vpb-side"' && check ok "the screens have the sidebar" || check no "the screens have the sidebar"
printf '%s' "$SIDE" | grep -q 'href="[^"]*vpb-items" aria-current="page"' && check ok "the sidebar marks the current screen" || check no "the sidebar marks the current screen"
curl -s -b "$JAR" -o /dev/null "$BASE/admin/vpb-mode?to=expert"
SIDE=$(curl -s -b "$JAR" "$BASE/admin/vpb-items")
printf '%s' "$SIDE" | grep -q 'item list' && check ok "Expert mode links to Omeka's own screens" || check no "Expert mode links to Omeka's own screens"
curl -s -b "$JAR" -o /dev/null "$BASE/admin/vpb-mode?to=simple"

echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
