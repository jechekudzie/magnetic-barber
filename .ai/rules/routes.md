---
paths:
  - routes/api.php
---

# Routes

## API v1 auth: two surfaces, two token abilities, one user table
Staff and clients are the same `users` table, separated by role. The API keeps them apart with **Sanctum token abilities**, issued at login and checked before any role or permission:

- Clients get `['client']` from `POST auth/client/verify`. They have no password — the phone number is the identity and a one-time code proves they hold it. Codes live in `verification_codes`, hashed, single use, 10 minutes, burned after 5 wrong guesses.
- Staff get `['staff']` from `POST auth/staff/login` with an email **or** phone plus password. Not every barber has a work email.
- Routes are grouped under `ability:client` / `ability:staff` (alias registered in `bootstrap/app.php`). A client token on a staff route is a 403 before anything else runs.

**Delivery of OTP codes is a driver** — `config('magnetic.otp_channel')`, bound in `AppServiceProvider`. The default `LogOtpChannel` does not deliver, and `OtpChannel::delivers()` being false is what makes the auth controller return the code in the response so the flow works on a laptop. Keyed on `delivers()`, never on the environment, so configuring a real SMS/WhatsApp channel closes that off even if `APP_ENV` is wrong.

**Two traps this cost us:**
- `SetPermissionsBranch` runs on the API stack, which is stateless. Guard every `$request->session()` touch with `hasSession()` or authenticated API requests 500.
- Spatie roles are per branch and resolve to **nothing** unless a team id is set. `SetPermissionsBranch` can't do it during login — there's no token yet — so `StaffAuthController` calls `setPermissionsTeamId()` itself before building the response. Without it a barber logs in with no roles and the app renders an empty navigation.

Staff requests name their branch with the `X-Branch-Id` header; the middleware only honours a branch the user actually works at.
