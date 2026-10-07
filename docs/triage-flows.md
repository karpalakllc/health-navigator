# Symptom-guidance flows — file schema `zdravje.triage/1`

Internal label *triage*; public label „Насоки за симптоми“. This is the contract between
the guidance engine (T-ENGINE: `app/Services/Triage/V2`, `php artisan triage:import`,
`php artisan triage:lint`) and the flow content (T-CONTENT: JSON files). Safety scope:
[docs/triage-safety.md](./triage-safety.md). Any change to this schema bumps the
`schema` string and is announced here first.

---

## 1. Files and ownership

| Path (under `apps/api/`) | What | Owner |
|---|---|---|
| `database/data/triage/flows/<flow-key>.json` | One flow per file. File name = `key` + `.json`. | T-CONTENT (except `general.json`) |
| `database/data/triage/flows/general.json` | The pre-v2 general flow, carried over („Не го најдов мојот симптом“ fallback). | T-ENGINE |
| `database/data/triage/global/core.json` | Global red-flag screen + the shared `emergency_now` and `crisis` outcomes. | T-ENGINE |
| `database/data/triage/global/<name>.json` | Extra global red flags / outcome patches (e.g. verified crisis lines). Any number of files, merged in name order. | T-CONTENT |

- Encoding UTF-8, JSON (no comments, no trailing commas). 2-space indent preferred.
- Every user-facing string is Macedonian, formal plural „вие“, Macedonian alphabet only
  (the linter rejects й щ ъ ы ь э ю я ё). Medical terms explained in brackets.
- `"summary_en"` and `"notes_en"` are internal English and never shown publicly.
- Files are **content, not state**: publication status and clinician sign-off live in the
  database (Filament). `php artisan triage:import` loads files as **draft** versions; an
  unchanged file (same canonical hash) is a no-op, a changed one becomes a new draft
  version. The published version stays live until a newer one is reviewed and published.

## 2. Flow file — top level

```jsonc
{
  "schema": "zdravje.triage/1",          // required, exact string
  "key": "sore-throat-adult",            // required, kebab-case ^[a-z0-9]+(-[a-z0-9]+)*$, = file name, max 64
  "title": "Болки во грлото",            // required, public, ≤ 80 chars, a symptom — never a diagnosis
  "summary_en": "Adult sore throat ...", // required, internal English, 1–3 sentences
  "notes_en": "Assumptions ...",         // optional, internal
  "audience": {                          // required: who the flow is for (see §4 age bands)
    "age_bands": ["teen_13_17", "adult_18_64", "older_65_plus"]
  },
  "urgency_rank": 40,                    // required int 1–100; higher = potentially more urgent symptom;
                                         // with several symptoms the most urgent flow runs first
  "body_areas": ["throat"],              // required, ≥1, from §6
  "search_terms": ["грло", "болно грло", "голтање", "ангина"], // required, ≥3, Cyrillic, lower case;
                                         // Latin input is transliterated by the engine, do not add Latin
  "red_flags": [ ... ],                  // required, ≥1, §3 — always asked FIRST
  "scores": { ... },                     // optional, §7
  "entry": "q_duration",                 // required: id of the first question after the red-flag screen
  "nodes": { ... },                      // required, §5
  "outcomes": { ... },                   // required, §8
  "populations": { ... },                // required where relevant, §9
  "sources": [ ... ]                     // required, ≥1, §10
}
```

Ids: node, outcome, score and red-flag ids/codes are `^[a-z][a-z0-9_]*$`, ≤ 48 chars,
unique **within the flow across nodes and outcomes** (a `goto` names either).
Option `value`s are `^[a-z0-9][a-z0-9_]*$`.

## 3. Red flags (asked first, hard stop)

```jsonc
"red_flags": [
  {
    "code": "drooling_cannot_swallow",
    "label": "Не можете да голтате плунка или ви тече плунка од устата",
    "help": "Ова може да значи дека грлото е многу отечено.",   // optional, ≤ 300
    "when": { "demo": "age_band", "in": ["teen_13_17", "adult_18_64", "older_65_plus"] }, // optional
    "outcome": "global:emergency_now",  // optional; default "global:emergency_now"
    "source": "nhs-sore-throat"          // required: id from this flow's or the global sources
  }
]
```

