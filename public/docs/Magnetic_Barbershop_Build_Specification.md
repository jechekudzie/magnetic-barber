# Magnetic Barbershop Platform
## Technical Build Specification

**Version 1.0 | August 2026**
Web system (Laravel + React), admin backend, and mobile app (React Native)

---

## 1. What we are building

One platform that runs the shop floor, the client's phone, and the back office across multiple branches.

**Three service modes, one client record:**

| Mode | Trigger | Key difference |
|---|---|---|
| Walk in | Client arrives, reception logs them | Queue position, no fixed time |
| Scheduled | Client books in app, web, or WhatsApp | Fixed slot, optional deposit |
| House call | Client books with an address | Travel fee by zone, barber routing |

**Service categories:** cuts and beards, wash / steam / dry, colour and tinting, skin and facials, products and accessories, renewable plans.

**Non negotiables:**
1. Every visit, whatever the channel, writes to one client record and one sales ledger.
2. Prices and stock are per branch, never global.
3. The client's phone number is the identity key.
4. Nothing important is deleted, only soft deleted or reversed.

---

## 2. Stack

### Backend
| Layer | Choice |
|---|---|
| Framework | Laravel 11 (PHP 8.3) |
| Database | MySQL 8 (or PostgreSQL 16 if you prefer) |
| Cache, queue, session | Redis 7 |
| Queue worker | Laravel Horizon |
| Search (later) | MySQL fulltext first, Meilisearch only if needed |
| Storage | S3 compatible (AWS S3, Cloudflare R2, or Backblaze B2) |
| Auth | Laravel Sanctum |

### Required packages

```bash
composer require \
  laravel/sanctum \
  laravel/horizon \
  spatie/laravel-permission \
  spatie/laravel-sluggable \
  spatie/laravel-medialibrary \
  spatie/laravel-activitylog \
  spatie/laravel-query-builder \
  spatie/laravel-backup \
  propaganistas/laravel-phone \
  simplesoftwareio/simple-qrcode \
  league/flysystem-aws-s3-v3

composer require --dev \
  laravel/pint \
  larastan/larastan \
  pestphp/pest \
  barryvdh/laravel-ide-helper
```

### Web frontend
| Layer | Choice |
|---|---|
| Framework | React 18 + TypeScript |
| Build | Vite |
| Routing | React Router 6 |
| Server state | TanStack Query v5 |
| Forms | React Hook Form + Zod |
| UI | Tailwind CSS + shadcn/ui |
| Tables | TanStack Table |
| Charts | Recharts |
| Dates | date-fns + date-fns-tz |
| HTTP | Axios with an interceptor layer |

### Mobile
| Layer | Choice |
|---|---|
| Framework | React Native (Expo, managed workflow) |
| Navigation | React Navigation 6 |
| Server state | TanStack Query (shared query keys with web) |
| Storage | expo-secure-store for tokens, MMKV for cache |
| Camera and QR | expo-camera, expo-barcode-scanner |
| Images | expo-image-picker + expo-image-manipulator |
| Push | expo-notifications (FCM and APNs) |
| Maps | react-native-maps (house call address) |

### Shared code
Create a `packages/shared` workspace holding TypeScript types generated from the API, Zod schemas, and money and phone formatting helpers. Both web and mobile import it. Generate types with `spatie/laravel-typescript-transformer` or write them once by hand and keep a single source of truth.

---

## 3. Repository layout

Use a monorepo. It keeps the shared types honest.

```
magnetic/
  apps/
    api/                 Laravel 11
    web/                 React admin + public site
    mobile/              Expo React Native
  packages/
    shared/              types, zod schemas, formatters
    ui-tokens/           colours, spacing, typography
  docker/
  .github/workflows/
```

Brand tokens, used everywhere:

```ts
export const brand = {
  black:    '#0D0D0D',
  panel:    '#1B1B1B',
  panelAlt: '#242424',
  gold:     '#D9A947',
  goldLite: '#F0CC80',
  white:    '#FFFFFF',
  muted:    '#9C9488',
  success:  '#3FA46A',
  danger:   '#C7442E',
};
```

---

## 4. Data model

### 4.1 Conventions

- Primary keys: auto increment `id` for internal joins, plus a `ulid` column with a unique index on anything exposed in a URL or API payload. Never expose sequential ids for clients or appointments.
- Money: integer `*_cents` columns, always paired with a `currency` char(3). Never floats.
- Timestamps: store UTC, render in `Africa/Harare`. Set `app.timezone` to `UTC` and convert at the edge.
- Soft deletes on: users, branches, services, products, styles. Hard delete nothing that has ever touched money.
- Every money and booking table gets `created_by` and `updated_by` user references.

### 4.2 Branches and staff

**branches**
```
id, ulid, slug (unique), name, phone, email,
address_line, area, city, latitude, longitude,
timezone (default Africa/Harare),
opens_at, closes_at, days_open (json),
house_call_enabled (bool), house_call_radius_km,
is_active, created_at, updated_at, deleted_at
```

**users** (staff and clients live here together, separated by role)
```
id, ulid, name, phone (unique, E.164), phone_verified_at,
email (nullable, unique), email_verified_at, password (nullable),
avatar_path, locale (default en), is_active,
last_seen_at, created_at, updated_at, deleted_at
```
Clients may never set a password. They authenticate with a phone OTP. Staff have a password plus an optional 4 digit till PIN.

**branch_user** (which staff work where)
```
id, branch_id, user_id, employment_type (employed|chair_rental),
commission_rate, chair_rate_cents, is_primary, starts_on, ends_on
```

**staff_profiles**
```
id, user_id, bio, specialities (json), instagram_handle,
accepts_house_calls (bool), rating_avg, rating_count
```

**working_hours** (per staff member per branch)
```
id, branch_user_id, weekday (0-6), starts_at, ends_at
```

**time_off**
```
id, user_id, branch_id (nullable), starts_at, ends_at, reason, approved_by
```

### 4.3 Clients

**client_profiles**
```
id, user_id, account_number (unique, e.g. MB-0143),
home_branch_id, preferred_staff_id,
date_of_birth, gender, notes,
source (walkin|qr|app|web|whatsapp|referral),
referred_by_user_id, referral_code (unique),
whatsapp_opt_in (bool), sms_opt_in, push_opt_in, marketing_opt_in,
first_visit_at, last_visit_at, visit_count,
lifetime_value_cents, average_cycle_days,
created_at, updated_at
```

**client_addresses** (house calls)
```
id, user_id, label, address_line, area, latitude, longitude,
directions_note, zone_id, is_default
```

**skin_profiles**
```
id, user_id, skin_type (oily|dry|combination|normal|sensitive),
concerns (json), allergies, current_products,
patch_test_done_at, patch_test_result, contraindications,
consent_photos (bool), consent_signed_at,
updated_by, created_at, updated_at
```

