#!/usr/bin/env bash
# Home (Phase 6): the counts on the page match the database. Read only.
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
db() { mysql -uroot omeka_test -N -e "$1" 2>/dev/null; }
P=$(curl -s -b "$JAR" "$BASE/admin/vpb-home")
printf '%s' "$P" | grep -q "Needs attention" && check ok "Home has Needs attention" || check no "Home has Needs attention"
printf '%s' "$P" | grep -q "Recently changed" && check ok "Home has Recently changed" || check no "Home has Recently changed"
DATE=$(db "SELECT p.id FROM property p JOIN vocabulary v ON v.id=p.vocabulary_id WHERE v.prefix='dcterms' AND p.local_name='date'")
NOFILE=$(db "SELECT COUNT(*) FROM item i WHERE NOT EXISTS (SELECT 1 FROM media m WHERE m.item_id=i.id)")
NODATE=$(db "SELECT COUNT(*) FROM item i WHERE NOT EXISTS (SELECT 1 FROM value v WHERE v.resource_id=i.id AND v.property_id=$DATE AND v.value<>'')")
PRIV=$(db "SELECT COUNT(*) FROM item i JOIN resource r ON r.id=i.id WHERE r.is_public=0")
ITEMS=$(db "SELECT COUNT(*) FROM item")
printf '%s' "$P" | grep -q ">$(printf '%s' "$NOFILE" | sed ':a;s/\B[0-9]\{3\}\>/,&/;ta')<" && check ok "items with no file ($NOFILE) match" || check no "items with no file ($NOFILE) match"
printf '%s' "$P" | grep -q ">$(printf '%s' "$NODATE" | sed ':a;s/\B[0-9]\{3\}\>/,&/;ta')<" && check ok "items with no date ($NODATE) match" || check no "items with no date ($NODATE) match"
printf '%s' "$P" | grep -q ">$(printf '%s' "$PRIV" | sed ':a;s/\B[0-9]\{3\}\>/,&/;ta')<" && check ok "private items ($PRIV) match" || check no "private items ($PRIV) match"
printf '%s' "$P" | grep -q ">$(printf '%s' "$ITEMS" | sed ':a;s/\B[0-9]\{3\}\>/,&/;ta')<" && check ok "items ($ITEMS) match" || check no "items ($ITEMS) match"
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
