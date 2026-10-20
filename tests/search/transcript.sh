#!/usr/bin/env bash
# Yes/no facet "Has a transcript" (Index::TRANSCRIPT_TERM, default bibo:content).
# Needs the test install with the seed fixtures (tests/lib/seed.js: "Oral history interview").
BASE=${BASE:-http://127.0.0.1:8080}
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
P=$(curl -s "$BASE/s/dhs/item")
printf '%s' "$P" | grep -q "Has a transcript" && check ok "the Has a transcript yes/no choice is offered" || check no "the Has a transcript yes/no choice is offered"
F=$(curl -s "$BASE/s/dhs/item?has_transcript%5B%5D=1")
printf '%s' "$F" | grep -q "Oral history interview" && check ok "filtering keeps the oral history item" || check no "filtering keeps the oral history item"
printf '%s' "$F" | grep -q "Railroad photo 0" && check no "filtering leaves out items without a transcript" || check ok "filtering leaves out items without a transcript"
printf '%s' "$F" | grep -q "Filtered by" && check ok "the choice shows in Filtered by" || check no "the choice shows in Filtered by"
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