### 4.4 Catalog

**service_categories**
```
id, slug (unique), name, description, icon, sort_order, is_active
```

**services**
```
id, ulid, slug (unique), service_category_id, name, description,
default_duration_minutes, buffer_minutes,
requires_patch_test (bool), patch_test_lead_hours,
is_skin_service (bool), is_house_call_eligible (bool),
requires_room (bool), sort_order, is_active, deleted_at
```

**branch_service** (this is where price lives)
```
id, branch_id, service_id,
price_cents, currency, duration_minutes,
house_call_surcharge_cents,
is_active,
UNIQUE(branch_id, service_id)
```

**products**
```
id, ulid, slug (unique), sku (unique), name, brand,
description, category, unit, is_active, deleted_at
```

**branch_product**
```
id, branch_id, product_id, price_cents, currency, cost_cents,
stock_qty, reorder_level, is_active,
UNIQUE(branch_id, product_id)
```

**stock_movements** (append only)
```
id, branch_product_id, type (purchase|sale|adjustment|transfer_in|transfer_out|write_off),
qty_delta, reason, reference_type, reference_id, unit_cost_cents,
performed_by, created_at
```

**styles** (the gallery)
```
id, ulid, slug (unique), code (e.g. 01), name, description,
service_id (default service), gender_tag, hair_type_tag (json),
typical_duration_minutes, sort_order, is_active
```
Images attach via medialibrary collection `gallery`.

### 4.5 Bookings

**appointments**
```
id, ulid, reference (short human code, e.g. MB-A7K2Q),
branch_id, client_id (users.id), staff_id (nullable until assigned),
type (walkin|scheduled|house_call),
status (pending|confirmed|checked_in|in_progress|completed|cancelled|no_show),
source (app|web|whatsapp|reception|qr|phone),
scheduled_start_at, scheduled_end_at,
checked_in_at, started_at, completed_at, cancelled_at,
queue_position (walk ins only),
estimated_wait_minutes,
style_id (nullable), reference_photo_media_id (nullable),
client_note, staff_note,
subtotal_cents, travel_fee_cents, discount_cents, total_cents, currency,
deposit_required_cents, deposit_paid_cents,
cancellation_reason, cancelled_by,
created_by, created_at, updated_at
```

**appointment_services** (price captured at booking time, never read live from the catalog)
```
id, appointment_id, service_id, staff_id,
name_snapshot, price_cents, currency, duration_minutes, qty
```

**house_call_details**
```
id, appointment_id, client_address_id,
address_snapshot, latitude, longitude, zone_id,
travel_fee_cents, distance_km,
departed_at, arrived_at, kit_checklist (json)
```

**house_call_zones**
```
id, branch_id, name, max_radius_km, fee_cents, currency, is_active
```

**visit_records** (what actually happened in the chair)
```
id, appointment_id, client_id, staff_id,
clipper_guards (json), fade_type, parting, beard_shape,
colour_formula, developer_volume, processing_minutes,
skin_products_used (json), skin_observations,
next_visit_recommended_days, private_note,
created_at, updated_at
```
Before and after photos attach via medialibrary collections `before` and `after`, gated on `skin_profiles.consent_photos`.

**queue_entries** (live walk in board, can be derived from appointments but a table makes the board fast)
```
id, branch_id, appointment_id, position, joined_at, called_at, seated_at, left_at
```

### 4.6 Money

**sales**
```
id, ulid, reference, branch_id, client_id (nullable for anonymous),
appointment_id (nullable), staff_id (who rang it up),
status (open|paid|part_paid|voided|refunded),
subtotal_cents, discount_cents, tax_cents, total_cents,
paid_cents, change_cents, currency,
fx_rate_to_usd (decimal 12,6), fx_captured_at,
loyalty_points_earned, loyalty_points_redeemed,
voucher_code, voided_by, voided_reason,
opened_at, closed_at, created_by
```

**sale_items**
```
id, sale_id, itemable_type (Service|Product|Plan), itemable_id,
name_snapshot, sku_snapshot, qty,
unit_price_cents, discount_cents, line_total_cents, currency,
staff_id (commission attribution), cost_cents
```

**payments**
```
id, sale_id, method (cash|ecocash|onemoney|innbucks|swipe|card|transfer|voucher|points),
amount_cents, currency, fx_rate_to_usd,
reference, phone_used, received_by, received_at,
is_refund (bool), refunded_payment_id
```

**cash_drawer_sessions**
```
id, branch_id, opened_by, opened_at, opening_float_cents,
closed_by, closed_at, counted_cents, expected_cents, variance_cents,
currency, note
```

### 4.7 Retention

**loyalty_ledger** (append only, balance is a SUM, never a column you update)
```
id, client_id, branch_id, sale_id (nullable), appointment_id (nullable),
type (earn|redeem|adjust|expire|referral_bonus|signup_bonus),
points (signed integer), balance_after (denormalised for statements),
description, expires_at, created_by, created_at
```

**loyalty_rules**
```
id, name, points_per_currency_unit, currency,
applies_to (all|services|products|skin),
min_spend_cents, redemption_value_cents_per_point,
points_expiry_months, is_active, starts_at, ends_at
```

**plans** (renewable cuts, facial packages)
```
id, ulid, slug, name, description, type (unlimited|session_pack),
included_service_ids (json), session_count,
price_cents, currency, validity_days, branch_scope (all|specific),
is_active
```

**subscriptions**
```
id, ulid, client_id, plan_id, branch_id,
status (active|expired|cancelled|paused),
starts_on, ends_on, sessions_total, sessions_used,
purchase_sale_id, auto_renew (bool), cancelled_at
```

**subscription_redemptions**
```
id, subscription_id, appointment_id, sale_id, redeemed_at, sessions_deducted
```

**reviews**
```
id, appointment_id, client_id, staff_id, branch_id,
rating (1-5), comment, is_public, published_at,
responded_by, response, responded_at, flagged_at
```

**referrals**
```
id, referrer_id, referee_id, code_used,
status (pending|qualified|rewarded|void),
qualifying_appointment_id, reward_points, rewarded_at
```

### 4.8 Messaging

**message_templates**
```
id, key (unique, e.g. welcome_account), channel (whatsapp|sms|push|email),
provider_template_name, language, body_preview, variables (json), is_active
```

**message_logs**
```
id, client_id, channel, template_key, provider_message_id,
to_phone, payload (json), status (queued|sent|delivered|read|failed),
error_code, error_message, cost_cents, sent_at, delivered_at
```

**reminder_schedules**
```
id, client_id, type (cut_due|facial_due|appointment_24h|appointment_2h|winback|birthday),
due_at, sent_at, cancelled_at, appointment_id
```

### 4.9 Infrastructure tables

