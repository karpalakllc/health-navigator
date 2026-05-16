# Community moderator onboarding

Short checklist for assigning a **client** user as a category-scoped forum moderator (P7c).

## Prerequisites

- Forum categories exist in admin (**Community → Forum categories**).
- `RolesAndPermissionsSeeder` has run (includes **Forum Moderator** role).

## Steps

1. **Users → Clients** — open the member account (or create one).
2. Under **Community roles**, assign **Forum Moderator**.
3. Under **Forum categories (scoped moderation)**, select one or more categories.
   - Leave **empty** only if this person should moderate **all** forum categories (rare for volunteers).
4. Save. The user can sign in at `/admin` with their normal email/password.

## What they can do

- See **Forum topics** and **Forum replies** queues (scoped to assigned categories when categories are selected).
- Approve, reject, pin, and lock content in those categories.
- They **cannot** edit doctors, facilities, reviews (unless given staff roles), analytics, or settings.

## What they cannot do

- Directory or review moderation (no `reviews.view` on the default Forum Moderator role).
- Create or delete forum categories (view-only).

## Removing access

- Remove the **Forum Moderator** role and clear category assignments on the client record.

## Daily digest (optional)

When pending items exist in queues they can see, they receive the **moderation digest** email (scheduled `moderation:send-digest` at 07:00). Staff with `reviews.view` get review counts too.
