#!/usr/bin/env bash
# Diagnostics: the report is off without a token (not found), refuses a missing or wrong
# token, and with the right token returns the JSON report without the token or its hash in it.
# Turning it off again makes it not found. Needs the test install and the admin cookie (JAR).
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
csrf() { grep -o 'name="vpb_diagnostics_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
code() { curl -s -o "$1" -w '%{http_code}' "${@:2}"; }

# Start from off.
C=$(curl -s -b "$JAR" -c "$JAR" "$BASE/admin/vpb-diagnostics" | csrf)
curl -s -b "$JAR" -c "$JAR" -o /dev/null -F "vpb_diagnostics_csrf=$C" -F vpb_diagnostics_action=revoke "$BASE/admin/vpb-diagnostics"
check $([ "$(code /tmp/vpb-d1.json "$BASE/vpb-diagnostics")" = 404 ] && echo ok || echo no) "off: the report address is not found"

# Make a token.
C=$(curl -s -b "$JAR" -c "$JAR" "$BASE/admin/vpb-diagnostics" | csrf)
TOKEN=$(curl -s -b "$JAR" -c "$JAR" -F "vpb_diagnostics_csrf=$C" -F vpb_diagnostics_action=create "$BASE/admin/vpb-diagnostics" | grep -o 'class="vpb-diag-token">[^<]*' | head -1 | sed 's/.*>//')
check $([ ${#TOKEN} -ge 40 ] && echo ok || echo no) "a token is shown once when made"

check $([ "$(code /tmp/vpb-d2.json "$BASE/vpb-diagnostics")" = 403 ] && echo ok || echo no) "no token: refused"
check $([ "$(code /tmp/vpb-d3.json -H 'Authorization: Bearer wrong' "$BASE/vpb-diagnostics")" = 403 ] && echo ok || echo no) "wrong token: refused"
check $([ "$(code /tmp/vpb-d4.json -H "Authorization: Bearer $TOKEN" "$BASE/vpb-diagnostics")" = 200 ] && echo ok || echo no) "right token: the report"

check $(python3 -c 'import json,sys; d=json.load(open("/tmp/vpb-d4.json")); assert "visual_page_builder" in d and "tables" in d and "warnings" in d and "jobs" in d and "recent_errors" in d' 2>/dev/null && echo ok || echo no) "the report has its sections"
check $(grep -q "$TOKEN" /tmp/vpb-d4.json && echo no || echo ok) "the report does not contain the token"
check $(grep -q "$(printf '%s' "$TOKEN" | sha256sum | cut -d' ' -f1)" /tmp/vpb-d4.json && echo no || echo ok) "the report does not contain the stored hash"
check $(grep -q 'testpass-local' /tmp/vpb-d4.json && echo no || echo ok) "the report does not contain a password"

# Turn it off: not found again.
C=$(curl -s -b "$JAR" -c "$JAR" "$BASE/admin/vpb-diagnostics" | csrf)
curl -s -b "$JAR" -c "$JAR" -o /dev/null -F "vpb_diagnostics_csrf=$C" -F vpb_diagnostics_action=revoke "$BASE/admin/vpb-diagnostics"
check $([ "$(code /tmp/vpb-d5.json -H "Authorization: Bearer $TOKEN" "$BASE/vpb-diagnostics")" = 404 ] && echo ok || echo no) "turned off: the old token no longer works"

echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