Standard Spatie tables (`roles`, `permissions`, `model_has_roles` with `branch_id` team column, `model_has_permissions`, `role_has_permissions`), `activity_log`, `media`, `jobs`, `failed_jobs`, `personal_access_tokens`, `notifications`, plus:

**settings** (per branch key value, cast in code)
```
id, branch_id (nullable = global), key, value (json), updated_by
```

**otp_codes**
```
id, phone, code_hash, purpose (login|verify|staff_reset),
attempts, expires_at, consumed_at, ip_address
```

**qr_tokens**
```
id, branch_id, label (Door, Chair 3), token (unique), scan_count,
last_scanned_at, is_active
```

---

## 5. Roles and permissions

### 5.1 Enable teams before your first migration

In `config/permission.php`:

```php
'teams' => true,
'team_foreign_key' => 'branch_id',
```

Retrofitting this later means rewriting `model_has_roles`, `role_has_permissions`, and every gate call in the codebase. Do it now.

Set the team context in middleware on every authenticated request:

```php
// app/Http/Middleware/SetPermissionsBranch.php
public function handle(Request $request, Closure $next)
{
    $branchId = $request->header('X-Branch-Id')
        ?? $request->user()?->clientProfile?->home_branch_id;

    if ($branchId && $request->user()?->worksAt($branchId)) {
        setPermissionsTeamId($branchId);
        app()->instance('currentBranch', Branch::find($branchId));
    }

    return $next($request);
}
```

Owner and super admin bypass the team check:

```php
// AuthServiceProvider
Gate::before(fn ($user) => $user->hasRole('super-admin') ? true : null);
```

### 5.2 Roles

| Role | Scope | Summary |
|---|---|---|
| `super-admin` | Global | Developer and system owner. Bypasses all gates. |
| `owner` | Global | Sees every branch, every number. Cannot be deleted. |
| `branch-manager` | Per branch | Runs one branch: staff, stock, prices, refunds, reports. |
| `receptionist` | Per branch | Check in, book, take payment, issue accounts. No refunds, no price edits. |
| `barber` | Per branch | Own schedule, own clients, visit records, own earnings. |
| `aesthetician` | Per branch | Same as barber plus skin profiles and consent capture. |
| `client` | Global | Own data only. |

### 5.3 Permission list

Group by resource, not by screen. Screens change, resources do not.

```
branch.view  branch.create  branch.update  branch.deactivate

staff.view  staff.invite  staff.update  staff.deactivate
staff.schedule.manage  staff.commission.view

client.view  client.create  client.update  client.merge
client.contact.view      # phone numbers, gated for barbers
client.note.view  client.note.write
client.export            # owner and manager only

appointment.view.own  appointment.view.branch  appointment.create
appointment.update  appointment.assign  appointment.cancel
appointment.checkin  appointment.complete  appointment.no-show

queue.view  queue.manage

visit.record.write  visit.photo.upload
skin.profile.view  skin.profile.write  skin.consent.capture

service.view  service.create  service.update
price.view  price.update

product.view  product.create  product.update
stock.view  stock.adjust  stock.transfer

sale.create  sale.discount  sale.void  sale.refund
payment.take  drawer.open  drawer.close  drawer.reconcile

loyalty.view  loyalty.adjust
plan.manage  subscription.manage

review.view  review.respond  review.publish

report.view.own  report.view.branch  report.view.financial  report.view.group

message.send  message.template.manage
settings.manage  audit.view
```

### 5.4 Matrix

| Permission group | owner | manager | reception | barber | aesthetician | client |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| branch.* | full | view, update | view | view | view | no |
| staff.* | full | full | view | own | own | no |
| client.view | all | branch | branch | booked only | booked only | self |
| client.contact.view | yes | yes | yes | no | no | self |
| appointment.create | yes | yes | yes | own | own | self |
| appointment.assign | yes | yes | yes | no | no | no |
| visit.record.write | yes | yes | no | own | own | no |
| skin.profile.write | yes | yes | no | no | yes | no |
| price.update | yes | yes | no | no | no | no |
| stock.adjust | yes | yes | no | no | no | no |
| sale.create | yes | yes | yes | own chair | own chair | no |
| sale.void / refund | yes | yes | no | no | no | no |
| drawer.close | yes | yes | yes | no | no | no |
| loyalty.adjust | yes | yes | no | no | no | no |
| report.view.financial | yes | branch only | no | no | no | no |
| report.view.group | yes | no | no | no | no | no |
| audit.view | yes | branch only | no | no | no | no |

Two rules worth defending in review:
1. **Barbers cannot see client phone numbers.** They can see the name, the style history, and the notes. This stops the most common way a shop loses a client list when a barber leaves.
2. **Only managers void and refund.** Reception can take money, never unwind it.

### 5.5 Seeder

```php
// database/seeders/RolesAndPermissionsSeeder.php
$permissions = [ /* the list above */ ];

foreach ($permissions as $p) {
    Permission::findOrCreate($p, 'web');
}

$roles = [
    'owner' => $permissions,
    'branch-manager' => [ /* minus branch.create, report.view.group */ ],
    'receptionist' => [
        'client.view','client.create','client.update','client.contact.view',
        'appointment.view.branch','appointment.create','appointment.update',
        'appointment.assign','appointment.checkin','appointment.cancel',
        'queue.view','queue.manage','service.view','price.view','product.view',
        'sale.create','payment.take','drawer.open','drawer.close',
        'loyalty.view','message.send','report.view.own',
    ],
    'barber' => [
        'appointment.view.own','appointment.create','appointment.checkin',
        'appointment.complete','queue.view','visit.record.write',
        'visit.photo.upload','service.view','price.view','client.view',
        'client.note.view','client.note.write','report.view.own',
    ],
    'aesthetician' => [ /* barber list + skin.profile.write, skin.consent.capture */ ],
    'client' => [],
];
```

Roles are global rows. The **assignment** carries the branch:

```php
setPermissionsTeamId($avenues->id);
$user->assignRole('barber');

setPermissionsTeamId($borrowdale->id);
$user->assignRole('branch-manager');   // same person, different branch
```

---

## 6. Slugs and identifiers

### 6.1 Sluggable

Apply `HasSlug` to: `branches`, `service_categories`, `services`, `products`, `styles`, `plans`.

```php
public function getSlugOptions(): SlugOptions
{
    return SlugOptions::create()
        ->generateSlugsFrom('name')
        ->saveSlugsTo('slug')
        ->doNotGenerateSlugsOnUpdate()   // a live QR or shared link must not break
        ->preventOverwrite();
}

public function getRouteKeyName(): string
{
    return 'slug';
}
```

Never slug appointments, clients, or sales. They get a ULID for API use and a short human reference for people to read aloud.

### 6.2 Account numbers

