#!/usr/bin/env bash
# Edit questions (Phase 7): a question's label, help text and Needed flag are saved to the
# resource template, and Add an item asks the question with them. Makes its own template and
# item and removes them, and sets the question template back to none.
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
KEY=${VPB_API_KEY:-testkey}; CRED=${VPB_API_CRED:-testcred}
A="key_identity=$KEY&key_credential=$CRED"
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
db() { mysql -uroot omeka_test -N -e "$1" 2>/dev/null; }
token() { curl -s -b "$JAR" "$BASE/admin/vpb-questions?template=$TPL" | grep -o 'name="vpb_questions_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }

DATE=$(db "SELECT p.id FROM property p JOIN vocabulary v ON v.id=p.vocabulary_id WHERE v.prefix='dcterms' AND p.local_name='date'")
TITLE=1
TPL=$(curl -s -X POST "$BASE/api/resource_templates?$A" -H 'Content-Type: application/json' -d "{\"o:label\":\"VPBTEST questions\",\"o:resource_template_property\":[{\"o:property\":{\"o:id\":$TITLE}},{\"o:property\":{\"o:id\":$DATE}}]}" | grep -o '"o:id":[0-9]*' | head -1 | cut -d: -f3)
ITEM=$(curl -s -X POST "$BASE/api/items?$A" -H 'Content-Type: application/json' -d '{"o:is_public":false,"dcterms:title":[{"type":"literal","property_id":1,"@value":"VPBTEST questions item"}]}' | grep -o '"o:id":[0-9]*' | head -1 | cut -d: -f3)
cleanup() {
  curl -s -o /dev/null -b "$JAR" -X POST "$BASE/admin/vpb-questions?template=0" --data-urlencode "vpb_questions_csrf=$(token)" -d template=0
  [ -n "$ITEM" ] && curl -s -o /dev/null -X DELETE "$BASE/api/items/$ITEM?$A"
  [ -n "$TPL" ] && curl -s -o /dev/null -X DELETE "$BASE/api/resource_templates/$TPL?$A"
}
trap cleanup EXIT
[ -n "$TPL" ] && [ -n "$ITEM" ] && check ok "test template and item made" || { check no "test template and item made"; exit 1; }

curl -s -b "$JAR" "$BASE/admin/vpb-questions?template=$TPL" | grep -q "VPBTEST" ; curl -s -b "$JAR" "$BASE/admin/vpb-questions?template=$TPL" | grep -q "Save questions" && check ok "the screen lists the template's questions" || check no "the screen lists the template's questions"

curl -s -b "$JAR" -o /dev/null -X POST "$BASE/admin/vpb-questions" --data-urlencode "vpb_questions_csrf=$(token)" -d template=$TPL \
  --data-urlencode "label[$DATE]=When was it made" --data-urlencode "comment[$DATE]=A year is enough" -d "needed[$DATE]=1"
[ "$(db "SELECT alternate_label FROM resource_template_property WHERE resource_template_id=$TPL AND property_id=$DATE")" = "When was it made" ] && check ok "the label is saved to the template" || check no "the label is saved to the template"
[ "$(db "SELECT alternate_comment FROM resource_template_property WHERE resource_template_id=$TPL AND property_id=$DATE")" = "A year is enough" ] && check ok "the help text is saved to the template" || check no "the help text is saved to the template"
[ "$(db "SELECT is_required FROM resource_template_property WHERE resource_template_id=$TPL AND property_id=$DATE")" = 1 ] && check ok "Needed is saved to the template" || check no "Needed is saved to the template"
[ "$(db "SELECT COUNT(*) FROM resource_template_property WHERE resource_template_id=$TPL")" = 2 ] && check ok "the other questions are kept" || check no "the other questions are kept"

ADD=$(curl -s -b "$JAR" "$BASE/admin/vpb-add?item=$ITEM&step=2")
printf '%s' "$ADD" | grep -q "When was it made" && check ok "Add an item asks the question with its new label" || check no "Add an item asks the question with its new label"
printf '%s' "$ADD" | grep -q "A year is enough" && check ok "Add an item shows the help text" || check no "Add an item shows the help text"
printf "%s" "$ADD" | grep -q "When was it made.*(needed)" && check ok "Add an item marks a needed question" || check no "Add an item marks a needed question"

echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