- The red-flag screen is one checklist shown **before any question of any selected flow**:
  global red flags (from `global/*.json`) + the red flags of every selected flow
  (up to 3), each list in file order. The visitor ticks any that apply.
- **Any** ticked red flag → the session ends immediately with that flag's outcome (if several,
  the most urgent; ties → the first). No way back into the questionnaire without a new
  session (triage-safety.md, gate 4).
- `when` may reference **only demographics** (`demo`, §4) — the screen comes before answers.
  Use it for population-specific flags (e.g. infants under 3 months).
- `outcome` must be an outcome of level `emergency_now` — a flow-local one (tailored
  „while you wait“ instructions) or `global:emergency_now` / `global:crisis`.
- Do not repeat a global red flag in a flow (the linter warns on identical labels).
- Questions later in the flow may still route to emergency outcomes (e.g. temperature with
  a rash); red flags are the things that must stop the questionnaire *before* it starts.

## 4. Demographics (asked by the engine, once per session)

Asked before symptom selection; not part of the files. Reference them in conditions as
`{"demo": <field>, ...}`.

| Field | Type | Values |
|---|---|---|
| `age_band` | enum | `infant_0_3m` (< 3 months), `infant_3_12m` (3–11 months), `child_1_4`, `child_5_12`, `teen_13_17`, `adult_18_64`, `older_65_plus` |
| `age_months` | int | Age in whole months (derived from the age answer) |
| `age_years` | int | Age in whole years |
| `sex` | enum | `female`, `male`, `unspecified` (sex at birth; „не сакам да кажам“) |
| `pregnancy` | enum | `pregnant`, `postpartum` (birth in the last 6 weeks), `not_pregnant`, `unsure`, `not_asked` (asked only when sex ≠ male and age 10–55 years) |
| `conditions` | set | any of `immunosuppressed`, `diabetes`, `heart_disease`, `lung_disease`, `kidney_disease`, `pregnancy_complication_history` — or empty |

Safety-relevant reading: treat `pregnancy: "unsure"` like `pregnant` (the linter warns when a
condition lists `pregnant` without `unsure`), and `sex: "unspecified"` as possibly female.

## 5. Nodes

`nodes` is an object keyed by node id. Two node types: `question` and `info`.
Outcomes are separate (§8). Every node has `next` (§5.3).

### 5.1 `question`

```jsonc
"q_fever": {
  "type": "question",
  "kind": "yes_no",           // single | multi | yes_no | number | scale
  "text": "Дали имате температура (треска) 38 °C или повеќе?",  // ≤ 300
  "help": "Ако немате топломер: дали сте топли на допир и ве тресе?", // optional, ≤ 500
  "next": "q_duration"
}
```

| kind | Extra fields | Stored answer (`values`) |
|---|---|---|
| `single` | `options` (2–12) | one option value |
| `multi` | `options` (2–15); an option may be `"exclusive": true` („Ништо од наведеното“) | 0..n option values (0 only if `"optional": true`) |
| `yes_no` | `"allow_unsure": true` adds „Не знам“ | `yes` / `no` / `unsure` |
| `number` | `unit`, `min`, `max`, `step` (default 1), optional `alt_units`, optional `"allow_unknown": true` | the number in `unit` as a string, or `unknown` |
| `scale` | optional `min_label`, `max_label` (≤ 40); always 0–10 integers (pain) | `0`…`10` |

Options: `{ "value": "two_days", "label": "1–2 дена", "help": "optional ≤ 200" }`, label ≤ 160.

Units (`unit` / `alt_units`): `celsius`; time `minutes`, `hours`, `days`, `weeks`, `months`,
`years`; `mmhg`, `mmol_l`, `bpm`, `kg`, `count`. `alt_units` must be from the same family
(time units only); the UI lets the visitor answer in any of them and always sends the value
converted to `unit` (e.g. `unit: "days"`, `alt_units: ["hours","weeks"]`; 36 hours → `"1.5"`).
`min`/`max` are hard bounds in `unit` (server-validated). With `allow_unknown`, route the
`unknown` answer explicitly and conservatively (the linter warns if no condition handles it).

