# Conservation time-scrubber: forecast overlay (#1189)

The conservation time-scrubber (`/exhibition-space/{slug}/forecast`) lets you drag a timeline and
watch each room's conservation status (green/amber/red) change. Past/now buckets reflect actual
sensor readings; the **future** buckets now carry a real **forecast** instead of flatly repeating
the current status.

## How the forecast works (ahg-exhibition)

`ExhibitionSpaceService::conservationTimeline($space, $days=21, $forecastDays=10)`:
- Past/now buckets: `readingsAsOf()` -> actual reading as of that day.
- Future buckets: `projectReadings($metricSeries, $futureTs)` -> per metric, a least-squares
  linear trend over the window extrapolated to the bucket day (slope in value/day). Falls back
  to the last value with <2 points; clamps to plausible physical bounds (lux 0-100k, temp
  -20..60 C, humidity 0-100%) so a steep short-term slope can't run away.
- Either way the projected `[metric => value]` goes through the same `statusFromReadings()`, so
  the future colour reflects where temperature / humidity / light are heading (degradation or
  recovery), not a frozen snapshot.

The scrubber view already marks future ticks " · projected" (bg-info), so past vs forecast is
clear as you drag past "now".

## Verified

`projectReadings` on a humidity series rising ~3%/day (last 70%) projects 73/79/91% at +1/3/7
days (amber->red), a flat series stays flat, and a single reading carries forward. The full
`conservationTimeline` returns past + 10 forecast buckets per room.

## Follow-ups

Use the longer-horizon light-dose model (`conservationForecast`, days-to-budget) for a
multi-month forecast mode; per-object (not just per-room) trajectories; climate-scenario inputs.