Format `MB-0143`. Per branch prefix when you pass one branch, so `AV-0143` and `BD-0088` if you prefer. Generate inside a transaction with a row lock, and put a unique index on the column. Do not use `max(id)+1` outside a lock.

```php
DB::transaction(function () use ($branch, $user) {
    $seq = DB::table('branch_sequences')
        ->where('branch_id', $branch->id)
        ->lockForUpdate()
        ->first();

    $next = $seq->last_account_number + 1;

    DB::table('branch_sequences')
        ->where('branch_id', $branch->id)
        ->update(['last_account_number' => $next]);

    $user->clientProfile()->create([
        'account_number' => sprintf('%s-%04d', $branch->code, $next),
        'home_branch_id' => $branch->id,
        'referral_code'  => Str::upper(Str::random(6)),
    ]);
});
```

### 6.3 Appointment reference

Five characters, Crockford base32 (no I, L, O, U, so nobody misreads it over the phone), prefixed `MB-A`. Unique index. Used in WhatsApp messages and on the receipt.

---

## 7. Phone numbers, the identity key

Clients will give you `0781879820`, `+263781879820`, `263 78 187 9820`, and `078 187 9820` for the same person. Normalise once, on the way in.

```php
// app/Casts/PhoneNumber.php
use Propaganistas\LaravelPhone\PhoneNumber as Phone;

public function set($model, $key, $value, $attributes)
{
    return (new Phone($value, 'ZW'))->formatE164();  // +263781879820
}
```

Rules:
- `users.phone` is unique and stores E.164 only.
- Validation rule: `['required','phone:ZW,mobile']`.
- Display format is a presentation concern, handled in the shared package.
- Before creating a client, always search by normalised phone first. Reception creating a duplicate is the single most common data problem in a shop system.
- Build a `client.merge` action from day one: pick a survivor, move appointments, sales, and loyalty ledger rows, soft delete the duplicate, log it.

---

## 8. Money and multi currency

Zimbabwe runs USD and ZiG side by side. Handle it explicitly or the reports will be worthless.

**Rules:**
1. Every money column is an integer of minor units plus a `currency` char(3).
2. `sales.fx_rate_to_usd` is captured at the moment the sale closes, along with `fx_captured_at`. Never recalculate historic sales with today's rate.
3. A single sale can take payments in more than one currency. `payments` rows each carry their own currency and rate. Reconcile against the sale total in the sale currency.
4. Reports offer a toggle: transaction currency, or converted to USD at the captured rate.
5. Cash drawer sessions are per currency. Two drawers, two counts, two variances.

```php
// app/Support/Money.php
final class Money
{
    public function __construct(
        public readonly int $cents,
        public readonly string $currency,
    ) {}

    public static function of(float|int|string $amount, string $currency): self
    {
        return new self((int) round(((float) $amount) * 100), strtoupper($currency));
    }

    public function plus(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->cents + $other->cents, $this->currency);
    }

    public function format(): string
    {
        return match ($this->currency) {
            'USD' => '$' . number_format($this->cents / 100, 2),
            'ZWG' => 'ZiG ' . number_format($this->cents / 100, 2),
            default => $this->currency . ' ' . number_format($this->cents / 100, 2),
        };
    }
}
```

Store the daily rate in `settings` with a `fx_rate_usd_zwg` key and an `updated_at`, editable by owner and branch manager. Warn on the till screen if the rate is more than 24 hours old.

---

## 9. Authentication

Three different users, three different flows.

### 9.1 Clients: phone OTP

1. `POST /api/auth/otp/request` with `{ phone }`. Rate limit 3 per phone per 15 minutes, 10 per IP per hour.
2. Backend normalises the phone, generates a 6 digit code, stores a hash with a 5 minute expiry, sends via WhatsApp template (SMS fallback).
3. `POST /api/auth/otp/verify` with `{ phone, code }`. On success, find or create the user, issue a Sanctum token.
4. Max 5 attempts per code, then invalidate.

Never confirm whether a phone number exists in the system in the response. Same message either way.

### 9.2 Staff: password plus till PIN

- Web admin uses Sanctum SPA mode with an httpOnly cookie and CSRF, not a bearer token in localStorage.
- The till screen locks after 90 seconds idle and reopens with a 4 digit PIN so a barber does not log the receptionist out mid shift. The PIN unlocks the UI, it does not re-authenticate. The session is still the receptionist's.
- Enforce 2FA for `owner` and `branch-manager` using `laravel/fortify` TOTP.

### 9.3 Mobile: Sanctum tokens

- Store in `expo-secure-store`, never AsyncStorage.
- Token abilities scoped by role: `['client']` or `['staff','branch:3']`.
- Refresh on app foreground if the token is older than 7 days. Revoke all tokens on logout everywhere.
- Device row per token so a client can see and revoke sessions.

### 9.4 Guest booking

A first time client booking from the website should not have to create an account. Take name and phone, create the user unverified, send the OTP as part of the booking confirmation, and mark `client_profiles.source = 'web'`. The account exists from the first booking, which is the point.

---

## 10. API design

### 10.1 Conventions

- Base path `/api/v1`. Version from the first commit.
- JSON:API-ish shape but keep it simple: `{ data, meta, links }` for collections, `{ data }` for single resources.
- Laravel API Resources for every response. No raw model dumps, ever.
- `X-Branch-Id` header sets branch context on staff requests.
- Errors: standard Laravel 422 validation shape, plus a stable `code` string on 4xx business errors:

```json
{
  "message": "This slot is no longer available.",
  "code": "slot_taken",
  "errors": {}
}
```
The apps switch on `code`, never on the message text.

- Idempotency: `POST /appointments`, `POST /sales`, and `POST /payments` accept an `Idempotency-Key` header. Store the key with the response for 24 hours and replay it. This is what stops a double booking when the client taps twice on a bad connection.
- Rate limits: 60/min authenticated, 20/min guest, 3/15min for OTP request.

### 10.2 Endpoint list

**Public (no auth)**
```
GET  /v1/branches
GET  /v1/branches/{slug}
GET  /v1/branches/{slug}/services            price list for that branch
GET  /v1/service-categories
GET  /v1/styles                              gallery, filterable
GET  /v1/styles/{slug}
GET  /v1/branches/{slug}/availability        ?service_ids=&date=&staff_id=
GET  /v1/reviews                             published only
POST /v1/auth/otp/request
POST /v1/auth/otp/verify
POST /v1/bookings/guest                      creates user + appointment
POST /v1/qr/{token}/scan                     returns branch + queue state
```