`"optional": true` (any kind) lets the visitor skip the question; the stored answer is then
empty and every comparison on it is false.

### 5.2 `info`

```jsonc
"i_hydration": {
  "type": "info",
  "title": "Пијте доволно течности",   // ≤ 80
  "text": "...",                       // ≤ 600
  "next": "q_next"
}
```

Use sparingly (a short explanation before a sensitive question). Not a substitute for an outcome.

### 5.3 Routing — `next`

Either a target id (string), or an ordered list of branches; the **first** branch whose
`when` is true wins; the **last** branch must have no `when` (the default):

```jsonc
"next": [
  { "when": { "answer": "q_temp", "gte": 39.5 }, "goto": "o_same_day" },
  { "when": { "any": [ { "demo": "age_band", "eq": "older_65_plus" },
                       { "demo": "conditions", "includes": "immunosuppressed" } ] },
    "goto": "o_gp_soon" },
  { "goto": "q_cough" }
]
```

`goto` names a node or an outcome of this flow, or a global outcome (`global:emergency_now`,
`global:crisis`). The graph must be acyclic. Every path from `entry` must end in an outcome.

## 6. Body areas (entry by body map)

`head`, `eyes`, `ears`, `mouth` (teeth, gums, tongue), `throat` (incl. neck), `chest`,
`abdomen`, `pelvis` (bladder, genital, pregnancy-related), `back`, `arms` (incl. hands),
`legs` (incl. feet), `skin`, `general` (whole body: fever, tiredness, falls), `mind`
(mood, anxiety, sleep, confusion).

## 7. Conditions and scores

A condition is one JSON object:

| Form | Meaning |
|---|---|
| `{"all": [c, ...]}` / `{"any": [c, ...]}` / `{"not": c}` | Combinators (non-empty lists) |
| `{"answer": "<node id>", <op>: <value>}` | Compare this flow's answer |
| `{"demo": "<field>", <op>: <value>}` | Compare a demographic (§4) |
| `{"score": "<score id>", <op>: <number>}` | Compare a computed score |
| `{"answer": "<node id>", "answered": true\|false}` | Whether the question was answered (non-empty) |

Operators (exactly one per leaf): `eq`, `ne`, `in` (list), `includes` (set contains value),
`includes_any` (list), `includes_all` (list), `gte`, `gt`, `lte`, `lt` (numbers),
`between` (`[min, max]`, inclusive).
- Numeric operators compare numerically; `unknown`/empty answers make them **false**.
- A comparison on a question that was never asked (not on the path) is **false** — and
  therefore `{"not": ...}` of it is true. Prefer positive conditions.
- `includes*` work on `multi` answers and on `demo.conditions`; `eq`/`in` on single values.

Scores — sums of points over conditions, evaluated after every answer:

```jsonc
"scores": {
  "amber_count": {
    "label_en": "Number of amber features",
    "items": [
      { "when": { "answer": "q_fever", "eq": "yes" }, "points": 1 },
      { "when": { "answer": "q_duration", "gte": 7 }, "points": 1 }
    ]
  }
}
```

Use only simple counts or public-domain validated scores; cite them in `sources`.

## 8. Outcomes

```jsonc
"outcomes": {
  "o_pharmacy": {
    "level": "pharmacy_advice",
    "title": "Разговарајте со фармацевт",                     // ≤ 90, never a diagnosis
    "summary": "Вашите одговори упатуваат дека ...",           // ≤ 300
    "reasons": ["Болката трае помалку од една недела.", "..."],      // „Зошто“, ≥ 1, each ≤ 200
    "do_now": ["Пијте доволно течности.", "..."],                    // ≥ 1, each ≤ 200
    "watch_for": ["Ако не можете да голтате течности, веднаш ..."], // safety-net, ≥ 1 unless emergency_now
    "care": {
      "setting": "pharmacy",
      "specialties": [],               // licence-group keys, §8.2
      "facility_types": []             // clinic | hospital | laboratory
    },
    "sources": ["nhs-sore-throat"]     // optional per outcome
  }
}
```

