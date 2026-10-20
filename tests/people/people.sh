#!/usr/bin/env bash
# People screen (Phase 9): invite (creates an inactive account, then tries Omeka's activation
# email), change another person's role, refuse your own role change. Makes and removes its own
# account. Needs the test install, the admin cookie (JAR, default /tmp/jar.txt) and root MySQL.
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
KEY=${VPB_API_KEY:-testkey}; CRED=${VPB_API_CRED:-testcred}
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
db() { mysql -uroot omeka_test -N -e "$1" 2>/dev/null; }
token() { curl -s -b "$JAR" "$BASE/admin/vpb-people" | grep -o 'name="vpb_people_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
post() { curl -s -b "$JAR" -o /dev/null -w '%{http_code}' -X POST "$BASE/admin/vpb-people" --data-urlencode "vpb_people_csrf=$(token)" "$@"; }
EMAIL=vpbtest-invite@example.org
db "DELETE FROM user WHERE email='$EMAIL'"

PAGE=$(curl -s -b "$JAR" "$BASE/admin/vpb-people")
printf '%s' "$PAGE" | grep -q "What each role can do" && check ok "the screen explains the roles" || check no "the screen explains the roles"
printf '%s' "$PAGE" | grep -q "(you)" && check ok "your own row says (you)" || check no "your own row says (you)"

CODE=$(post -d vpb_action=invite -d name="VPBTEST invite" -d email=$EMAIL -d role=reviewer)
[ "$CODE" = 302 ] && check ok "invite redirects back to People" || check no "invite redirects back to People ($CODE)"
[ "$(db "SELECT role FROM user WHERE email='$EMAIL'")" = reviewer ] && check ok "the invited person has the chosen role" || check no "the invited person has the chosen role"
[ "$(db "SELECT is_active FROM user WHERE email='$EMAIL'")" = 0 ] && check ok "the invitation is inactive until accepted" || check no "the invitation is inactive until accepted"
INVITED=$(db "SELECT id FROM user WHERE email='$EMAIL'")

post -d user_id=$INVITED -d role=editor >/dev/null
[ "$(db "SELECT role FROM user WHERE id=$INVITED")" = editor ] && check ok "another person's role can be changed" || check no "another person's role can be changed"

post -d user_id=1 -d role=author >/dev/null
[ "$(db "SELECT role FROM user WHERE id=1")" = global_admin ] && check ok "your own role cannot be changed" || check no "your own role cannot be changed"

post -d vpb_action=invite -d name=Bad -d email=not-an-email -d role=author >/dev/null
[ "$(db "SELECT COUNT(*) FROM user WHERE email='not-an-email'")" = 0 ] && check ok "an invalid email is refused" || check no "an invalid email is refused"

curl -s -o /dev/null -X DELETE "$BASE/api/users/$INVITED?key_identity=$KEY&key_credential=$CRED"
[ "$(db "SELECT COUNT(*) FROM user WHERE email='$EMAIL'")" = 0 ] && check ok "the test account is removed" || check no "the test account is removed"

echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
