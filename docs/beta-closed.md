# Closed beta — invite-only (Path B)

Zdravje360 is in a **closed beta**. This document is the canonical onboarding policy for testers and staff.

## What this means

- **No public registration.** There is no sign-up form and no self-service account creation.
- **Staff-provisioned accounts only.** Admins create `member` users in Filament (`/admin` → Users).
- **Password reset (A2) is not available** in this beta. If a tester forgets a password, an admin must set a new one in Filament.
- **Not a broad public launch.** Access is limited to invited testers; do not share production URLs publicly until product approves.

## Provisioning a beta tester (staff)

1. Log in to Filament as **admin** at `{API_URL}/admin`.
2. Open **Users** → **Create**.
3. Set name, email, role **`member`**, and a **strong unique password** (communicate securely out of band).
4. Tell the tester:
   - Web URL (staging or production)
   - Email and temporary password
   - Link to [Terms](/terms) and [Disclaimer](/disclaimer) on the web app
   - That reviews and forum posts are **moderated** before publication

## Tester expectations

- Use the platform for **informational** purposes only; it does not provide diagnosis or emergency care.
- For emergencies, call **194** or **112**.
- Do not expect pharmacy prices to be purchase offers on this site.
- UI is **Macedonian-first**; directory content in the database may still be in English or mixed languages during beta (**content debt** — see [beta-content-readiness.md](./beta-content-readiness.md)).

## Engineering boundaries (R1)

Not in closed beta: Meilisearch, sponsorships, AI symptom guidance, public registration, password reset, CMS for legal pages (legal copy is static in `apps/web`).
