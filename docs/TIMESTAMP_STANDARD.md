# Telemetry timestamp standard (v1.5-compatible)

## Rule: record the instant once, display the calendar day locally

| Layer | Contract |
| --- | --- |
| Windows / macOS / Ubuntu | Send each timestamp in ISO 8601 / RFC 3339 form, with `Z` or an explicit UTC offset: `2026-11-06T19:00:00-06:00`. |
| Ingestion | Validate the timestamp and normalize to UTC. Never parse an offset-aware value as server-local time. |
| MySQL telemetry `DATETIME` | UTC, using `YYYY-MM-DD HH:MM:SS`. SQL `DATETIME` does not carry timezone metadata, so all code must honor this invariant. |
| Screenshot folders and filenames | Use the **UTC capture day**. A CST November 6 screenshot may legitimately live in the November 7 folder. Never look up a screenshot by guessing its folder from a viewer-local date; use the metadata path. |
| Browser portal | Display dates/times in the viewer's IANA timezone (from the browser, for example `America/Chicago`). Keep the original UTC instant for calculations. |
| Date-range APIs | Prefer `start_utc` inclusive and `end_utc` exclusive as UTC ISO 8601 instants. When a user selects a local day, construct *each* local midnight in the browser and then convert both to UTC. |
| Historical APIs | `start_date` and `end_date` remain accepted as **UTC** calendar-day boundaries for older callers. Do not combine these with a timezone guess. The new portal sends explicit UTC bounds. |
| DB bucketing | Bucket UTC `DATETIME` values with timezone-invariant arithmetic; avoid `UNIX_TIMESTAMP(DATETIME)` when DB session timezone may differ. |
| Client clocks | Keep OS clocks synchronized (NTP). Record and expose upload failures; the screenshot filename is evidence of server acknowledgement, not a guarantee that a clock is correct. |

## Examples

A screenshot captured November 6, 2026 at 7:00 PM Central Standard Time is `2026-11-07T01:00:00Z`. It is stored in a UTC `2026-11-07` folder, but **must appear** in the portal's November 6 local calendar view.

For November 6 in `America/Chicago`, query UTC instants:

- Start (inclusive): `2026-11-06T06:00:00.000Z`
- End (exclusive): `2026-11-07T06:00:00.000Z`

DST varies the length of a calendar day: March 8, 2026 has 23 hours in Chicago, while November 1, 2026 has 25 hours. **Never** calculate the next calendar day by adding exactly 24 hours.

## Legacy and rollout

- Offset-less timestamps from legacy v1.3/v1.5 clients have no recoverable timezone. Interpret them as UTC for compatibility going forward. Some historical rows may have been recorded as local wall time; they cannot be reliably converted without identifying the original client timezone and period.
- **Do not** rename existing screenshot folders, move files, or rewrite historical DATETIME rows automatically. File paths in `screenshot_metadata` are authoritative.
- Deploy the backend and portal before updating clients. The new backend accepts both legacy `YYYY-MM-DD HH:MM:SS` and offset-aware ISO-8601 screenshot uploads. Updated clients include offsets.
- Screenshot cleanup preview and deletion use the same viewer-local UTC interval, so an image in the next UTC date folder is not accidentally excluded or deleted in the wrong day.
- Daily API request-log partition names use UTC dates (not the server's PHP timezone).
- The API contract is implemented in `Backend/services/TelemetryTime.php`. To verify the edge cases, run `php Backend/tests/telemetry_timezone_contract.php` from the repository root.
