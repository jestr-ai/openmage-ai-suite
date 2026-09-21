#!/usr/bin/env bash
# Logs into the dev admin with curl and checks every AI Suite page renders. Usage: dev/scripts/admin-check.sh [password]
B=${B:-http://ainative-openmage.ddev.site}; PW=${1:-AiSuite-Dev-2026!}; J=/tmp/adm.jar; rm -f $J
FK=$(curl -s -c $J -b $J $B/admin/ | grep -o 'form_key[^>]*value="[A-Za-z0-9]*"' | head -1 | grep -o 'value="[^"]*"' | sed 's/value="//;s/"//')
curl -s -c $J -b $J -o /dev/null -w 'login %{http_code}\n' -X POST "$B/admin/index/index/" --data-urlencode "login[username]=admin" --data-urlencode "login[password]=$PW" --data-urlencode "form_key=$FK"
check() { local out; out=$(curl -s -c $J -b $J -w '\n__HTTP:%{http_code}' "$B/admin/$1"); local code=${out##*__HTTP:}; local body=${out%__HTTP:*}; printf "%-52s HTTP %s " "$1" "$code"
  if echo "$body" | grep -q -i -E "Fatal error|Uncaught|Call to undefined|There has been an error|Collapse vendor frames|login-form"; then echo "ERROR"; echo "$body" | grep -o -i -E "(Fatal error|Call to undefined[^<\"]{0,120}|Exception)[^<]{0,180}" | head -2
  else echo "$body" | grep -q -- "$2" && echo "OK" || { echo "MISSING '$2' (ainative mentions: $(echo "$body" | grep -o -i ainative | wc -l | tr -d ' '))"; }; fi; }
check "ainative_audit/index/" "Audit Log"; check "ainative_token/index/" "MCP Access Tokens"; check "ainative_token/new/" "Runs as Admin User"
check "ainative_ask/index/" "ainative-ask-thread"; check "ainative_job/index/" "AI Content Drafts"; check "ainative_conversation/index/" "Transcripts"
check "system_config/edit/section/ainative/" "Anthropic (Claude)"; check "system_config/edit/section/ainative_mcp/" "Read-only SQL Tool"; check "system_config/edit/section/ainative_copilot/" "Brand"
check "system_config/edit/section/ainative_assistant/" "Chat Widget"; check "system_config/edit/section/ainative_discovery/" "GTIN"
check "catalog_product/edit/id/400/" "ainative-copilot-modal"; check "catalog_category/edit/id/4/" "ainative-copilot-modal"; check "sales_order/view/order_id/193/" "AI Reply Draft"; check "catalog_product/index/" "AI: Generate content drafts"
FK2=$(curl -s -c $J -b $J "$B/admin/ainative_ask/index/" | grep -o -E 'formKey: ?"[A-Za-z0-9]+' | head -1 | sed 's/.*"//')
echo "ask form_key=$FK2"
echo "--- Ask read (mock)"; curl -s -c $J -b $J -X POST "$B/admin/ainative_ask/send/" --data-urlencode "form_key=$FK2" --data-urlencode 'message=[[call:sales_summary {"from":"2013-04-01","to":"2013-04-30","group_by":"none"}]]' --data-urlencode "conversation_id=0" | cut -c1-300; echo
echo "--- Ask write proposal"; curl -s -c $J -b $J -X POST "$B/admin/ainative_ask/send/" --data-urlencode "form_key=$FK2" --data-urlencode 'message=[[call:update_stock {"sku":"hde013","qty":30}]]' --data-urlencode "conversation_id=0" > /tmp/pending.json; python3 -c "import json; d=json.load(open('/tmp/pending.json')); print('pending', [(p['name'],p['arguments']) for p in d.get('pending',[])], 'text', d.get('text','')[:80])"
echo "--- confirm"; read CID KEY < <(python3 -c "import json; d=json.load(open('/tmp/pending.json')); print(d['conversation_id'], d['pending'][0]['key'])"); curl -s -c $J -b $J -X POST "$B/admin/ainative_ask/send/" --data-urlencode "form_key=$FK2" --data-urlencode "message=" --data-urlencode "confirm=$KEY" --data-urlencode "conversation_id=$CID" | cut -c1-250; echo
echo "--- history/load"; curl -s -c $J -b $J "$B/admin/ainative_ask/history/" | cut -c1-200; echo; curl -s -c $J -b $J "$B/admin/ainative_ask/load/id/$CID/" | cut -c1-200; echo
echo "--- copilot generate (mock)"; curl -s -c $J -b $J -X POST "$B/admin/ainative_copilot/generate/" --data-urlencode "form_key=$FK2" --data-urlencode "type=product" --data-urlencode "id=400" --data-urlencode "fields=meta_title,short_description" --data-urlencode "tone=playful" | cut -c1-250; echo
echo "--- copilot category"; curl -s -c $J -b $J -X POST "$B/admin/ainative_copilot/generate/" --data-urlencode "form_key=$FK2" --data-urlencode "type=category" --data-urlencode "id=4" --data-urlencode "fields=description" | cut -c1-200; echo
echo "--- order reply"; curl -s -c $J -b $J -X POST "$B/admin/ainative_copilot/orderReply/" --data-urlencode "form_key=$FK2" --data-urlencode "order_id=193" --data-urlencode "intent=apologise for delay" | cut -c1-200; echo
echo "--- mass generate → job"; curl -s -c $J -b $J -o /dev/null -w 'massGenerate %{http_code}\n' -X POST "$B/admin/ainative_job/massGenerate/" --data-urlencode "form_key=$FK2" --data-urlencode "product[]=400" --data-urlencode "product[]=403" --data-urlencode "fields[]=meta_description" --data-urlencode "tone=minimal"
curl -s -c $J -b $J -o /dev/null -w 'runNow %{http_code}\n' "$B/admin/ainative_job/runNow/"
check "ainative_job/index/" "Draft"
