#!/usr/bin/env bash
# Phase 6 shift templates smoke test: happy path, computed hours, shift rules, isolation, filters.
set -u
API="http://127.0.0.1:8899/api"
STAMP=$(date +%s)
PASS=0
FAIL=0

check() { # check <label> <expected> <actual>
	if [ "$2" = "$3" ]; then
		PASS=$((PASS + 1)); printf 'ok    %-52s %s\n' "$1" "$3"
	else
		FAIL=$((FAIL + 1)); printf 'FAIL  %-52s expected %s got %s\n' "$1" "$2" "$3"
	fi
}

jqv() { node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{try{const o=JSON.parse(s);const p=process.argv[1].split(".");let v=o;for(const k of p)v=v?.[k];console.log(typeof v==="object"?JSON.stringify(v):v)}catch(e){console.log("")}})' "$1"; }

post() { curl -s -X POST "$API$1" -H "Content-Type: application/json" -H "Accept: application/json" ${2:+-H "Authorization: Bearer $2"} -d "$3"; }
put()  { curl -s -X PUT  "$API$1" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $2" -d "$3"; }
get()  { curl -s "$API$1" -H "Accept: application/json" -H "Authorization: Bearer $2"; }
code() { curl -s -o /dev/null -w "%{http_code}" -X "$1" "$API$2" -H "Content-Type: application/json" -H "Accept: application/json" -H "Authorization: Bearer $3" ${4:+-d "$4"}; }

# ---------- store A ----------
TOKEN_A=$(post /auth/register "" "{\"name\":\"Manager A\",\"email\":\"ta$STAMP@qrs.test\",\"password\":\"password123\"}" | jqv data.token)
[ -n "$TOKEN_A" ] || { echo "register A failed"; exit 1; }
post /store "$TOKEN_A" '{"store_name":"Burger Loop","timezone":"Asia/Manila","week_starts_on":0,"max_consecutive_duty_days":6}' > /dev/null

# ---------- store B (isolation fixture) ----------
TOKEN_B=$(post /auth/register "" "{\"name\":\"Manager B\",\"email\":\"tb$STAMP@qrs.test\",\"password\":\"password123\"}" | jqv data.token)
post /store "$TOKEN_B" '{"store_name":"Fry Town","timezone":"Asia/Manila","week_starts_on":1,"max_consecutive_duty_days":6}' > /dev/null
B_STAMP_BEFORE=$(get /auth/me "$TOKEN_B" | jqv data.store.onboarding_completed_at)

echo "--- happy path ---"
T1=$(post /shift-templates "$TOKEN_A" '{"template_name":"Opening","template_code":"op","start_time":"06:00","end_time":"14:00","break_minutes":60,"applies_to":"both","color_hex":"#2563eb","sort_order":1}')
T1_ID=$(echo "$T1" | jqv data.id)
check "template created"                  "Opening" "$(echo "$T1" | jqv data.template_name)"
check "code upper-cased"                  "OP" "$(echo "$T1" | jqv data.template_code)"
check "times trimmed to HH:MM"            "06:00" "$(echo "$T1" | jqv data.start_time)"
check "total_hours computed net of break" "7" "$(echo "$T1" | jqv data.total_hours)"
check "crosses_midnight false"            "false" "$(echo "$T1" | jqv data.crosses_midnight)"
check "color_hex upper-cased"             "#2563EB" "$(echo "$T1" | jqv data.color_hex)"

GRAVE=$(post /shift-templates "$TOKEN_A" '{"template_name":"Graveyard","template_code":"GY","start_time":"22:00","end_time":"06:00","break_minutes":30,"applies_to":"crew"}')
check "overnight crosses_midnight"        "true" "$(echo "$GRAVE" | jqv data.crosses_midnight)"
check "overnight total_hours"             "7.5" "$(echo "$GRAVE" | jqv data.total_hours)"

NOBRK=$(post /shift-templates "$TOKEN_A" '{"template_name":"Short","start_time":"11:00","end_time":"15:00","applies_to":"crew","break_minutes":0}')
check "zero break keeps full hours"       "4" "$(echo "$NOBRK" | jqv data.total_hours)"
check "color defaults when omitted"       "#2563EB" "$(echo "$NOBRK" | jqv data.color_hex)"

echo "--- validation (block) ---"
check "duplicate name -> 400"        "400" "$(code POST /shift-templates "$TOKEN_A" '{"template_name":"Opening","start_time":"07:00","end_time":"15:00","applies_to":"both"}')"
check "equal start/end -> 400"       "400" "$(code POST /shift-templates "$TOKEN_A" '{"template_name":"Zero","start_time":"08:00","end_time":"08:00","applies_to":"both"}')"
check "break >= duration -> 400"     "400" "$(code POST /shift-templates "$TOKEN_A" '{"template_name":"AllBreak","start_time":"08:00","end_time":"09:00","break_minutes":60,"applies_to":"both"}')"
check "bad time format -> 400"       "400" "$(code POST /shift-templates "$TOKEN_A" '{"template_name":"BadTime","start_time":"6am","end_time":"14:00","applies_to":"both"}')"
check "bad applies_to -> 400"        "400" "$(code POST /shift-templates "$TOKEN_A" '{"template_name":"BadWho","start_time":"06:00","end_time":"14:00","applies_to":"chef"}')"
check "bad color -> 400"             "400" "$(code POST /shift-templates "$TOKEN_A" '{"template_name":"BadColor","start_time":"06:00","end_time":"14:00","applies_to":"both","color_hex":"blue"}')"
check "break over 480 -> 400"        "400" "$(code POST /shift-templates "$TOKEN_A" '{"template_name":"LongBreak","start_time":"00:00","end_time":"23:00","break_minutes":600,"applies_to":"both"}')"

echo "--- update ---"
UPD=$(put "/shift-templates/$T1_ID" "$TOKEN_A" '{"template_name":"Opening","template_code":"OP","start_time":"05:30","end_time":"13:30","break_minutes":45,"applies_to":"manager"}')
check "update recomputes total_hours"     "7.25" "$(echo "$UPD" | jqv data.total_hours)"
check "update keeps same name allowed"    "Opening" "$(echo "$UPD" | jqv data.template_name)"
check "applies_to changed"                "manager" "$(echo "$UPD" | jqv data.applies_to)"

echo "--- cross-store isolation ---"
check "store B cannot update A template" "404" "$(code PUT "/shift-templates/$T1_ID" "$TOKEN_B" '{"template_name":"Hack","start_time":"06:00","end_time":"14:00","applies_to":"both"}')"
check "store B cannot delete A template" "404" "$(code DELETE "/shift-templates/$T1_ID" "$TOKEN_B" "")"
check "B's list excludes A templates"    "0" "$(get "/shift-templates" "$TOKEN_B" | jqv meta.total)"

echo "--- filters ---"
check "filter applies_to=manager"        "1" "$(get "/shift-templates?applies_to=manager" "$TOKEN_A" | jqv meta.total)"
check "filter applies_to=crew incl both" "2" "$(get "/shift-templates?applies_to=crew" "$TOKEN_A" | jqv meta.total)"
check "search by name"                   "1" "$(get "/shift-templates?search=Graveyard" "$TOKEN_A" | jqv meta.total)"
check "search by code"                   "1" "$(get "/shift-templates?search=GY" "$TOKEN_A" | jqv meta.total)"

echo "--- deactivate + is_active filter ---"
check "deactivate returns 200"           "200" "$(code DELETE "/shift-templates/$T1_ID" "$TOKEN_A" "")"
check "is_active=1 excludes deactivated" "2" "$(get "/shift-templates?is_active=1" "$TOKEN_A" | jqv meta.total)"
check "is_active=0 finds deactivated"    "1" "$(get "/shift-templates?is_active=0" "$TOKEN_A" | jqv meta.total)"

echo "--- QSR defaults ---"
SEED=$(post /shift-templates/defaults "$TOKEN_B" '{}')
check "seeds 4 QSR defaults"             "4" "$(echo "$SEED" | jqv data.created)"
check "re-seed is idempotent"            "0" "$(post /shift-templates/defaults "$TOKEN_B" '{}' | jqv data.created)"
check "name unique per store only"       "1" "$(get "/shift-templates?search=Opening" "$TOKEN_B" | jqv meta.total)"
check "seeded Closing hours"             "7" "$(get "/shift-templates?search=Closing" "$TOKEN_B" | jqv data.0.total_hours)"

echo "--- onboarding ---"
check "onboarding advanced to step 7"    "7" "$(get "/auth/me" "$TOKEN_A" | jqv data.store.onboarding_step)"
check "not completed mid-setup"          "null" "$B_STAMP_BEFORE"
check "completed_at stamped on finish"   "$(date +%Y)" "$(get "/auth/me" "$TOKEN_A" | jqv data.store.onboarding_completed_at | cut -c1-4)"
check "defaults also complete setup"     "$(date +%Y)" "$(get "/auth/me" "$TOKEN_B" | jqv data.store.onboarding_completed_at | cut -c1-4)"

echo
echo "passed: $PASS   failed: $FAIL"
[ "$FAIL" -eq 0 ]
