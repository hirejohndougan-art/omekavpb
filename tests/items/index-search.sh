#!/usr/bin/env bash
# Admin search (Phase 6): the Items search uses the VPB Search index, so it finds staff-only and
# draft items as well as public ones. Makes its own items and
# removes them.
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
KEY=${VPB_API_KEY:-testkey}; CRED=${VPB_API_CRED:-testcred}
A="key_identity=$KEY&key_credential=$CRED"
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
db() { mysql -uroot omeka_test -N -e "$1" 2>/dev/null; }
make() { curl -s -X POST "$BASE/api/items?$A" -H 'Content-Type: application/json' -d "$1" | grep -o '"o:id":[0-9]*' | head -1 | cut -d: -f3; }
PRIV=$(make '{"o:is_public":false,"o:site":[{"o:id":1}],"dcterms:title":[{"type":"literal","property_id":1,"@value":"VPBTEST indexfind private"}]}')
PUB=$(make '{"o:is_public":true,"o:site":[{"o:id":1}],"dcterms:title":[{"type":"literal","property_id":1,"@value":"VPBTEST indexfind public"}]}')
cleanup() { for i in $PRIV $PUB; do curl -s -o /dev/null -X DELETE "$BASE/api/items/$i?$A"; done; }
trap cleanup EXIT
[ -n "$PRIV" ] && [ -n "$PUB" ] && check ok "test items made" || { check no "test items made"; exit 1; }

LIST=$(curl -s -b "$JAR" "$BASE/admin/vpb-items?q=VPBTEST+indexfind")
printf '%s' "$LIST" | grep -q "VPBTEST indexfind private" && check ok "admin search finds a staff-only item" || check no "admin search finds a staff-only item"
printf '%s' "$LIST" | grep -q "VPBTEST indexfind public" && check ok "admin search finds a public item" || check no "admin search finds a public item"


curl -s -b "$JAR" "$BASE/admin/vpb-items?q=VPBTEST+nothingmatchesthis" | grep -q "VPBTEST indexfind" && check no "a search with no match shows nothing" || check ok "a search with no match shows nothing"

echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
