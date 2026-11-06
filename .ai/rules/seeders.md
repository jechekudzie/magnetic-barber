---
paths:
  - database/seeders/CatalogSeeder.php
---

# Seeders

## The printed price cards are the catalog's source of truth
The seeded menu comes line for line from the shop's printed men's and ladies' price cards. `tests/Feature/PriceListSeedTest.php` asserts every line at its printed price, so changing a price here without changing the card fails the suite.

Two conventions the cards force:

- **Men's and ladies' work are separate services, not one service with two prices.** The cards charge differently for the same job (Haircut + Dye is $10 men, $15 ladies) and `branch_service` prices per branch, not per client. Ladies' lines are named with a `Ladies ` prefix; men's lines are unprefixed. The Deep Clean and LED Therapy Facial is the one shared line: $10 on both cards, so it is one service.
- **"From $20, depending with colour" is `branch_service.is_from_price`.** It sits on the pivot beside the price it qualifies, not on `services`. Read it with `Service::priceIsFromForLoadedBranch()`. A from-priced service cannot be charged in full up front — the final figure is settled in the chair.

Services that come off the cards are retired (deactivated on every branch pivot, then soft deleted), never hard deleted: `appointment_services` points at them and a past booking must still name what was bought. Lookups here use `Service::withTrashed()` because the slug is unique across soft-deleted rows.
