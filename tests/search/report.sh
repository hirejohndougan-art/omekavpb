#!/usr/bin/env bash
# Search report (Phase 6): public searches are logged with their result count, and Home shows
# the top searches and the searches that found nothing. Makes its own item and removes it, and
# removes its own log rows.
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
KEY=${VPB_API_KEY:-testkey}; CRED=${VPB_API_CRED:-testcred}
A="key_identity=$KEY&key_credential=$CRED"
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
db() { mysql -uroot omeka_test -N -e "$1" 2>/dev/null; }
search() { curl -s -o /dev/null "$BASE/s/dhs/item?fulltext_search=$1"; }

FOUND="vpbtest reportfind"
EMPTY="vpbtest reportnothing"
ITEM=$(curl -s -X POST "$BASE/api/items?$A" -H 'Content-Type: application/json' -d '{"o:is_public":true,"o:site":[{"o:id":1}],"dcterms:title":[{"type":"literal","property_id":1,"@value":"VPBTEST reportfind item"}]}' | grep -o '"o:id":[0-9]*' | head -1 | cut -d: -f3)
cleanup() { [ -n "$ITEM" ] && curl -s -o /dev/null -X DELETE "$BASE/api/items/$ITEM?$A"; db "DELETE FROM vpb_search_log WHERE term LIKE 'vpbtest report%'"; }
trap cleanup EXIT
db "DELETE FROM vpb_search_log WHERE term LIKE 'vpbtest report%'"

search "VPBTEST+reportfind"; search "VPBTEST+reportfind"
search "VPBTEST+reportnothing"
[ "$(db "SELECT COUNT(*) FROM vpb_search_log WHERE term='$FOUND'")" = 2 ] && check ok "each search with results is logged" || check no "each search with results is logged"
[ "$(db "SELECT MIN(total) FROM vpb_search_log WHERE term='$FOUND'")" -ge 1 ] && check ok "a search with results is logged with its count" || check no "a search with results is logged with its count"
[ "$(db "SELECT total FROM vpb_search_log WHERE term='$EMPTY'")" = 0 ] && check ok "a search with no results is logged as empty" || check no "a search with no results is logged as empty"

HOME=$(curl -s -b "$JAR" "$BASE/admin/vpb-home")
printf '%s' "$HOME" | grep -q "Searches that found nothing" && check ok "Home has the search report" || check no "Home has the search report"
printf '%s' "$HOME" | grep -q "vpbtest reportnothing" && check ok "Home lists the empty search" || check no "Home lists the empty search"
printf '%s' "$HOME" | grep -q "vpbtest reportfind" && check ok "Home lists the top search" || check no "Home lists the top search"

echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
