#!/usr/bin/env bash
# Phase 7B schedule board smoke test: week resolution, draft creation, board payload shape.
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
len() { node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{try{console.log(JSON.parse(s).length)}catch(e){console.log("")}})'; }

post() { curl -s -X POST "$API$1" -H "Content-Type: application/json" -H "Accept: application/json" ${2:+-H "Authorization: Bearer $2"} -d "$3"; }
get()  { curl -s "$API$1" -H "Accept: application/json" ${2:+-H "Authorization: Bearer $2"}; }
code() { curl -s -o /dev/null -w "%{http_code}" -X "$1" "$API$2" -H "Content-Type: application/json" -H "Accept: application/json" ${3:+-H "Authorization: Bearer $3"} ${4:+-d "$4"}; }

# Expected week starts, computed the same way DateHelper::weekStartDate does.
DOW=$(date +%w)
SUNDAY=$(date -d "-$DOW days" +%F)
SUNDAY_END=$(date -d "$SUNDAY +6 days" +%F)
MIDWEEK=$(date -d "$SUNDAY +3 days" +%F)
NEXT_SUNDAY=$(date -d "$SUNDAY +7 days" +%F)
MONDAY=$(date -d "-$(( (DOW + 6) % 7 )) days" +%F)

# ---------- store A: week starts Sunday ----------
TOKEN_A=$(post /auth/register "" "{\"name\":\"Manager A\",\"email\":\"sa$STAMP@qrs.test\",\"password\":\"password123\"}" | jqv data.token)
[ -n "$TOKEN_A" ] || { echo "register A failed"; exit 1; }
post /store "$TOKEN_A" '{"store_name":"Burger Loop","timezone":"Asia/Manila","week_starts_on":0,"max_consecutive_duty_days":6}' > /dev/null
post /manager-positions/defaults "$TOKEN_A" '{}' > /dev/null
post /crew-stations/defaults "$TOKEN_A" '{}' > /dev/null
post /shift-templates/defaults "$TOKEN_A" '{}' > /dev/null

POS_A=$(get "/manager-positions?per_page=1" "$TOKEN_A" | jqv data.0.position_id)
STATIONS_A=$(get "/crew-stations?per_page=100" "$TOKEN_A")
ST1=$(echo "$STATIONS_A" | jqv data.0.station_id)
ST2=$(echo "$STATIONS_A" | jqv data.1.station_id)

CREW_ID=$(post /employees "$TOKEN_A" "{\"first_name\":\"Ana\",\"last_name\":\"Cruz\",\"employee_type\":\"crew\",\"primary_station_id\":$ST1,\"employment_status\":\"part_time\",\"date_hired\":\"2026-01-15\",\"stations\":[{\"station_id\":$ST2,\"proficiency\":\"trainer\"}]}" | jqv data.id)
MGR_ID=$(post /employees "$TOKEN_A" "{\"first_name\":\"Ben\",\"last_name\":\"Santos\",\"employee_type\":\"manager\",\"manager_position_id\":$POS_A,\"employment_status\":\"full_time\",\"date_hired\":\"2025-06-01\"}" | jqv data.id)

# ---------- store B: week starts Monday (isolation + week_starts_on fixture) ----------
TOKEN_B=$(post /auth/register "" "{\"name\":\"Manager B\",\"email\":\"sb$STAMP@qrs.test\",\"password\":\"password123\"}" | jqv data.token)
post /store "$TOKEN_B" '{"store_name":"Fry Town","timezone":"Asia/Manila","week_starts_on":1,"max_consecutive_duty_days":6}' > /dev/null

