#!/usr/bin/env bash
# Import wizard (Phase 8): upload a CSV, match the columns, preview, start CSV Import's job, and
# check the items arrive. Also checks the refusals (no title, not a CSV). Removes what it made.
# Needs the test install with CSV Import active, the admin cookie (JAR, default /tmp/jar.txt),
# the sample file tests/fixtures/railroad-photos.csv and root MySQL over the socket.
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
HERE=$(cd "$(dirname "$0")/.." && pwd)
SAMPLE="$HERE/fixtures/railroad-photos.csv"
KEY=${VPB_API_KEY:-testkey}; CRED=${VPB_API_CRED:-testcred}
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
db() { mysql -uroot omeka_test -N -e "$1" 2>/dev/null; }
field() { grep -o "name=\"$1\" value=\"[^\"]*\"" | head -1 | sed 's/.*value="//;s/"$//'; }
csrf() { grep -o 'name="vpb_import_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
# The first start consumes the uploaded file, so each later import uploads the sample again.
upload_again() {
  local t; t=$(curl -s -b "$JAR" -c "$JAR" "$BASE/admin/vpb-import" | csrf)
  curl -s -b "$JAR" -c "$JAR" -o /tmp/vpb-up-page.html -F "vpb_import_csrf=$t" -F vpb_import_action=upload -F "source=@$SAMPLE;type=text/csv" "$BASE/admin/vpb-import"
  F=$(field file < /tmp/vpb-up-page.html)
}
COLS=(-F "column[0]=catalog" -F "column[1]=title" -F "column[2]=description" -F "column[3]=creator" -F "column[4]=date" -F "column[5]=subject" -F "column[6]=place")

curl -s -b "$JAR" -c "$JAR" "$BASE/admin/vpb-import" > /tmp/vpb-import-page.html
check $([ -s /tmp/vpb-import-page.html ] && grep -q 'Check the columns' /tmp/vpb-import-page.html && echo ok || echo no) "the Import page offers a file to choose"
C=$(csrf < /tmp/vpb-import-page.html)

curl -s -b "$JAR" -c "$JAR" -o /tmp/vpb-map.html -F "vpb_import_csrf=$C" -F vpb_import_action=upload -F "source=@$SAMPLE;type=text/csv" "$BASE/admin/vpb-import"
check $(grep -c '>Matched<' /tmp/vpb-map.html | awk '{print ($1==7)?"ok":"no"}') "all seven columns are matched"
F=$(field file < /tmp/vpb-map.html); C=$(csrf < /tmp/vpb-map.html)

curl -s -b "$JAR" -c "$JAR" -o /tmp/vpb-prev.html -F "vpb_import_csrf=$C" -F vpb_import_action=map -F "file=$F" -F "filename=railroad-photos.csv" -F "duplicate=add" -F "item_set=0" "${COLS[@]}" "$BASE/admin/vpb-import"
check $(grep -q 'Depot at Dickinson' /tmp/vpb-prev.html && echo ok || echo no) "the preview shows the first rows"
C=$(csrf < /tmp/vpb-prev.html)

curl -s -b "$JAR" -c "$JAR" -o /tmp/vpb-nt.html -F "vpb_import_csrf=$C" -F vpb_import_action=map -F "file=$F" -F "filename=railroad-photos.csv" -F "duplicate=add" -F "item_set=0" -F "column[0]=catalog" "$BASE/admin/vpb-import"
grep -q "Choose the column for the title" /tmp/vpb-nt.html && check ok "a file with no title column is refused" || check no "a file with no title column is refused"

curl -s -b "$JAR" -c "$JAR" -o /dev/null -F "vpb_import_csrf=$(csrf < /tmp/vpb-import-page.html)" -F vpb_import_action=upload -F "source=@$HERE/fixtures/events.ics;type=text/calendar;filename=events.ics" "$BASE/admin/vpb-import"
curl -s -b "$JAR" -c "$JAR" "$BASE/admin/vpb-import" | grep -q 'Only CSV and TSV' && check ok "a file that is not CSV or TSV is refused" || check no "a file that is not CSV or TSV is refused"

BEFORE=$(db "SELECT COUNT(*) FROM item")
MAXID=$(db "SELECT COALESCE(MAX(id),0) FROM item")
curl -s -b "$JAR" -c "$JAR" -o /dev/null -F "vpb_import_csrf=$C" -F vpb_import_action=start -F "file=$F" -F "filename=railroad-photos.csv" -F "duplicate=add" -F "item_set=0" "${COLS[@]}" "$BASE/admin/vpb-import"
for i in $(seq 1 30); do [ "$(db "SELECT COUNT(*) FROM item")" -ge $((BEFORE+3)) ] && break; sleep 1; done
check $([ "$(db "SELECT COUNT(*) FROM item")" -eq $((BEFORE+3)) ] && echo ok || echo no) "three items are imported"
check $([ "$(db "SELECT COUNT(*) FROM value WHERE property_id=1 AND value IN ('Depot at Dickinson','Track crew','Engine 7')")" -eq 3 ] && echo ok || echo no) "the imported titles are there"
check $([ "$(db "SELECT COUNT(*) FROM resource WHERE is_public=1 AND id > $MAXID AND resource_type='Omeka\\\\Entity\\\\Item'")" -eq 0 ] && echo ok || echo no) "new items are not public unless asked"
check $([ "$(db "SELECT COUNT(*) FROM job WHERE class LIKE '%CSVImport%Import%' AND status='completed'")" -ge 1 ] && echo ok || echo no) "CSV Import's job ran"

# Update path: the same sheet again, updating the items that now exist, into an item set, public.
SET=$(db "SELECT id FROM item_set ORDER BY id LIMIT 1")
upload_again
C=$(curl -s -b "$JAR" -c "$JAR" "$BASE/admin/vpb-import" | csrf)
curl -s -b "$JAR" -c "$JAR" -o /tmp/vpb-up.html -F "vpb_import_csrf=$C" -F vpb_import_action=map -F "file=$F" -F "filename=railroad-photos.csv" -F "duplicate=update" -F "item_set=$SET" -F "public=1" "${COLS[@]}" "$BASE/admin/vpb-import"
C=$(csrf < /tmp/vpb-up.html)
curl -s -b "$JAR" -c "$JAR" -o /dev/null -F "vpb_import_csrf=$C" -F vpb_import_action=start -F "file=$F" -F "filename=railroad-photos.csv" -F "duplicate=update" -F "item_set=$SET" -F "public=1" "${COLS[@]}" "$BASE/admin/vpb-import"
sleep 6
check $([ "$(db "SELECT COUNT(*) FROM item")" -eq $((BEFORE+3)) ] && echo ok || echo no) "updating does not add new items"
# An update leaves the existing items' item set and visibility as they are (CSV Import sets those on create).
check $([ "$(db "SELECT COUNT(*) FROM item_item_set WHERE item_set_id=$SET AND item_id > $MAXID")" -eq 0 ] && echo ok || echo no) "an update does not change the item set"
check $([ "$(db "SELECT COUNT(*) FROM resource WHERE is_public=1 AND id > $MAXID AND resource_type='Omeka\\\\Entity\\\\Item'")" -eq 0 ] && echo ok || echo no) "an update does not change visibility"

# A new import into an item set, public: the three items are added there and are public.
MID=$(db "SELECT COALESCE(MAX(id),0) FROM item")
upload_again
C=$(curl -s -b "$JAR" -c "$JAR" "$BASE/admin/vpb-import" | csrf)
curl -s -b "$JAR" -c "$JAR" -o /tmp/vpb-new.html -F "vpb_import_csrf=$C" -F vpb_import_action=map -F "file=$F" -F "filename=railroad-photos.csv" -F "duplicate=add" -F "item_set=$SET" -F "public=1" "${COLS[@]}" "$BASE/admin/vpb-import"
C=$(csrf < /tmp/vpb-new.html)
curl -s -b "$JAR" -c "$JAR" -o /tmp/vpb-start.html -w "start %{http_code}\n" -F "vpb_import_csrf=$C" -F vpb_import_action=start -F "file=$F" -F "filename=railroad-photos.csv" -F "duplicate=add" -F "item_set=$SET" -F "public=1" "${COLS[@]}" "$BASE/admin/vpb-import"
for i in $(seq 1 30); do [ "$(db "SELECT COUNT(*) FROM item WHERE id > $MID")" -ge 3 ] && break; sleep 1; done
check $([ "$(db "SELECT COUNT(*) FROM item_item_set WHERE item_set_id=$SET AND item_id > $MID")" -eq 3 ] && echo ok || echo no) "new items go into the chosen item set"
check $([ "$(db "SELECT COUNT(*) FROM resource WHERE is_public=1 AND id > $MID AND resource_type='Omeka\\\\Entity\\\\Item'")" -eq 3 ] && echo ok || echo no) "new items are public when asked"

# Remove the three imported items.
for id in $(db "SELECT id FROM item WHERE id > $MAXID"); do curl -s -o /dev/null -X DELETE "$BASE/api/items/$id?key_identity=$KEY&key_credential=$CRED"; done
echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
