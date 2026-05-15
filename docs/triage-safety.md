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
- Anonymous sessions allowed; optional `user_id` when a valid Sanctum token is sent.
- Minimal audit storage: session id, flow id, structured answers, outcome code, timestamps, `emergency_stopped`.
- Public web label: **“Symptom guidance”** (not “Triage” in UI).
- Handoffs: home, doctors list, facilities list, emergency numbers — no booking or ranked recommendations.

---

## Explicitly out of scope (3f-a)

- AI / LLM assistance (**3f-b**).
- Diagnostic labels (“you have…”, condition names as outcomes).
- Emergency **decision engines** beyond static red flags and an emergency screen.
- Medical device integrations, clinician escalation, telehealth.
- Symptom ontologies (SNOMED, ICD, large taxonomies).
- Free-text input, IP address logging, behavioral analytics.
- Multiple concurrent published flows or audience-specific pathways.
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
2. **Every step:** Compact emergency line (194 / 112) visible.
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
| `triage_sessions` + `triage_session_answers` | **90 days**, then delete via scheduled command (to be added when ops ready) |
| No IP, no user-agent fingerprinting | Unless explicitly approved in a future revision |
| No free text | Enforced by API validation |

Adjust retention only with legal sign-off and a doc update.

---

## API exposure

Public endpoints may return: flow metadata, steps, option labels/codes, red-flag labels/codes, session id, outcome title/body, handoff links.

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
