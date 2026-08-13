---
paths:
  - 'app/Models/**'
---

# Models

## Price lives on branch_service, never on services
A service has no price of its own. `branch_service` carries price_cents, currency and duration_minutes, unique per (branch_id, service_id). Any read that shows a price must be scoped to one branch.

Read it via `Service::priceForLoadedBranch()`, which handles both load paths: the pivot sits on the model when read through `$branch->services`, and on the eager-loaded branch when read through `Service::with('branches')`. Reaching for `$service->pivot` directly works in only one of those and silently returns null in the other.