### 8.1 Levels (most → least urgent)

| `level` | Public meaning | Required |
|---|---|---|
| `emergency_now` | Call 194 (or 112) now | `call` with 194 and 112 (§8.3); `do_now` = what to do while waiting |
| `urgent_same_day` | Today: emergency department / дежурна служба / итна амбуланта | `watch_for` |
| `see_doctor_24_48h` | See a doctor within 1–2 days | `watch_for` |
| `see_gp_this_week` | Appointment with your матичен лекар this week | `watch_for` |
| `pharmacy_advice` | Ask a pharmacist | `watch_for` |
| `self_care_with_safety_net` | Look after yourself at home, with clear „if … then …“ | `watch_for` |

With several symptoms the session's result is the most urgent of all flow outcomes;
an `emergency_now` outcome in any flow stops the remaining flows.
Every `watch_for` item is a safety-net sentence: „Ако … , веднаш …“.

`"crisis": true` (only with `emergency_now`) marks a mental-health crisis outcome: shown
with crisis wording; must still carry 194/112. Never route self-harm to any other level.

### 8.2 `care` — where to go (directory links)

- `setting` (required): `emergency_department`, `on_call` (дежурна служба / итна
  амбуланта), `gp` (матичен лекар), `specialist`, `gynecology`, `pediatrics`,
  `dentist`, `pharmacy`, `mental_health`, `self_care`.
- `specialties`: keys of the specialty groups in
  `database/seeders/data/licence_specialty_groups.php` (e.g. `opsta-medicina`,
  `semejna-medicina`, `pedijatrija`, `ginekologija`, `interna-medicina`, `kardiologija`,
  `pulmologija`, `gastroenterohepatologija`, `nevrologija`, `psihijatrija`, `dermatologija`,
  `oftalmologija`, `otorinolaringologija`, `ortopedija`, `traumatologija`, `urologija`,
  `infektologija`, `endokrinologija`, `urgentna-medicina`, `opsta-hirurgija`,
  `maksilofacijalna-hirurgija`, `fizikalna-medicina`, ...). The linter rejects unknown keys.
  The UI links to the doctors directory filtered by the specialty when our catalogue has it,
  otherwise to the unfiltered list; plus the visitor's city (chosen on the result screen,
  kept in the browser only).
- `facility_types`: `clinic`, `hospital`, `laboratory`. `emergency_department` links to
  facilities with an emergency service; `pharmacy` to the pharmacy directory.

### 8.3 `call` (emergency outcomes)

```jsonc
"call": [
  { "number": "194", "label": "Итна медицинска помош" },
  { "number": "112", "label": "Единствен европски број за итни случаи" }
]
```

Rendered as `tel:` links. Required on every `emergency_now` outcome and must include
`194` and `112`; crisis outcomes may add verified crisis lines after them.

## 9. Special populations

For every population that the flow's `audience` covers, the flow must either reference it
in at least one condition (red-flag `when`, routing or score), **or** explain in
`populations` why nothing differs (or where such visitors are sent instead):

| Key | Relevant when `audience.age_bands` contains | Counts as referenced by |
|---|---|---|
| `infant_0_3m` | `infant_0_3m` | `demo.age_band` with `infant_0_3m`, or `demo.age_months` |
| `infant_3_12m` | `infant_3_12m` | `demo.age_band` with `infant_3_12m`, or `demo.age_months` |
| `child` | any of `child_1_4`, `child_5_12`, `teen_13_17` | `demo.age_band` with a child band, `demo.age_years`/`age_months` |
| `pregnancy` | any of `teen_13_17`, `adult_18_64` | `demo.pregnancy` |
| `older_adult` | `older_65_plus` | `demo.age_band` with `older_65_plus`, or `demo.age_years` |
| `chronic` | any band | `demo.conditions` |

```jsonc
"populations": {
  "pregnancy": "Same thresholds apply in pregnancy for this symptom (NHS); pregnant visitors with fever are covered by the global red flags and the fever flow."
}
```

Notes are internal English. The linter fails a flow that neither references nor explains a
relevant population.

