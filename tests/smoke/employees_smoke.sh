#!/usr/bin/env bash
# Phase 5 employees smoke test: happy path, type rules, cross-store isolation, filters.
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
TOKEN_A=$(post /auth/register "" "{\"name\":\"Manager A\",\"email\":\"a$STAMP@qrs.test\",\"password\":\"password123\"}" | jqv data.token)
[ -n "$TOKEN_A" ] || { echo "register A failed"; exit 1; }
post /store "$TOKEN_A" '{"store_name":"Burger Loop","timezone":"Asia/Manila","week_starts_on":0,"max_consecutive_duty_days":6}' > /dev/null
post /manager-positions/defaults "$TOKEN_A" '{}' > /dev/null
post /crew-stations/defaults "$TOKEN_A" '{}' > /dev/null

POS_A=$(get "/manager-positions?per_page=1" "$TOKEN_A" | jqv data.0.position_id)
STATIONS_A=$(get "/crew-stations?per_page=100" "$TOKEN_A")
ST1=$(echo "$STATIONS_A" | jqv data.0.station_id)
ST2=$(echo "$STATIONS_A" | jqv data.1.station_id)
echo "store A: position=$POS_A stations=$ST1,$ST2"

# ---------- store B (isolation fixture) ----------
TOKEN_B=$(post /auth/register "" "{\"name\":\"Manager B\",\"email\":\"b$STAMP@qrs.test\",\"password\":\"password123\"}" | jqv data.token)
post /store "$TOKEN_B" '{"store_name":"Fry Town","timezone":"Asia/Manila","week_starts_on":1,"max_consecutive_duty_days":6}' > /dev/null
post /crew-stations/defaults "$TOKEN_B" '{}' > /dev/null
ST_B=$(get "/crew-stations?per_page=1" "$TOKEN_B" | jqv data.0.station_id)

echo "--- happy path ---"
CREW=$(post /employees "$TOKEN_A" "{\"first_name\":\"Ana\",\"last_name\":\"Cruz\",\"middle_name\":\"Reyes\",\"employee_type\":\"crew\",\"primary_station_id\":$ST1,\"employment_status\":\"part_time\",\"date_hired\":\"2026-01-15\",\"stations\":[{\"station_id\":$ST2,\"proficiency\":\"trainer\"}]}")
CREW_ID=$(echo "$CREW" | jqv data.id)
check "crew employee_no generated"        "EMP-0001" "$(echo "$CREW" | jqv data.employee_no)"
check "full_name from StringHelpers"      "Ana R. Cruz" "$(echo "$CREW" | jqv data.full_name)"
check "part_time default weekly hours"    "24" "$(echo "$CREW" | jqv data.max_hours_per_week)"
check "primary station name mapped"       "Front Counter" "$(echo "$CREW" | jqv data.primary_station_name)"
check "manager_position_id nulled on crew" "null" "$(echo "$CREW" | jqv data.manager_position_id)"
check "primary station auto-certified"    "2" "$(echo "$CREW" | jqv data.stations | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>console.log(JSON.parse(s).length))')"

MGR=$(post /employees "$TOKEN_A" "{\"first_name\":\"Ben\",\"last_name\":\"Santos\",\"employee_type\":\"manager\",\"manager_position_id\":$POS_A,\"employment_status\":\"full_time\",\"date_hired\":\"2025-06-01\"}")
check "manager employee_no increments"    "EMP-0002" "$(echo "$MGR" | jqv data.employee_no)"
check "full_time default weekly hours"    "40" "$(echo "$MGR" | jqv data.max_hours_per_week)"
check "position name mapped"              "Store Manager" "$(echo "$MGR" | jqv data.manager_position_name)"
check "primary_station_id nulled on mgr"  "null" "$(echo "$MGR" | jqv data.primary_station_id)"