**Client (auth: client ability)**
```
GET    /v1/me
PATCH  /v1/me
GET    /v1/me/appointments                   ?status=upcoming|past
POST   /v1/me/appointments
GET    /v1/me/appointments/{ulid}
PATCH  /v1/me/appointments/{ulid}            reschedule
DELETE /v1/me/appointments/{ulid}            cancel, subject to policy
POST   /v1/me/appointments/{ulid}/reference-photo
GET    /v1/me/queue-status                   position + estimated wait
GET    /v1/me/visits                         style history
GET    /v1/me/loyalty                        balance + ledger
GET    /v1/me/subscriptions
GET    /v1/me/addresses      POST/PATCH/DELETE
GET    /v1/me/skin-profile   PUT
POST   /v1/me/reviews
GET    /v1/me/referral                       code + status of invitees
POST   /v1/me/devices                        push token registration
```

**Staff (auth: staff ability + permission)**
```
GET   /v1/branches/{branch}/queue
POST  /v1/branches/{branch}/walk-ins         log a walk in, issue account if new
POST  /v1/appointments/{ulid}/assign
POST  /v1/appointments/{ulid}/check-in
POST  /v1/appointments/{ulid}/start
POST  /v1/appointments/{ulid}/complete
POST  /v1/appointments/{ulid}/no-show
POST  /v1/appointments/{ulid}/visit-record
POST  /v1/appointments/{ulid}/photos          before|after, consent enforced

GET   /v1/clients                             search by phone, name, account no
POST  /v1/clients
GET   /v1/clients/{ulid}
PATCH /v1/clients/{ulid}
POST  /v1/clients/merge

POST  /v1/sales                               open a sale
POST  /v1/sales/{ulid}/items
POST  /v1/sales/{ulid}/payments
POST  /v1/sales/{ulid}/close
POST  /v1/sales/{ulid}/void
POST  /v1/sales/{ulid}/refund
GET   /v1/sales/{ulid}/receipt                pdf or whatsapp send

GET   /v1/drawer/current
POST  /v1/drawer/open
POST  /v1/drawer/close

GET   /v1/branches/{branch}/stock
POST  /v1/stock/adjust
POST  /v1/stock/transfer

GET   /v1/reports/daily                       ?date=&branch=
GET   /v1/reports/staff-performance
GET   /v1/reports/services
GET   /v1/reports/retention
GET   /v1/reports/stock-valuation
```

**Admin**
```
CRUD /v1/admin/branches
CRUD /v1/admin/services      + POST /v1/admin/branches/{b}/services (pricing)
CRUD /v1/admin/products
CRUD /v1/admin/styles
CRUD /v1/admin/plans
CRUD /v1/admin/staff
CRUD /v1/admin/loyalty-rules
CRUD /v1/admin/message-templates
CRUD /v1/admin/house-call-zones
GET  /v1/admin/audit-log
GET  /v1/admin/settings   PUT
```

### 10.3 The availability endpoint

This is the hardest read in the system. Get it right once and both apps just consume it.

Input: branch, service ids, date range, optional staff id.

Algorithm:
1. Sum service durations plus buffers to get the required block.
2. Pull staff who work at that branch, who can perform every requested service, and who are on shift that day.
3. Subtract existing appointments, time off, and breaks.
4. Slice the remainder into slots on a 15 minute grid, discarding any slot where `slot_start + required_block` crosses the shift end or an existing booking.
5. If a service `requires_patch_test`, exclude slots less than `patch_test_lead_hours` away and return a `patch_test_required` flag so the UI can explain why.
6. Cache per branch per day for 60 seconds, bust on any appointment write for that branch and date.

Return slots grouped by staff, and a merged `any_staff` list.

### 10.4 Booking concurrency

Two clients tapping the same 10:00 slot is not a rare edge case, it is Saturday morning.

```php
DB::transaction(function () use ($data) {
    $conflict = Appointment::where('staff_id', $data['staff_id'])
        ->whereIn('status', ['pending','confirmed','checked_in','in_progress'])
        ->where('scheduled_start_at', '<', $data['end'])
        ->where('scheduled_end_at', '>', $data['start'])
        ->lockForUpdate()
        ->exists();

    if ($conflict) {
        throw new SlotTakenException();
    }

    return Appointment::create($data);
});
```

Add a database level guard as well. On PostgreSQL use an exclusion constraint on a `tstzrange`. On MySQL, a unique index on `(staff_id, scheduled_start_at)` catches the exact duplicate case, and the locked read catches overlaps.

---

## 11. Core workflows

### 11.1 Walk in, first visit

```
Reception taps New walk in
  -> enter phone
  -> system normalises and searches
       found    -> show client card, history, last cut
       not found-> name + phone form
  -> create user, client_profile, account number MB-0143, referral code
  -> pick service(s) and barber (or Any)
  -> appointment created: type=walkin, status=checked_in, queue_position=n
  -> queue board updates over websocket
  -> WhatsApp welcome template fires (queued job)
       account number, price list link, app link, MENU keyword
  -> barber sees the job on their phone or the chair tablet
```

Target: under 45 seconds at the desk. That means the form is phone, name, service. Everything else the client fills in later from their phone.

### 11.2 QR self check in

Each branch has QR codes at the door and one per chair, backed by `qr_tokens`.

```
Scan -> /q/{token}
  known device? -> show name, confirm, join queue
  new?          -> phone + OTP, then join queue
  -> pick service, pick style from gallery OR upload a reference photo
  -> confirmation screen: position 3, roughly 25 minutes
  -> push notification when 1 away
```

The QR resolves to a web page, not an app store link. The app is offered, never required. Someone standing in your shop should not have to install anything to join the queue.

### 11.3 Scheduled booking

```
Choose branch -> choose services -> see combined duration and price
  -> choose barber or Any -> availability grid
  -> choose slot -> add style or reference photo -> confirm
  -> deposit required? (set per branch, per service, or for new clients only)
       yes -> payment link -> hold slot 15 minutes pending payment
       no  -> confirmed immediately
  -> confirmation via WhatsApp + push
  -> reminders at 24h and 2h
  -> no show policy applied after grace period
```

### 11.4 House call

```
Client picks House call -> saved address or new one (map pin)
  -> system resolves the zone by distance from branch
       outside all zones -> polite decline with nearest branch suggested
  -> travel fee shown before confirming, added as a sale line item
  -> assignment: nearest available barber with accepts_house_calls
  -> barber gets a job card: address, directions note, services, kit checklist
  -> departed / arrived timestamps from the barber's phone
  -> payment on completion, same till flow, marked as house_call
```

Safety item worth building: a barber can flag a house call address, and a flagged address requires manager approval before a future booking is accepted.

### 11.5 Checkout

```
Complete appointment -> sale opens pre-filled with the booked services
  -> add products (barcode or search)
  -> apply subscription redemption if active
  -> apply loyalty redemption (capped, e.g. 50% of the bill)
  -> apply discount (permission gated, reason required over 10%)
  -> take payment, possibly split across methods and currencies
  -> close sale: stock decrements, loyalty earns, commission attributes,
     receipt to WhatsApp, review request queued for 2 hours later
```