## 10. Sources (citations)

```jsonc
"sources": [
  { "id": "nhs-sore-throat", "title": "NHS — Sore throat",
    "url": "https://www.nhs.uk/conditions/sore-throat/", "accessed": "2026-10-07" }
]
```

`id` snake/kebab, unique per flow; global sources live in `global/*.json` and are referenced
by the same id. Public sources only (nhs.uk, nice.org.uk/cks.nice.org.uk, who.int,
cdc.gov, zdravstvo.gov.mk, iph.mk, fzo.org.mk …; others → linter warning). Every red flag
cites a source; every flow has ≥ 1 source. Do not copy proprietary protocols.

## 11. Global files (`global/*.json`)

```jsonc
{
  "schema": "zdravje.triage.global/1",
  "red_flags": [ /* as §3; codes unique across all global files; outcome must be global */ ],
  "outcomes": { /* as §8, ids unique across global files; referenced as "global:<id>" */ },
  "outcome_patches": {           // optional: append to an outcome defined in another global file
    "crisis": { "call": [ { "number": "...", "label": "..." } ], "do_now": ["..."] }
  },
  "sources": [ /* as §10 */ ]
}
```

`core.json` defines `emergency_now` and `crisis`.

## 12. What the visitor's browser receives

Only: flow key/title/body areas/search terms/audience (catalogue), the red-flag labels of the
screen, one node at a time (text, help, kind, options, unit bounds — never `next`, `when`,
scores or other rules), and the outcome texts. Answers are stored anonymously as option
codes / numbers per triage-safety.md and purged with the session (90 days).

## 13. Linter — `php artisan triage:lint [paths…]` (also run on import and before publish)

Errors (block import/publish): schema/shape violations; unknown ids in `goto`/conditions;
cycles; a path without an outcome; unreachable nodes or outcomes; red flags missing,
without source, or `when` using anything but `demo`; red-flag outcome not `emergency_now`;
`emergency_now` outcome without 194 and 112 in `call`; non-emergency outcome without
`watch_for`; `crisis` on a non-emergency outcome; a relevant population neither referenced
nor explained; unknown specialty key; missing sources; non-Macedonian Cyrillic letters;
a numeric comparator on a non-numeric question or an option value the question does not
offer.
Warnings: duplicate global red-flag label; `pregnant` without `unsure`; `allow_unknown`
without an `unknown` branch; source domain outside the public list; banned wording
(„дијагноза“ as a claim, „сигурно“, „дефинитивно“, „рецепт“, „доза“) in outcomes.

## 14. Complete example (illustrative only — not clinical content)

`database/data/triage/flows/example-sore-throat.json` would be:

```json
{
  "schema": "zdravje.triage/1",
  "key": "example-sore-throat",
  "title": "Болки во грлото (пример)",
  "summary_en": "Illustrative example of the schema. Adults and teenagers with a sore throat: red flags, duration, fever, then pharmacy / GP / same-day outcomes.",
  "audience": { "age_bands": ["teen_13_17", "adult_18_64", "older_65_plus"] },
  "urgency_rank": 30,
  "body_areas": ["throat"],
  "search_terms": ["грло", "болно грло", "голтање", "гуша"],
  "red_flags": [
    {
      "code": "cannot_swallow_saliva",
      "label": "Не можете да голтате плунка или ви тече плунка од устата",
      "source": "nhs-sore-throat"
    },
    {
      "code": "noisy_breathing",
      "label": "Свирежи или шумно дишење (стридор)",
      "help": "Висок звук при вдишување.",
      "source": "nhs-sore-throat"
    }
  ],
  "scores": {
    "amber_count": {
      "label_en": "Amber features",
      "items": [
        { "when": { "answer": "q_fever", "eq": "yes" }, "points": 1 },
        { "when": { "answer": "q_duration", "gte": 7 }, "points": 1 }
      ]
    }
  },
  "entry": "q_duration",
  "nodes": {
    "q_duration": {
      "type": "question",
      "kind": "number",
      "text": "Колку дена ве боли грлото?",
      "unit": "days",
      "alt_units": ["hours", "weeks"],
      "min": 0,
      "max": 365,
      "step": 0.5,
      "allow_unknown": true,
      "next": [
        { "when": { "answer": "q_duration", "eq": "unknown" }, "goto": "q_fever" },
        { "goto": "q_fever" }
      ]
    },
    "q_fever": {
      "type": "question",
      "kind": "yes_no",
      "allow_unsure": true,
      "text": "Дали имате температура (треска) 38 °C или повеќе?",
      "next": "q_pain"
    },
    "q_pain": {
      "type": "question",
      "kind": "scale",
      "text": "Колку е силна болката, од 0 (нема болка) до 10 (најсилна)?",
      "min_label": "Нема болка",
      "max_label": "Најсилна",
      "next": [
        { "when": { "answer": "q_pain", "gte": 9 }, "goto": "o_same_day" },
        { "when": { "any": [
            { "demo": "conditions", "includes": "immunosuppressed" },
            { "demo": "age_band", "eq": "older_65_plus" }
          ] }, "goto": "o_gp_soon" },
        { "when": { "score": "amber_count", "gte": 2 }, "goto": "o_gp_soon" },
        { "when": { "answer": "q_fever", "in": ["yes", "unsure"] }, "goto": "o_pharmacy" },
        { "goto": "o_self_care" }
      ]
    }
  },
  "outcomes": {
    "o_same_day": {
      "level": "urgent_same_day",
      "title": "Побарајте преглед денес",
      "summary": "Многу силна болка во грлото треба да се прегледа денес.",
      "reasons": ["Ја оценивте болката како многу силна."],
      "do_now": ["Јавете се во дежурна служба или појдете во итна амбуланта денес."],
      "watch_for": ["Ако почнете тешко да дишете или не можете да голтате, веднаш јавете се на 194."],
      "care": { "setting": "on_call", "specialties": ["otorinolaringologija"], "facility_types": ["hospital"] }
    },
    "o_gp_soon": {
      "level": "see_doctor_24_48h",
      "title": "Јавете се кај лекар во наредните 1–2 дена",
      "summary": "Според вашите одговори, добро е лекар да ве прегледа наскоро.",
      "reasons": ["Имате повеќе знаци што бараат преглед, или поголем ризик од компликации."],
      "do_now": ["Закажете преглед кај вашиот матичен лекар."],
      "watch_for": ["Ако болката нагло се влоши или не можете да голтате течности, веднаш побарајте итна помош."],
      "care": { "setting": "gp", "specialties": ["opsta-medicina", "semejna-medicina"], "facility_types": [] }
    },
    "o_pharmacy": {
      "level": "pharmacy_advice",
      "title": "Разговарајте со фармацевт",
      "summary": "Фармацевтот може да ве посоветува за олеснување на болката и температурата.",
      "reasons": ["Имате температура, но без знаци за итност."],
      "do_now": ["Пијте доволно течности и одморајте."],
      "watch_for": ["Ако по 7 дена нема подобрување, јавете се кај матичниот лекар."],
      "care": { "setting": "pharmacy", "specialties": [], "facility_types": [] }
    },
    "o_self_care": {
      "level": "self_care_with_safety_net",
      "title": "Грижете се за себе дома",
      "summary": "Болките во грлото често минуваат сами за околу една недела.",
      "reasons": ["Немате знаци што бараат преглед."],
      "do_now": ["Пијте топли или ладни течности.", "Одморајте."],
      "watch_for": ["Ако се појави температура над 38 °C или тешко голтање, јавете се кај лекар."],
      "care": { "setting": "self_care", "specialties": [], "facility_types": [] }
    }
  },
  "populations": {
    "pregnancy": "Example only: no pregnancy-specific threshold for this symptom; the global screen covers pregnancy red flags.",
    "child": "Example only: teenagers follow the adult path."
  },
  "sources": [
    {
      "id": "nhs-sore-throat",
      "title": "NHS — Sore throat",
      "url": "https://www.nhs.uk/conditions/sore-throat/",
      "accessed": "2026-10-07"
    }
  ]
}
```

(`chronic` and `older_adult` are referenced in `q_pain`'s routing, so they need no note.)
