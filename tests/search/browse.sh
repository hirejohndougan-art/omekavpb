#!/usr/bin/env bash
# HTTP checks for the Browse items screen (plan Phase 4, first slice): the Results and
# Pagination blocks on live "Browse items" and "Browse item sets" templates. Needs a running
# Omeka S test install with VPB active, a site with slug $SITE, and live templates for Browse
# items, Browse item sets and Item set on it, each holding Results and Pagination (the Item set
# template also holds an Item set name and description block). Usage:
#   BASE=http://127.0.0.1:8080 SITE=dhs tests/search/browse.sh
BASE=${BASE:-http://127.0.0.1:8080}
SITE=${SITE:-dhs}
pass=0; fail=0
check() { # name, got, want
  if [ "$2" = "$3" ]; then pass=$((pass+1)); else fail=$((fail+1)); echo "FAIL $1: got '$2' want '$3'"; fi
}
page() { curl -s "$BASE/s/$SITE/item?$1"; }
count() { page "$1" | php -r '$h = stream_get_contents(STDIN); echo preg_match("/vpb-results-count\">([0-9,]+) items/", $h, $m) ? str_replace(",", "", $m[1]) : "none";'; }
cards() { page "$1" | grep -o 'class="vpb-result-title"' | wc -l; }

check "browse page answers" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/s/$SITE/item")" "200"
check "Results block is drawn" "$(page '' | grep -c 'vpb-results-count')" "1"
TOTAL=$(count '')
check "a keyword count is the same on the browse page and the search endpoint" \
  "$(count 'q=lighthouse')" \
  "$(curl -s "$BASE/s/$SITE/vpb-search?q=lighthouse" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["total"];')"
check "nonsense shows the empty message" "$(page 'q=zzzzqqqq' | grep -c 'vpb-results-empty')" "1"
check "a page holds at most per_page cards" "$(cards 'per_page=12&page=1')" "$([ "$TOTAL" -gt 12 ] 2>/dev/null && echo 12 || echo "$TOTAL")"
if [ "$TOTAL" -gt 12 ] 2>/dev/null; then
  check "pagination appears when there is more than one page" "$(page 'per_page=12' | grep -c 'vpb-pagination-numbers')" "1"
fi

setpage() { curl -s "$BASE/s/$SITE/item-set?$1"; }
check "item set browse page answers" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/s/$SITE/item-set")" "200"
check "item set Results block is drawn" "$(setpage '' | grep -c 'vpb-results-count')" "1"
check "nonsense shows the item set empty message" "$(setpage 'q=zzzzqqqq' | grep -c 'No item sets match')" "1"

SET_ID=$(setpage '' | grep -o 'item-set/[0-9]*' | head -1 | cut -d/ -f2)
if [ -n "$SET_ID" ]; then
  check "item set page answers" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/s/$SITE/item-set/$SET_ID")" "200"
  check "item set header names the set" "$(curl -s "$BASE/s/$SITE/item-set/$SET_ID" | grep -c 'vpb-item-set-title')" "1"
  check "item set page counts the set's items" \
    "$(curl -s "$BASE/s/$SITE/item-set/$SET_ID" | grep -o 'vpb-results-count">[0-9,]* [a-z ]*' | head -1 | grep -o '[0-9,]*' | tr -d ',')" \
    "$(page "item_set%5B%5D=$SET_ID" | grep -o 'vpb-results-count">[0-9,]* [a-z ]*' | grep -o '[0-9,]*' | tr -d ',')"
fi

echo "browse checks: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
