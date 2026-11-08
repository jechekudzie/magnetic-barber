---
paths:
  - 'app/Services/**'
  - app/Services/CommissionService.php
  - app/Services/VisitService.php
---

# Services

## Never cache Eloquent models or collections
Laravel 13 cache stores unserialize with an `allowed_classes` allowlist, so a cached Eloquent model or Collection comes back as `__PHP_Incomplete_Class` and blows up on first method call. It fails silently on a cache miss and only breaks on the second read, which makes it easy to ship.

Cache plain arrays instead. In this app that means caching the resolved API Resource payload (see App\Services\CatalogPayload), never the query result. Service classes return live Eloquent; CatalogPayload caches the arrays those get shaped into.

## Barber commission is snapshotted per service line, never recomputed
A barber earns a percentage of every service line they personally rendered. The house rate is `config('magnetic.commission_rate')` (5%); `branch_user.commission_rate` overrides it per barber.

**Attribution is per line, not per appointment.** Two barbers can split one booking — a cut from one, a facial from the other — so paying on the appointment total pays the wrong person. `appointment_services.staff_id` carries who did the work.

**`commission_rate` and `commission_cents` on `appointment_services` are snapshots**, written when the line is created, for the same reason `price_cents` and `name_snapshot` beside them are: a rate change next month must not rewrite what was already earned. Never recompute earnings from the live `branch_user` rate — read the frozen columns.

**Only completed work counts.** `earnedLines()` filters to `status = completed` with a non-null `completed_at`. A cancelled appointment keeps its commission figures on the row rather than zeroing them, so reinstating a booking at the desk needs no recalculation.

**Days are the shop's, not UTC's.** All three read methods go through `shopDay()`, which reads the date in the branch timezone before converting to UTC bounds — a 22:30 Harare cut belongs to that evening. Note that Eloquent formats a datetime in whatever timezone the instance carries, so a non-UTC Carbon written to `completed_at` stores its wall-clock digits and reads back as UTC; always pass `->utc()`.

## Reassigning a chair re-snapshots commission; completing freezes it
Commission snapshots on `appointment_services` are normally never recomputed — but `VisitService::assign()` deliberately rewrites `staff_id`, `commission_rate` and `commission_cents` on every line.

That is not a violation of the snapshot rule. Snapshots exist so a *rate change* cannot rewrite work already done. Reassignment happens before the work is done, and leaving the snapshot alone would pay the original barber for a cut somebody else is about to give. `assign()` refuses once the visit is completed, which is where the money is actually settled.

Start and finish live here too, and nowhere else. They used to be duplicated in `Admin\BookingController` and `Api\V1\Staff\DiaryController`, and the copies had drifted: the desk recorded the visit against the client, the handset did not. Both controllers delegate now — do not reimplement either.
