# Symptom guidance — safety and scope (Phase 3f-a)

This document is a **hard prerequisite** for implementing symptom guidance (internal label: *triage*). Engineering must not ship user-facing guidance flows until this scope is agreed and referenced in PRs.

**Not legal advice.** Production copy requires review by qualified counsel for North Macedonia.

---

## Purpose

Zdravje360 offers **general, informational symptom guidance** to help users think about next steps (e.g. consider contacting a professional, use emergency services, or read educational context). It does **not** provide diagnosis, treatment plans, prescriptions, or emergency dispatch.

---

## In scope (3f-a foundation)

- One **published** guidance flow at a time (staff-configured in Filament).
- Fixed steps with **structured options only** (no free-text symptoms).
- **Red-flag** checklist with **hard stop** to an emergency outcome.
- **Rule-based** outcome selection on the server (rules are not exposed via public API).
- Sessions are **always anonymous**: never linked to an account, even when a valid Sanctum token is sent (owner decision 2026-10-06). The client continues a session with a per-session secret issued at creation; only its hash is stored.
- Minimal audit storage: session id, token hash, flow id, structured answers, outcome code, timestamps, `emergency_stopped`.
- Public web label: **“Symptom guidance”** (not “Triage” in UI).
- Handoffs: home, doctors list, facilities list, emergency numbers — no booking or ranked recommendations.

---

## In scope (v2, 2026-10 — docs/triage-flows.md)

v2 extends 3f-a; everything above still holds unless stated here.

- **Several published flows**, one per symptom, loaded from reviewed files as
  versioned content. A visitor picks **up to three** symptoms (search or body
  map); one combined red-flag screen is asked **before any question**, then the
  flows run most urgent first. The result is the **most urgent** outcome of all;
  an emergency outcome in any flow stops the rest.
- **Clinician sign-off before publication.** A new or changed flow is a draft
  („нацрт — чека лекарски преглед“) and is hidden from visitors until a staff
  member records a clinician's approving review of that exact version and
  publishes it. Only the v1 general flow, live before sign-off existed, was
  carried over without one (config `triage.grandfathered_flows`). Staff preview
  any version in the admin simulator; nothing is stored by it.
- **Linter** (`php artisan triage:lint`, also on import and publish): every
  path ends in an outcome, nothing unreachable, red flags first and cited, every
  emergency outcome offers 194 and 112, every other outcome has safety-netting,
  special populations (infants, children, pregnancy, older adults, chronic
  conditions) handled or explained, public sources cited, Macedonian alphabet.
- **Structured answers only**, now also bounded numbers (temperature, days,
  pain 0–10) and anonymous demographics (age in months, sex at birth,
  pregnancy, chronic conditions). Still no free text; symptom search runs in
  the browser and its text never reaches the server.
- **Outcome levels**: emergency (194/112), same day, 1–2 days, GP this week,
  pharmacist, self-care — each with reasons, what to do now, „if … then at
  once …“ safety-netting and where to go (directory links built in the
  browser; the city stays in the browser).
- **Mental-health crisis**: self-harm is a global red flag leading to a crisis
  outcome with 194/112 and the nearest emergency department; never self-care.
- **Summary for the doctor**: built and printed / shared in the browser only;
  no e-mail sending (it would identify the visitor).
- **Anonymous aggregate counts** of outcomes per flow per week
  (`triage_outcome_stats`), outliving the session purge because they hold no
  link to any session or answer.
- **AI seam only**: `TriageEscalation` interface with a `NullEscalation`, flag
  `triage.escalation.enabled` off; called when the visitor finds no matching
  symptom, and may only suggest published flows. No external call exists.

## Explicitly out of scope (3f-a)

- AI / LLM assistance (**3f-b**).
- Diagnostic labels (“you have…”, condition names as outcomes).
- Emergency **decision engines** beyond static red flags and an emergency screen.
- Medical device integrations, clinician escalation, telehealth.
- Symptom ontologies (SNOMED, ICD, large taxonomies).
- Free-text input, IP address logging, behavioral analytics.
- Multiple concurrent published flows or audience-specific pathways
  *(superseded by v2 above)*.
- Member “my guidance history” UI.
- Appointment booking or provider recommendation logic.

---

## Banned claims and language (UI + API outcomes)

Do **not** use in user-facing or outcome copy:

- “Diagnosis”, “diagnosed”, “you have”, “confirmed”, “definitely”, “certain”
- “Prescription”, “take this medication”, “dose”, “treatment plan”
- “Emergency room required” as a deterministic instruction (prefer “consider seeking urgent care” / call emergency services)
- Any implication the platform is a medical device or licensed clinician

**Prefer:** “general information”, “may help you decide next steps”, “consider speaking with a healthcare professional”, “if you are worried or symptoms worsen…”.

---

## Mandatory disclaimers and gates

1. **Pre-start:** User must confirm understanding that content is informational, not medical advice, and not for delaying emergency care.
2. **Every step:** Compact emergency line (194 / 112, both `tel:` links) visible.
3. **Emergency shortcut:** “I need emergency help now” available during the flow → terminal emergency screen.
4. **Red flags:** Any selected red flag **short-circuits**; user must not return to the normal questionnaire without starting a new session.
5. **Results:** Repeat non-diagnostic framing; no disease-specific titles in v1.

---

## Fail-closed behavior

| Condition | Behavior |
|-----------|----------|
| No published flow | `GET /triage/flow` → 404; web shows safe fallback |
| Invalid / unknown session | 404; no guessed outcome |
| Session already completed | Reject further answer updates; `complete` is idempotent or 409 |
| Emergency stopped | `complete` returns **only** emergency outcome |
| Rule evaluation error | Log server-side; return generic safe outcome + support message |
| Rate limit exceeded | 429 JSON envelope |

---

## Data retention

| Data | Retention (v1 default) |
|------|---------------------------|
| `triage_sessions` + `triage_session_answers` | **90 days**, then deleted by `triage:purge-old-sessions` (scheduled daily 03:15, `routes/console.php`) |
| No IP, no user-agent fingerprinting | Unless explicitly approved in a future revision |
| No free text | Enforced by API validation |

Adjust retention only with legal sign-off and a doc update.

---

## API exposure

Public endpoints may return: flow metadata, steps, option labels/codes, red-flag labels/codes, session id, session token (at creation only), outcome title/body, handoff links.

Public endpoints must **not** return: rule definitions, rule priorities, internal staff notes.

---

## Navigation and prominence

- **Secondary / contextual** entry (e.g. site footer link).
- **Not** a homepage hero feature.
- Label: informational guidance, not “triage” or “diagnosis”.

---

## References

- [PROJECT_BRIEF.md](../PROJECT_BRIEF.md) — symptom guidance (non-diagnostic)
- [docs/architecture.md](./architecture.md) — target triage/AI behavior (3f-b)
- [TASKS.md](../TASKS.md) — Phase 3f-a / 3f-b split
