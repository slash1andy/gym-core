# CoWork Playbook: Export Attendance History from Spark

## Goal

Extract historical attendance/check-in records from Spark Membership and produce a CSV that can be imported into gym-core via `wp gym import attendance`.

## Required CSV Format

```csv
user_id,location,checked_in_at,class_id,method
```

Since we don't have WP user IDs during export, produce an email-keyed version:

```csv
email,location,checked_in_at,class_name,method
```

We'll resolve email→user_id before import, similar to how belt ranks were handled.

## Steps

### 1. Log into Spark Membership

Navigate to the Spark Membership dashboard at https://app.sparkmembership.com and log in with the gym's credentials.

### 2. Navigate to Attendance Reports

Look for:
- Reports → Attendance Report
- OR Members → [Individual Member] → Attendance History
- OR Check-in → History / Logs

### 3. Export Strategy

Spark may not have a bulk attendance export. If not, try these approaches in order:

**Option A: Bulk Report Export**
If there's an "Attendance Report" with date range filters and an Export/CSV button:
1. Set date range to the earliest available → today
2. Set location filter to "All" or export per-location
3. Click Export/Download
4. Save as `spark_attendance_raw.csv`

**Option B: Per-Member Export**
If only per-member attendance is available:
1. Go to Members list
2. For each active member, open their profile
3. Navigate to their attendance/check-in history
4. Copy or export the data
5. This is tedious for 180+ members — use browser automation if possible

**Option C: API Access**
If Spark has an API:
1. Check https://app.sparkmembership.com/api or developer docs
2. Look for endpoints like `/attendance`, `/checkins`, `/class-history`
3. Use the API to bulk-export attendance records

### 4. Transform the Data

Once you have raw attendance data, transform it to match this format:

```csv
email,location,checked_in_at,class_name,method
member@email.com,rockford,2025-01-15 09:00:00,Adult BJJ,imported
member@email.com,rockford,2025-01-17 18:30:00,Kickboxing,imported
```

Rules:
- `email` — member's email address (used to match WP users)
- `location` — `rockford` or `beloit` (lowercase slug)
- `checked_in_at` — ISO datetime format `YYYY-MM-DD HH:MM:SS`
- `class_name` — class name as shown in Spark (we'll map to gym_class IDs later)
- `method` — always `imported` for historical records

### 5. Save Output

Save the transformed CSV to: `spark-import/spark_attendance.csv`

### 6. Import Commands (Andrew will run these)

```bash
# Resolve email→user_id
wp eval-file scripts/spark_resolve_ids.php spark_attendance.csv spark_attendance_resolved.csv

# Dry run
wp gym import attendance --file=spark_attendance_resolved.csv --dry-run

# Import
wp gym import attendance --file=spark_attendance_resolved.csv
```

## What We Already Have

- 179 users imported with emails
- 185 belt ranks imported
- 74 stripe counts updated
- 0 attendance records (this is the gap)

## Priority

Historical attendance data is nice-to-have but not blocking. The system works without it — members just won't see their historical streak/attendance count. New check-ins going forward will be tracked automatically.

If extracting historical attendance is too difficult, we can start fresh from the go-live date and note in member profiles that historical data predates the system migration.
