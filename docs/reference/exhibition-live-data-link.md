# Exhibition Digital Twin - Live Data Link (heratio#1146)

**Summary:** The exhibition digital twin can now ingest live sensor / occupancy
readings per space and reflect them in the 3D walkthrough as a colour-coded
conservation overlay. This is Phase 1 of turning the virtual *model* into a true
*twin* (live physical-to-virtual feedback). Readings are stored in
`ahg_exhibition_reading`; the walkthrough tints each room green / amber / red by
conservation status and shows a live readout for the room you stand in.

## Data link (ingest)
Any source (sensor gateway, cron, script, MCP) can POST readings for a space:

- `POST /exhibition-space/{slug}/readings`
  - single: `{ "metric": "temp_c", "value": 22.5, "recorded_at": "2026-06-05 12:00:00" }`
  - batch: `{ "readings": [ {"metric":"lux","value":180}, {"metric":"humidity","value":52} ] }`
  - `recorded_at` is optional (defaults to now).
- Metrics in use: `lux`, `temp_c`, `humidity`, `visitors` (free-form; latest value per metric wins).
- Requires the `acl:update` permission (session/admin today; route a future API key for unattended sensors via the ahg-api package).

`POST /exhibition-space/{slug}/readings/simulate` seeds plausible demo readings
across the whole building - used by the **Simulate live data** button in the
Digital Twin Builder so the overlay can be previewed without physical sensors.

## Conservation status (international museum norms)
Computed per room from the latest readings vs targets; the worst metric wins:

- Light: vs the space's `lighting_lux_target` (default 200 lux). Above target = warn; above 1.5x = alert.
- Temperature: ideal 16-24 C; outside = warn; below 14 or above 26 = alert.
- Humidity: ideal 40-60%; outside = warn; below 35 or above 65 = alert.

Status values: `ok` (green), `warn` (amber), `alert` (red), `none` (no readings, grey).

## Walkthrough overlay
- A thermometer button toggles the **Live data** overlay.
- Each room's floor is tinted by its conservation status.
- A HUD panel shows the current room's name, status, and readings (light/temp/humidity/visitors) plus the reasons for any warning.

## Data model
`ahg_exhibition_reading`: `id, exhibition_space_id, metric, value, recorded_at`
(append-only time series; the latest value per metric is the current state, and
the history feeds the planned twin-analytics dashboard, heratio#1148).

## Still to come (umbrella heratio#1145)
Simulation/prediction (#1147), analytics dashboard (#1148), AI recommendation
(#1149), multi-user presence (#1150), XR (#1152), interoperability (#1151).
