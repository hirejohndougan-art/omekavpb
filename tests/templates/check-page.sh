#!/usr/bin/env bash
# Check page (plan decision 10): a template missing a required block is listed with
# "Missing" and a Put back button, Put back returns to the Check page and fixes it.
# Needs the test install, the admin cookie jar (JAR, default /tmp/jar.txt) and root
# MySQL access over the unix socket. Creates and deletes its own template.
#   BASE=http://127.0.0.1:8080 JAR=/tmp/jar.txt tests/templates/check-page.sh
set -u
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
KEY=${VPB_API_KEY:-testkey}; CRED=${VPB_API_CRED:-testcred}
SITE=dhs
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
csrf() { grep -o 'name="vpb_theme_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }

THEME=$(curl -s -b "$JAR" "$BASE/admin/visual-builder/$SITE/theme")
TOKEN=$(printf '%s' "$THEME" | csrf)
[ -n "$TOKEN" ] && check ok "theme page has a form token" || check no "theme page has a form token"

# Make an Item template (starts with its required blocks).
curl -s -b "$JAR" -o /dev/null -X POST "$BASE/admin/visual-builder/$SITE/theme" \
  --data-urlencode "vpb_theme_csrf=$TOKEN" -d vpb_action=add -d screen=item -d scope=all \
  --data-urlencode "name=VPBTEST check page" >/dev/null
ID=$(mysql -uroot omeka_test -N -e "SELECT MAX(id) FROM site_page WHERE title='VPBTEST check page'" 2>/dev/null)
[ -n "$ID" ] && check ok "template made (id $ID)" || { check no "template made"; exit 1; }
cleanup() { curl -s -o /dev/null -X DELETE "$BASE/api/site_pages/$ID?key_identity=$KEY&key_credential=$CRED"; }
trap cleanup EXIT

# Remove the Item title block directly (the editor refuses to delete a required block).
mysql -uroot omeka_test -e "DELETE FROM site_page_block WHERE page_id=$ID AND layout='itemTitle'" 2>/dev/null

CHECK=$(curl -s -b "$JAR" "$BASE/admin/visual-builder/$SITE/check")
printf '%s' "$CHECK" | grep -q "VPBTEST check page" && check ok "check page lists the template" || check no "check page lists the template"
printf '%s' "$CHECK" | grep -q "Missing:" && check ok "check page shows Missing" || check no "check page shows Missing"
printf '%s' "$CHECK" | grep -q "Put back" && check ok "check page offers Put back" || check no "check page offers Put back"
printf '%s' "$CHECK" | grep -q 'name="return" value="check"' && check ok "Put back returns to the check page" || check no "Put back returns to the check page"
CTOKEN=$(printf '%s' "$CHECK" | csrf)

LOC=$(curl -s -b "$JAR" -o /dev/null -w '%{redirect_url}' -X POST "$BASE/admin/visual-builder/$SITE/check" \
  --data-urlencode "vpb_theme_csrf=$CTOKEN" -d vpb_action=restore -d template_id=$ID -d return=check)
case "$LOC" in *"/check") check ok "Put back redirects to the check page" ;; *) check no "Put back redirects to the check page ($LOC)" ;; esac
AFTER=$(curl -s -b "$JAR" "$BASE/admin/visual-builder/$SITE/check")
printf '%s' "$AFTER" | grep -q "Required blocks in place" && check ok "after Put back the template is complete" || check no "after Put back the template is complete"

echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
