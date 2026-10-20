#!/bin/bash
# Checks "Show all" / "Show fewer" on a long text filter (Phase 4) against the test install.
# Creates 12 items with 12 different subjects under one search word, checks the links on
# the browse page, then deletes the items. Needs the test server, site dhs with a live
# Browse items template (see tests/search/browse.sh), and the API key testkey.
BASE=${VPB_BASE:-http://127.0.0.1:8080}
AUTH="key_identity=testkey&key_credential=testcred"
WORD="vpbshowall$(tr -dc a-z </dev/urandom | head -c 8)"  # letters only: the index keeps letter words
fail=0
check() { if [ "$2" = "1" ]; then echo "ok   $1"; else echo "FAIL $1"; fail=1; fi; }

ids=$(python3 - "$BASE" "$AUTH" "$WORD" <<'PY'
import json, sys, urllib.request
base, auth, word = sys.argv[1], sys.argv[2], sys.argv[3]
subj = None
with urllib.request.urlopen(base + '/api/properties?term=dcterms:subject&' + auth) as r:
    subj = json.loads(r.read())[0]['o:id']
out = []
for n in range(12):
    body = {'o:is_public': True, 'dcterms:title': [{'property_id': 1, 'type': 'literal', '@value': f'{word} item {n}'}],
            'dcterms:subject': [{'property_id': subj, 'type': 'literal', '@value': f'{word} subject {n:02d}'}]}
    req = urllib.request.Request(base + '/api/items?' + auth, data=json.dumps(body).encode(),
                                 headers={'Content-Type': 'application/json'}, method='POST')
    with urllib.request.urlopen(req) as r:
        out.append(str(json.loads(r.read())['o:id']))
print(' '.join(out))
PY
)

# Items are added to site dhs here (a site's search only finds items in that site).
for id in $ids; do mysql -uroot omeka_test -e "INSERT IGNORE INTO item_site (item_id, site_id) SELECT $id, id FROM site WHERE slug = 'dhs'"; done

page=$(curl -s "$BASE/s/dhs/item?fulltext_search=$WORD")
check "a long text filter offers Show all with its count" "$(echo "$page" | grep -c 'Show all 12' | sed 's/^[1-9][0-9]*$/1/;s/^0$/0/')"
check "only ten subjects are listed before Show all" "$(echo "$page" | grep -o "subject [0-9][0-9]" | sort -u | wc -l | awk '{print ($1==10)?1:0}')"

expanded=$(curl -s "$BASE/s/dhs/item?fulltext_search=$WORD&show_all%5B%5D=subject")
check "Show all lists every subject" "$(echo "$expanded" | grep -o "subject [0-9][0-9]" | sort -u | wc -l | awk '{print ($1==12)?1:0}')"
check "the expanded list offers Show fewer" "$(echo "$expanded" | grep -c 'Show fewer' | awk '{print ($1>=1)?1:0}')"

for id in $ids; do curl -s -o /dev/null -X DELETE "$BASE/api/items/$id?$AUTH"; done
echo "$([ $fail = 0 ] && echo 'show-all checks passed' || echo 'show-all checks FAILED')"
exit $fail