### 11.6 The cut due reminder

This is the retention engine. Do not send it on a fixed 4 week timer.

```
After each completed visit:
  - recompute average_cycle_days = median gap between last 5 visits
    (median, not mean, so one holiday gap does not skew it)
  - fall back to the service's next_visit_recommended_days for new clients
  - schedule a reminder_schedules row at last_visit + cycle - 3 days
  - cancel it automatically if the client books in the meantime
```

Send between 09:00 and 18:00 Harare time only. One reminder per client per 14 days across all types, enforced in a single `CanMessage` check, so cut due, birthday, and win back never stack up on the same person in one week.

### 11.7 Loyalty

- Earn on every closed sale: points from `loyalty_rules`, written as one ledger row.
- Redeem: client chooses at checkout, capped at a configurable share of the bill, written as a negative ledger row plus a `points` payment row.
- Referral: referee's first completed appointment qualifies, both sides get a bonus row, referral status moves to `rewarded`.
- Balance is always `SUM(points) WHERE client_id = ? AND (expires_at IS NULL OR expires_at > now())`. Cache it for 60 seconds. Never store it in a mutable column that can drift.

---

## 12. WhatsApp integration

Use the **Meta WhatsApp Cloud API** directly. Avoid unofficial libraries that drive a browser session: they get numbers banned, and a banned number takes your client list's only reliable channel with it.

### 12.1 Setup order

1. Meta Business account, verified.
2. WhatsApp Business Account, phone number registered (a number not currently on the WhatsApp consumer app).
3. Permanent system user access token, stored in `.env`, rotated quarterly.
4. Webhook endpoint verified for `messages` and `message_status`.
5. Submit message templates for approval. **Approval takes days, so submit these while you are still building.**

### 12.2 Templates to submit first

| Key | Category | Purpose |
|---|---|---|
| `welcome_account` | Utility | Account number, price list link, app link |
| `otp_login` | Authentication | 6 digit code |
| `booking_confirmed` | Utility | Date, time, branch, barber, reference |
| `booking_reminder_24h` | Utility | With reschedule and cancel buttons |
| `booking_reminder_2h` | Utility | Short nudge |
| `queue_almost_up` | Utility | You are next |
| `cut_due` | Marketing | Retention nudge, requires marketing opt in |
| `receipt` | Utility | Total, items, points earned |
| `review_request` | Utility | One tap rating link |
| `house_call_on_the_way` | Utility | Barber has left, rough ETA |

Utility templates can be sent outside the 24 hour window. Marketing templates need explicit opt in, tracked in `client_profiles.marketing_opt_in` with a timestamp, and every marketing send must honour a STOP reply.

### 12.3 Inbound keywords

Handle the free form 24 hour window with a small keyword router, not an AI agent, in phase 1.

```
MENU    -> interactive list: Book, Prices, My next appointment, Location, Talk to us
BOOK    -> deep link to the booking page with the client pre-identified
PRICES  -> link to the branch price list
STOP    -> marketing_opt_in = false, confirm, never message marketing again
HELP    -> hand off to a human, flag in the admin inbox
```

Everything else goes to an admin inbox for a human. Log every inbound and outbound message in `message_logs` against the client.

### 12.4 Implementation shape

```php
interface MessagingChannel {
    public function sendTemplate(string $to, string $template, array $vars): MessageResult;
    public function sendText(string $to, string $body): MessageResult;
}

// WhatsAppCloudChannel, SmsChannel (fallback), LogChannel (local dev)
```

Bind by environment. Local development uses `LogChannel` so nobody messages a real client from a laptop. Queue every send on a dedicated `messaging` queue with retry and exponential backoff, and record `cost_cents` per send so the owner can see what the channel costs.

---

## 13. Real time

Use **Laravel Reverb** (first party websockets, self hosted) or Pusher if you would rather not run it.

Channels:
```
private-branch.{id}.queue        queue changes, new walk ins
private-branch.{id}.appointments assignment, status changes
private-staff.{id}                 jobs assigned to me
private-client.{id}                my queue position, my booking status
presence-branch.{id}.floor       who is on shift right now
```

Events: `WalkInLogged`, `QueuePositionChanged`, `AppointmentAssigned`, `AppointmentStatusChanged`, `SaleClosed`, `StockLow`.

The queue board and the barber's job list subscribe. Everything else can poll. Do not put the whole app on websockets.

---

## 14. Web application

### 14.1 Two surfaces, one build

| Surface | Route prefix | Audience |
|---|---|---|
| Public site | `/` | Anyone. Marketing, prices, booking. |
| Admin | `/admin` | Staff, behind auth. |

Server render the public site if SEO matters to you, using Inertia or a separate Next.js front. If it does not, a Vite SPA with good meta tags is enough for a local shop. Decide before you start: retrofitting SSR is expensive.

### 14.2 Public site pages

```
/                       hero, three ways to book, featured styles, socials
/branches               list + map
/branches/{slug}        hours, team, live queue length, directions
/services               category tabs, branch price selector
/styles                 gallery grid, filter by category and hair type
/styles/{slug}          large image, price, Book this cut
/skin                   the facial offer, the taglines, before and afters
/plans                  monthly cut plans and facial packages
/book                   the booking wizard
/q/{token}              QR landing, join queue
/r/{code}               referral landing
/account                client portal: bookings, history, points, profile
/policies               cancellation, no show, photo consent, privacy
```

### 14.3 Admin screens

| Screen | Purpose | Permission |
|---|---|---|
| Floor board | Live queue, chairs, who is with whom. Default landing for reception. | `queue.view` |
| Day view | Calendar, drag to reassign, colour by status | `appointment.view.branch` |
| Walk in | Fast phone-first form | `appointment.create` |
| Client search | Phone, name, or account number | `client.view` |
| Client card | History, style record, points, notes, skin profile | `client.view` |
| Till | Sale, items, split payment, receipt | `sale.create` |
| Drawer | Open, close, count, variance | `drawer.close` |
| Visit record | Guards, formula, photos, next visit | `visit.record.write` |
| House calls | Today's jobs, map, assignment | `appointment.view.branch` |
| Catalog | Services, categories, styles | `service.update` |
| Pricing | Per branch grid, bulk edit | `price.update` |
| Products and stock | Levels, adjustments, transfers, reorder alerts | `stock.view` |
| Staff | Roster, hours, time off, commission | `staff.view` |
| Plans and subscriptions | Active plans, sessions used | `plan.manage` |
| Loyalty | Rules, manual adjustments, statements | `loyalty.view` |
| Reviews | Inbox, respond, publish | `review.view` |
| Messages | Template manager, inbound inbox, send history | `message.send` |
| Reports | Daily, staff, service mix, retention, stock | `report.view.*` |
| Branches | Add, edit, hours, zones | `branch.update` |
| Settings | FX rate, deposit policy, cancellation policy, opening hours | `settings.manage` |
| Audit log | Who changed what | `audit.view` |

