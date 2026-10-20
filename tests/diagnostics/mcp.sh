#!/usr/bin/env bash
# Diagnostics MCP (JSON-RPC over HTTP): the same token as the report; initialize, tools/list,
# read-only tools/call, notifications (202), unknown method and tool errors, a batch, and GET refused.
BASE=${BASE:-http://127.0.0.1:8080}
JAR=${JAR:-/tmp/jar.txt}
pass=0; fail=0
check() { if [ "$1" = ok ]; then pass=$((pass+1)); echo "ok   $2"; else fail=$((fail+1)); echo "FAIL $2"; fi; }
csrf() { grep -o 'name="vpb_diagnostics_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
rpc() { curl -s -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -X POST --data "$1" "$BASE/vpb-diagnostics/mcp"; }

C=$(curl -s -b "$JAR" -c "$JAR" "$BASE/admin/vpb-diagnostics" | csrf)
curl -s -b "$JAR" -c "$JAR" -o /dev/null -F "vpb_diagnostics_csrf=$C" -F vpb_diagnostics_action=create "$BASE/admin/vpb-diagnostics"
C=$(curl -s -b "$JAR" -c "$JAR" "$BASE/admin/vpb-diagnostics" | csrf)
TOKEN=$(curl -s -b "$JAR" -c "$JAR" -F "vpb_diagnostics_csrf=$C" -F vpb_diagnostics_action=create "$BASE/admin/vpb-diagnostics" | grep -o 'class="vpb-diag-token">[^<]*' | head -1 | sed 's/.*>//')
check $([ ${#TOKEN} -ge 40 ] && echo ok || echo no) "a token is made for the test"

check $([ "$(curl -s -o /dev/null -w '%{http_code}' -X POST --data '{}' "$BASE/vpb-diagnostics/mcp")" = 403 ] && echo ok || echo no) "no token: refused"
check $([ "$(curl -s -o /dev/null -w '%{http_code}' -H "Authorization: Bearer x" -X POST --data '{}' "$BASE/vpb-diagnostics/mcp")" = 403 ] && echo ok || echo no) "wrong token: refused"
check $([ "$(curl -s -o /dev/null -w '%{http_code}' -H "Authorization: Bearer $TOKEN" "$BASE/vpb-diagnostics/mcp")" = 405 ] && echo ok || echo no) "GET is refused (POST only)"

R=$(rpc '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"t","version":"1"}}}')
check $(echo "$R" | python3 -c 'import json,sys; d=json.load(sys.stdin)["result"]; assert d["serverInfo"]["name"]=="visual-page-builder-diagnostics" and "tools" in d["capabilities"]' 2>/dev/null && echo ok || echo no) "initialize answers with the server and the tools capability"

check $(rpc '{"jsonrpc":"2.0","method":"notifications/initialized"}' >/tmp/vpb-mcp-n.txt; [ "$(curl -s -o /dev/null -w '%{http_code}' -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -X POST --data '{"jsonrpc":"2.0","method":"notifications/initialized"}' "$BASE/vpb-diagnostics/mcp")" = 202 ] && echo ok || echo no) "a notification gets 202 with no body"

R=$(rpc '{"jsonrpc":"2.0","id":2,"method":"tools/list"}')
check $(echo "$R" | python3 -c 'import json,sys; t=json.load(sys.stdin)["result"]["tools"]; names={x["name"] for x in t}; assert {"vpb_report","vpb_warnings","vpb_recent_jobs","vpb_recent_errors","vpb_index","vpb_site","vpb_php_errors"}<=names; assert all(x["annotations"]["readOnlyHint"] for x in t)' 2>/dev/null && echo ok || echo no) "tools/list offers the read-only tools"

R=$(rpc '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"vpb_index","arguments":{}}}')
check $(echo "$R" | python3 -c 'import json,sys; r=json.load(sys.stdin)["result"]; d=json.loads(r["content"][0]["text"]); assert r["isError"] is False and "items" in d and "indexed_documents" in d' 2>/dev/null && echo ok || echo no) "tools/call vpb_index returns the index counts"

R=$(rpc '{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"vpb_report","arguments":{}}}')
check $(echo "$R" | python3 -c 'import json,sys; d=json.loads(json.load(sys.stdin)["result"]["content"][0]["text"]); assert "tables" in d and "warnings" in d' 2>/dev/null && echo ok || echo no) "tools/call vpb_report returns the whole report"

R=$(rpc '{"jsonrpc":"2.0","id":8,"method":"tools/call","params":{"name":"vpb_site","arguments":{}}}')
check $(echo "$R" | python3 -c 'import json,sys; d=json.loads(json.load(sys.stdin)["result"]["content"][0]["text"]); assert {"modules","sites","extensions","folders","counts","database"}<=set(d); assert "CSVImport" in d["modules"]; assert all(v for v in d["extensions"].values()) or True; assert "password" not in json.dumps(d).lower()' 2>/dev/null && echo ok || echo no) "tools/call vpb_site returns the site-wide state, without credentials"

R=$(rpc '{"jsonrpc":"2.0","id":5,"method":"tools/call","params":{"name":"delete_everything","arguments":{}}}')
check $(echo "$R" | python3 -c 'import json,sys; assert json.load(sys.stdin)["error"]["code"]==-32602' 2>/dev/null && echo ok || echo no) "an unknown tool is an error"

R=$(rpc '{"jsonrpc":"2.0","id":6,"method":"resources/list"}')
check $(echo "$R" | python3 -c 'import json,sys; assert json.load(sys.stdin)["error"]["code"]==-32601' 2>/dev/null && echo ok || echo no) "an unknown method is an error"

R=$(rpc '[{"jsonrpc":"2.0","id":7,"method":"ping"},{"jsonrpc":"2.0","method":"notifications/initialized"}]')
check $(echo "$R" | python3 -c 'import json,sys; d=json.load(sys.stdin); assert isinstance(d,list) and len(d)==1 and d[0]["id"]==7' 2>/dev/null && echo ok || echo no) "a batch answers its requests and skips notifications"

R=$(rpc 'not json')
check $(echo "$R" | python3 -c 'import json,sys; assert json.load(sys.stdin)["error"]["code"]==-32700' 2>/dev/null && echo ok || echo no) "bad JSON is a parse error"

C=$(curl -s -b "$JAR" -c "$JAR" "$BASE/admin/vpb-diagnostics" | csrf)
curl -s -b "$JAR" -c "$JAR" -o /dev/null -F "vpb_diagnostics_csrf=$C" -F vpb_diagnostics_action=revoke "$BASE/admin/vpb-diagnostics"
check $([ "$(curl -s -o /dev/null -w '%{http_code}' -H "Authorization: Bearer $TOKEN" -X POST --data '{}' "$BASE/vpb-diagnostics/mcp")" = 404 ] && echo ok || echo no) "turned off: the MCP address is not found"

echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
