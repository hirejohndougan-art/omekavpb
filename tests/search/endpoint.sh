#!/usr/bin/env bash
# HTTP checks for the public VPB Search endpoint (plan Phase 3).
# Needs a running Omeka S test install with VPB active, a site with slug $SITE,
# and an index built by the reindex job. Usage:
#   BASE=http://127.0.0.1:8080 SITE=dhs tests/search/endpoint.sh
BASE=${BASE:-http://127.0.0.1:8080}
SITE=${SITE:-dhs}
pass=0; fail=0
json() { php -r '$d = json_decode(stream_get_contents(STDIN), true); echo $d[$argv[1]] ?? "";' "$1"; }
check() { # name, got, want
  if [ "$2" = "$3" ]; then pass=$((pass+1)); else fail=$((fail+1)); echo "FAIL $1: got '$2' want '$3'"; fi
}
get() { curl -s "$BASE/s/$SITE/vpb-search?$1"; }

check "index mode" "$(get 'q=lighthouse' | json mode)" "index"
# Item 250 is staff-only in the test data (every fifth item is private).
check "staff-only item is hidden from visitors" "$(get 'q=reindex%20item%20250' | json total)" "0"
check "per_page is limited to 12, 24 or 48" "$(get 'q=lighthouse&per_page=7' | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["per_page"];')" "12"
check "a word typed partly still matches" "$(get 'q=lighthous' | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["total"]>0?"yes":"no";')" "yes"
check "nonsense finds nothing" "$(get 'q=zzzzqqqq' | json total)" "0"

# A facet count matches the filtered result (data-independent: reads the first facet value).
FACET_ID=$(get 'q=lighthouse' | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["facets"]["item_set"][0]["id"] ?? "";')
if [ -n "$FACET_ID" ]; then
  COUNT=$(get 'q=lighthouse' | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["facets"]["item_set"][0]["count"];')
  FILTERED=$(get "q=lighthouse&item_set[]=$FACET_ID" | json total)
  check "facet count equals the filtered total" "$FILTERED" "$COUNT"
fi

echo "endpoint checks: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