echo "--- validation (block) ---"
check "crew without station -> 400"  "400" "$(code POST /employees "$TOKEN_A" '{"first_name":"X","last_name":"Y","employee_type":"crew","employment_status":"full_time","date_hired":"2026-01-01"}')"
check "manager without position -> 400" "400" "$(code POST /employees "$TOKEN_A" '{"first_name":"X","last_name":"Y","employee_type":"manager","employment_status":"full_time","date_hired":"2026-01-01"}')"
check "bad employee_type -> 400"     "400" "$(code POST /employees "$TOKEN_A" '{"first_name":"X","last_name":"Y","employee_type":"chef","employment_status":"full_time","date_hired":"2026-01-01"}')"
check "bad date format -> 400"       "400" "$(code POST /employees "$TOKEN_A" "{\"first_name\":\"X\",\"last_name\":\"Y\",\"employee_type\":\"crew\",\"primary_station_id\":$ST1,\"employment_status\":\"full_time\",\"date_hired\":\"15/01/2026\"}")"
check "bad proficiency -> 400"       "400" "$(code PUT "/employees/$CREW_ID/stations" "$TOKEN_A" "{\"stations\":[{\"station_id\":$ST1,\"proficiency\":\"expert\"}]}")"

echo "--- cross-store isolation ---"
check "store B cannot read A employee" "404" "$(code PUT "/employees/$CREW_ID" "$TOKEN_B" "{\"first_name\":\"Hack\",\"last_name\":\"Er\",\"employee_type\":\"crew\",\"primary_station_id\":$ST_B,\"employment_status\":\"full_time\",\"date_hired\":\"2026-01-01\"}")"
check "store B cannot delete A employee" "404" "$(code DELETE "/employees/$CREW_ID" "$TOKEN_B" "")"
check "A cannot use B's station"       "404" "$(code POST /employees "$TOKEN_A" "{\"first_name\":\"X\",\"last_name\":\"Y\",\"employee_type\":\"crew\",\"primary_station_id\":$ST_B,\"employment_status\":\"full_time\",\"date_hired\":\"2026-01-01\"}")"
check "A cannot cross-train on B stn"  "404" "$(code PUT "/employees/$CREW_ID/stations" "$TOKEN_A" "{\"stations\":[{\"station_id\":$ST_B,\"proficiency\":\"certified\"}]}")"
check "B's list excludes A employees"  "0" "$(get "/employees" "$TOKEN_B" | jqv meta.total)"

echo "--- stations sync ---"
SYNC=$(put "/employees/$CREW_ID/stations" "$TOKEN_A" "{\"stations\":[{\"station_id\":$ST2,\"proficiency\":\"certified\"}]}")
check "sync keeps primary + submitted"  "2" "$(echo "$SYNC" | jqv data.stations | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>console.log(JSON.parse(s).length))')"
check "sync updated proficiency"        "certified" "$(echo "$SYNC" | jqv data.stations | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{const a=JSON.parse(s).find(x=>x.proficiency!=="certified"||true);console.log(JSON.parse(s).filter(x=>x.station_id!=Number(process.argv[1]))[0].proficiency)})' "$ST1")"

echo "--- filters ---"
check "filter employee_type=manager"    "1" "$(get "/employees?employee_type=manager" "$TOKEN_A" | jqv meta.total)"
check "filter employee_type=crew"       "1" "$(get "/employees?employee_type=crew" "$TOKEN_A" | jqv meta.total)"
check "filter employment_status"        "1" "$(get "/employees?employment_status=part_time" "$TOKEN_A" | jqv meta.total)"
check "filter station_id"               "1" "$(get "/employees?station_id=$ST2" "$TOKEN_A" | jqv meta.total)"
check "search by last name"             "1" "$(get "/employees?search=Santos" "$TOKEN_A" | jqv meta.total)"
check "search by employee_no"           "1" "$(get "/employees?search=EMP-0001" "$TOKEN_A" | jqv meta.total)"

echo "--- deactivate + is_active filter ---"
check "deactivate returns 200"          "200" "$(code DELETE "/employees/$CREW_ID" "$TOKEN_A" "")"
check "is_active=1 excludes deactivated" "1" "$(get "/employees?is_active=1" "$TOKEN_A" | jqv meta.total)"
check "is_active=0 finds deactivated"    "1" "$(get "/employees?is_active=0" "$TOKEN_A" | jqv meta.total)"

echo "--- onboarding ---"
check "onboarding advanced to step 6"   "6" "$(get "/auth/me" "$TOKEN_A" | jqv data.store.onboarding_step)"

echo
echo "passed: $PASS   failed: $FAIL"
[ "$FAIL" -eq 0 ]