### 14.4 Frontend structure

```
apps/web/src/
  api/            axios client, interceptors, endpoint modules
  auth/           session, guards, permission hooks
  components/     shared UI
  features/
    queue/  booking/  clients/  till/  catalog/  stock/  staff/
    loyalty/  reports/  messages/  settings/
  hooks/
  lib/            money, dates, phone, validation
  pages/
  routes.tsx
```

Permission gating in React mirrors the backend, it does not replace it:

```tsx
const { can } = usePermissions();

{can('sale.void') && <Button onClick={voidSale}>Void</Button>}
```

Always enforce the same rule server side. The frontend check is a courtesy, not a control.

TanStack Query key convention, shared with mobile:

```ts
['branch', branchId, 'queue']
['appointments', { branchId, date }]
['client', clientUlid]
['client', clientUlid, 'loyalty']
['availability', { branchSlug, serviceIds, date }]
```

### 14.5 Offline and poor connectivity

Harare load shedding and patchy mobile data are design constraints, not edge cases.

- The till holds an open sale in local storage and retries submission with the idempotency key.
- The queue board shows a stale data banner if the websocket drops, and falls back to 30 second polling.
- Receipt sending is queued server side, so a failed send retries without the cashier doing anything.
- Never block the UI on a message send.

---

## 15. Mobile application

### 15.1 Two apps in one codebase

Ship a single Expo app with a role switch at login, or two builds from one repo if the store listings need to differ. One codebase either way.

**Client app**
```
Onboarding      phone -> OTP -> name -> home branch
Home            next appointment, queue status, points, quick rebook
Book            branch -> services -> barber -> slot -> style -> confirm
Styles          gallery, save favourites, upload a reference photo
My cuts         visit history with photos and the exact formula used
Skin            skin profile, facial history, before and afters
Wallet          points balance, ledger, active plans, referral code
Shop            products, click and collect at a branch
Profile         addresses, notification preferences, saved cards later
```

**Staff app**
```
Today           my appointments, next client, queue
Client          card, history, notes, style record
Record visit    guards, formula, photos, next visit recommendation
House calls     job card, directions, departed and arrived, checklist
Earnings        my sales, my commission, my ratings
Schedule        my hours, request time off
```

### 15.2 Native features that matter

| Feature | Why |
|---|---|
| QR scanner | Check in at the door in two taps |
| Camera + gallery | Reference photos, before and after |
| Push notifications | Queue nudge, reminders, cut due |
| Calendar write | Add booking to the phone calendar |
| Deep links | `magnetic://appointment/{ulid}`, WhatsApp buttons land in the app |
| Maps | House call address pin and directions |
| Biometric unlock | Staff app, protects client data on a lost phone |

### 15.3 Image handling

Reference photos from clients arrive as 6MB phone shots on a 3G connection. Compress before upload.

```ts
const compressed = await ImageManipulator.manipulateAsync(
  uri,
  [{ resize: { width: 1080 } }],
  { compress: 0.7, format: ImageManipulator.SaveFormat.JPEG }
);
```

Upload direct to S3 with a presigned URL from the API, not through the Laravel server. Strip EXIF location data on the server before storing, always.

### 15.4 Offline behaviour

- Cache the client's next appointment, points balance, and style history with MMKV so the home screen renders instantly and works with no signal.
- Queue visit records written by staff and sync on reconnect.
- Show an explicit offline banner. Never silently fail a booking.

### 15.5 Release

- Expo EAS Build and EAS Update. Push JavaScript fixes over the air without a store review.
- Native version bumps only when you add a native module.
- Test on a low end Android device on purpose. That is what most of your clients use.

---

## 16. Reports

| Report | Contents | Audience |
|---|---|---|
| Daily takings | Revenue by service, product, currency, payment method. Drawer variance. | Owner, manager |
| Staff performance | Cuts done, revenue, average ticket, rebook rate, rating, commission owed | Owner, manager |
| Service mix | Which services sell, average duration versus booked duration | Owner, manager |
| Retention cohort | Percentage of clients from month N who returned in months N+1, N+2, N+3 | Owner |
| Client value | Top clients by lifetime value, visit frequency, at risk list | Owner, manager |
| At risk | Clients past their average cycle plus 14 days who have not booked | Manager |
| Stock | Valuation, movement, reorder list, shrinkage | Owner, manager |
| Skin | Facial uptake, repeat rate, package usage | Owner |
| Channel | Where bookings come from: app, web, WhatsApp, walk in, QR | Owner |

Two numbers to put on the owner's home screen, because they drive every decision: **repeat visit rate** (share of this month's clients who have visited before) and **average days between visits**.

Aggregate nightly into a `daily_branch_metrics` table. Do not compute cohort retention live against the sales table.

---

## 17. Security and compliance

### 17.1 Zimbabwe Data Protection Act (Chapter 11:12)

You are processing personal data including biometric-adjacent images and health-adjacent skin information. Practical obligations:

- Collect the minimum: name, phone, and what you need to do the service.
- State the purpose at collection. A one line notice at the walk in form and in the app onboarding.
- Get explicit, separate consent for: marketing messages, before and after photos, and public use of any photo. Three separate toggles, each with a timestamp in the database. A signed consent form scan attaches to the client record for skin services.
- Let a client request their data and request deletion. Build both as admin actions, log both.
- Register with POTRAZ as a data controller if your processing volume requires it. Check current thresholds.

### 17.2 Technical controls

- TLS everywhere, HSTS on.
- Encrypt at rest: `skin_profiles.allergies`, `contraindications`, and client `notes` via Laravel's `encrypted` cast.
- Photos in a private S3 bucket, served only through short lived signed URLs (5 minutes), never public.
- Strip EXIF on upload.
- Activity log on every write to sales, payments, prices, stock, loyalty, and client records.
- Never log a full phone number, an OTP, or a token. Mask in logs: `+2637****820`.
- Enforce 2FA for owner and manager roles.
- Sanctum tokens expire: 30 days for clients, 12 hours for staff on shared devices.
- Automated daily encrypted backups with `spatie/laravel-backup` to a different provider than your application host. Test a restore before go live, and once a quarter after.

### 17.3 Payments

If you take card payments online, do not touch card data. Use a hosted checkout from your provider (Paynow for EcoCash, OneMoney, and Zimswitch, or Stripe for international cards) and store only the provider's reference. PCI scope stays with them.

---

## 18. Testing

