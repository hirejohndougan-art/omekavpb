#!/bin/bash
# Checks the Search results template (Phase 4) on the test install: a live template
# for the search-results screen is drawn for a search, a search with no matches too,
# and the browse page without a search is not affected. Creates the template through
# the Theme Builder form, makes it live, adds a marker block, and deletes it afterward.
# Needs the admin session in /tmp/jar.txt (see the harness login) and the API key testkey.
BASE=${VPB_BASE:-http://127.0.0.1:8080}
JAR=${VPB_JAR:-/tmp/jar.txt}
AUTH="key_identity=testkey&key_credential=testcred"
NAME="Search check $(tr -dc a-z </dev/urandom | head -c 6)"
MARK="SEARCH TEMPLATE MARK"
fail=0
check() { if [ "$2" = "1" ]; then echo "ok   $1"; else echo "FAIL $1"; fail=1; fi; }

csrf=$(curl -s -b "$JAR" "$BASE/admin/visual-builder/dhs/theme" | grep -o 'name="vpb_theme_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -o /dev/null -b "$JAR" -X POST "$BASE/admin/visual-builder/dhs/theme" --data "vpb_theme_csrf=$csrf&vpb_action=add&screen=search-results&scope=all&name=$NAME"
tid=$(mysql -uroot omeka_test -N -e "SELECT id FROM site_page WHERE title='$NAME' ORDER BY id DESC LIMIT 1")
check "the template is made" "$([ -n "$tid" ] && echo 1 || echo 0)"
curl -s -o /dev/null -b "$JAR" -X POST "$BASE/admin/visual-builder/dhs/theme" --data "vpb_theme_csrf=$csrf&vpb_action=status&template_id=$tid&status=live"
live=$(curl -s -b "$JAR" "$BASE/admin/visual-builder/dhs/theme" | grep -c "$NAME")
check "the template goes live with its starter blocks" "$(mysql -uroot omeka_test -N -e "SELECT COUNT(*) FROM site_page_block WHERE page_id=$tid AND layout IN ('results','pagination')" | awk '{print ($1>=2)?1:0}')"
mysql -uroot omeka_test -e "INSERT INTO site_page_block (page_id, layout, data, position) VALUES ($tid, 'html', '{\"html\":\"<p>$MARK</p>\"}', 99)"

check "a search with matches is drawn with the template" "$(curl -s "$BASE/s/dhs/item?fulltext_search=railroad" | grep -c "$MARK" | awk '{print ($1>=1)?1:0}')"
check "a search with no matches is drawn with the template" "$(curl -s "$BASE/s/dhs/item?fulltext_search=zzqqxxnomatch" | grep -c "$MARK" | awk '{print ($1>=1)?1:0}')"
check "the browse page without a search is not" "$(curl -s "$BASE/s/dhs/item" | grep -c "$MARK" | awk '{print ($1==0)?1:0}')"

curl -s -o /dev/null -w "" -X DELETE "$BASE/api/site_pages/$tid?$AUTH"
check "the test template is removed" "$(mysql -uroot omeka_test -N -e "SELECT COUNT(*) FROM site_page WHERE id=$tid" | awk '{print ($1==0)?1:0}')"
echo "$([ $fail = 0 ] && echo 'search template checks passed' || echo 'search template checks FAILED')"
exit $fail