echo "--- week resolution ---"
WEEK=$(get "/schedules" "$TOKEN_A")
SCHED_A=$(echo "$WEEK" | jqv data.week.schedule_id)
check "no date returns this week"          "$SUNDAY" "$(echo "$WEEK" | jqv data.week.week_start_date)"
check "week_end_date is start + 6"         "$SUNDAY_END" "$(echo "$WEEK" | jqv data.week.week_end_date)"
check "draft created on first open"        "draft" "$(echo "$WEEK" | jqv data.week.status)"
check "is_published false"                 "false" "$(echo "$WEEK" | jqv data.week.is_published)"
check "published_at null"                  "null" "$(echo "$WEEK" | jqv data.week.published_at)"
check "dates[] has 7 entries"              "7" "$(echo "$WEEK" | jqv data.week.dates | len)"
check "mid-week date snaps to same draft"  "$SCHED_A" "$(get "/schedules?week_start_date=$MIDWEEK" "$TOKEN_A" | jqv data.week.schedule_id)"
check "reopening does not duplicate"       "$SCHED_A" "$(get "/schedules?week_start_date=$SUNDAY" "$TOKEN_A" | jqv data.week.schedule_id)"
NEXT_ID=$(get "/schedules?week_start_date=$NEXT_SUNDAY" "$TOKEN_A" | jqv data.week.schedule_id)
check "next week is its own draft"         "true" "$([ "$NEXT_ID" != "$SCHED_A" ] && echo true || echo false)"
check "store B week starts Monday"         "$MONDAY" "$(get "/schedules" "$TOKEN_B" | jqv data.week.week_start_date)"

echo "--- days ---"
check "7 day columns"                      "7" "$(echo "$WEEK" | jqv data.days | len)"
check "first day is Sunday"                "Sunday" "$(echo "$WEEK" | jqv data.days.0.day_name)"
check "day_short trimmed"                  "Sun" "$(echo "$WEEK" | jqv data.days.0.day_short)"
check "seeded Sunday is closed"            "false" "$(echo "$WEEK" | jqv data.days.0.is_open)"
check "seeded Monday is open"              "true" "$(echo "$WEEK" | jqv data.days.1.is_open)"
check "open_time trimmed to HH:MM"         "08:00" "$(echo "$WEEK" | jqv data.days.1.open_time)"
check "close_time trimmed to HH:MM"        "22:00" "$(echo "$WEEK" | jqv data.days.1.close_time)"
check "closed day has no open_time"        "null" "$(echo "$WEEK" | jqv data.days.0.open_time)"

echo "--- employee rows ---"
check "both employees are rows"            "2" "$(echo "$WEEK" | jqv data.employees | len)"
check "managers sort before crew"          "$MGR_ID" "$(echo "$WEEK" | jqv data.employees.0.employee_id)"
check "manager group_label is position"    "Store Manager" "$(echo "$WEEK" | jqv data.employees.0.group_label)"
check "crew group_label is station"        "Front Counter" "$(echo "$WEEK" | jqv data.employees.1.group_label)"
check "crew station_ids include trained"   "2" "$(echo "$WEEK" | jqv data.employees.1.station_ids | len)"
check "weekly_hours starts at zero"        "0" "$(echo "$WEEK" | jqv data.employees.1.weekly_hours)"
check "is_over_hours false on empty week"  "false" "$(echo "$WEEK" | jqv data.employees.1.is_over_hours)"
check "max_hours_per_week carried"         "24" "$(echo "$WEEK" | jqv data.employees.1.max_hours_per_week)"
check "initials for the grid avatar"       "AC" "$(echo "$WEEK" | jqv data.employees.1.initials)"

echo "--- shifts + templates ---"
check "empty week has no shifts"           "0" "$(echo "$WEEK" | jqv data.shifts | len)"
check "active templates for the palette"   "4" "$(echo "$WEEK" | jqv data.templates | len)"
check "template carries color for chips"   "#2563EB" "$(echo "$WEEK" | jqv data.templates.0.color_hex)"

echo "--- isolation + guards ---"
check "store B gets its own schedule"      "true" "$([ "$(get "/schedules" "$TOKEN_B" | jqv data.week.schedule_id)" != "$SCHED_A" ] && echo true || echo false)"
check "store B sees no employees"          "0" "$(get "/schedules" "$TOKEN_B" | jqv data.employees | len)"
check "store B sees no templates"          "0" "$(get "/schedules" "$TOKEN_B" | jqv data.templates | len)"
check "no token -> 401"                    "401" "$(code GET /schedules "" "")"
check "bad date format -> 400"             "400" "$(code GET "/schedules?week_start_date=25-08-2026" "$TOKEN_A" "")"

echo
echo "passed: $PASS   failed: $FAIL"
[ "$FAIL" -eq 0 ]