| Layer | Tool | Minimum bar |
|---|---|---|
| Backend unit | Pest | Money maths, availability algorithm, loyalty calculation, phone normalisation |
| Backend feature | Pest + database | Every endpoint, every role, permission denial cases |
| Static analysis | Larastan level 6 | No new errors |
| Frontend unit | Vitest | Formatters, hooks, reducers |
| Frontend component | Testing Library | Booking wizard, till, walk in form |
| E2E | Playwright | Book, check in, check out, refund |
| Mobile | Jest + Maestro | Onboarding, book, QR scan |

Tests that must exist before go live, because these are the ones that cost real money when they break:

1. Two concurrent bookings for the same slot: one succeeds, one gets `slot_taken`.
2. A barber at branch A cannot read a client record from branch B.
3. A receptionist cannot void a sale.
4. A split payment in USD and ZiG reconciles to the sale total.
5. Loyalty balance equals the sum of the ledger after 100 random earn and redeem operations.
6. A cancelled appointment cancels its pending reminders.
7. A client who opts out of marketing receives no marketing template.
8. A photo upload without consent is rejected.

---

## 19. Environments and deployment

### 19.1 Environments

| Env | Purpose | Data |
|---|---|---|
| local | Docker Compose, seeded | Fake |
| staging | Mirror of production | Anonymised copy |
| production | Live | Real |

### 19.2 Infrastructure

Modest requirements. One branch with 8 chairs is not a scale problem.

- Application: 2 vCPU, 4GB. Laravel Forge or Ploi on Hetzner, DigitalOcean, or AWS Lightsail.
- Database: managed MySQL 8, 2GB, with automated backups and point in time recovery.
- Redis: 1GB.
- Object storage: S3 or R2, with a CDN in front for the style gallery.
- Queue: two Horizon workers, one for `default`, one for `messaging`.
- Latency matters for a South African or European region if your clients are in Harare. Test both.

### 19.3 Scheduler

```php
// routes/console.php
Schedule::command('reminders:dispatch')->everyFifteenMinutes();
Schedule::command('metrics:aggregate')->dailyAt('01:00');
Schedule::command('loyalty:expire')->dailyAt('02:00');
Schedule::command('subscriptions:expire')->dailyAt('02:30');
Schedule::command('cycles:recompute')->dailyAt('03:00');
Schedule::command('stock:low-alert')->dailyAt('07:00');
Schedule::command('backup:run')->dailyAt('04:00');
Schedule::command('drawer:auto-close-stale')->dailyAt('23:59');
```

### 19.4 CI/CD

GitHub Actions:
1. On pull request: Pint, Larastan, Pest, Vitest, build web.
2. On merge to `main`: deploy API and web to staging, run migrations, smoke test.
3. On tag: deploy to production with `php artisan down --render=maintenance`, migrate, `up`. Horizon restarts automatically.
4. Mobile: EAS Build on tag, EAS Update for JavaScript only changes.

Never run `migrate:fresh` against anything but local.

---

## 20. Build phases

### Phase 1: Run the shop (weeks 1 to 6)

Goal: the shop stops using a paper book.

- Laravel skeleton, auth, Spatie roles with teams, seeders
- Branches, staff, services, categories, per branch pricing
- Clients with phone normalisation, account numbers, duplicate search
- Walk in logging and the live queue board
- Basic scheduled bookings from the admin side
- Till: sale, items, split payment, close, receipt
- Cash drawer open and close
- WhatsApp welcome and receipt templates
- Daily takings report
- Public site: branches, services, prices, styles, contact

**Done when:** every client through the door on a normal Saturday exists in the system with an account number, and the day's takings reconcile without a calculator.

### Phase 2: Put it in their hands (weeks 7 to 12)

- Public booking wizard with the availability engine
- Client OTP login and account portal
- QR check in at door and chairs
- Style gallery with reference photo upload
- Deposits and cancellation policy
- Reminders at 24h and 2h
- Loyalty earn and redeem, referral codes
- Reviews after visit
- React Native client app, both stores
- Push notifications

**Done when:** a third of bookings arrive without a staff member typing them, and the client app is on the stores.

### Phase 3: Grow the group (weeks 13 to 20)

- Second branch live on the same catalog
- Products, stock, transfers, reorder alerts
- House call zones, fees, assignment, job cards
- Skin profiles, consent capture, before and after photos
- Plans and subscriptions
- Cut due reminders driven by cycle
- Staff mobile app
- Owner dashboard and the full report set
- Commission and chair rental reporting

**Done when:** the owner can open one screen and see both branches, and a barber can complete a house call end to end from their phone.

---

## 21. Decisions to make before writing code

Answer these now. Each one is expensive to change later.

1. **Deposits.** Required for everyone, for new clients only, for house calls only, or never? What percentage?
2. **Cancellation window.** Free cancellation up to how many hours? What happens inside that window, and what happens on a no show? A strike system or a forfeited deposit?
3. **Barber pay model.** Employed on salary, commission per service, chair rental, or a mix per person? This determines `branch_user` fields and the whole commission report.
4. **Any barber bookings.** Allowed, or must a client always pick a person? Affects assignment logic significantly.
5. **Walk in versus booking priority.** Does a 10:00 booking jump the walk in queue at 10:00? Say so explicitly, put it on the wall, and code it.
6. **Points value.** How many points per dollar, and what is a point worth on redemption? Cap redemption at what share of a bill?
7. **Price visibility.** Are prices public on the website, or shown only after choosing a branch?
8. **Photo consent default.** Off by default, on for internal records only, or opt in for public use? Recommendation: off by default, separate toggle for public use.
9. **Second branch timing.** If it is more than 6 months away, keep phase 3 out of the phase 1 schema conversations, but keep `branch_id` on everything anyway.
10. **Currency of record.** Which currency do reports default to? Recommendation: USD as the reporting currency, transactions in whatever was tendered.

---

## 22. Getting started

```bash
# API
composer create-project laravel/laravel apps/api
cd apps/api
composer require laravel/sanctum laravel/horizon spatie/laravel-permission \
  spatie/laravel-sluggable spatie/laravel-medialibrary spatie/laravel-activitylog \
  spatie/laravel-query-builder propaganistas/laravel-phone

php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
# set 'teams' => true and 'team_foreign_key' => 'branch_id' BEFORE migrating
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider"
php artisan migrate

# Web
npm create vite@latest apps/web -- --template react-ts
cd apps/web && npm i @tanstack/react-query react-router-dom axios \
  react-hook-form zod @hookform/resolvers date-fns

# Mobile
npx create-expo-app apps/mobile --template
cd apps/mobile && npx expo install expo-secure-store expo-camera \
  expo-barcode-scanner expo-image-picker expo-notifications \
  @react-navigation/native @tanstack/react-query
```

Build order inside phase 1: migrations and models first, then the roles seeder, then the client and walk in flow end to end through the API with tests, then the queue board, then the till. Resist building screens before the walk in flow works, because the walk in flow is where every assumption about the data model gets tested.
