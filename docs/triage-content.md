# Symptom guidance — clinical content (T-CONTENT)

Status: **every flow is a draft — „нацрт — чека лекарски преглед“.** The engine imports the files as draft versions; a flow becomes visible to the public only after a staff member records a clinician review in the admin panel (see [triage-flows.md](./triage-flows.md) and [triage-safety.md](./triage-safety.md)). The printable review form for the doctor is [triage-clinician-checklist.md](./triage-clinician-checklist.md).

Files: `apps/api/database/data/triage/flows/<key>.json` (schema `zdravje.triage/1`). `TriageContentFilesTest` checks them on their own; `php artisan triage:lint` runs the engine's full rules (all 40 files passed it with 0 errors and 0 warnings on 2026-10-07, against `feat/triage-engine-v2` @ 98f5cd9; `triage:import` on a scratch SQLite database loaded all 40 as v1 drafts and a second import reported every file unchanged).

## How the content was written

- **Public sources only**: NHS (nhs.uk condition and „when to get help“ pages), NICE public guidelines (NG143, CG84, NG232, NG249, CG103), WHO (pocket book of hospital care for children, IMCI chart booklet, fact sheets). Every source URL was checked to resolve on 2026-10-07 (HTTP 200, after redirects); the key thresholds were re-read on that date from: NHS urgent help for under-5s, fever in children, emergency contraception, pre-eclampsia, head injury, asthma attack, hypoglycaemia and chest pain.
- **No proprietary protocols** (NHS Pathways, Schmitt-Thompson, Manchester Triage System and similar) were used or consulted. Questions, options and outcome texts are original wording. Validated scores (Wells, Centor/FeverPAIN, Ottawa rules, NICE traffic-light table) are **not reproduced**; plain-language items approximate them conservatively and are marked as such.
- **Safety rules**: red flags come first and stop the questionnaire (194/112, or the global crisis outcome for self-harm); under-triage is avoided — when a rule is unsure it escalates; within a flow the most urgent matching rule wins (emergency rules are evaluated as soon as their answers exist). Every non-emergency outcome has „Ако … , веднаш …“ safety-netting. No diagnosis, no drug names or doses: pharmacy outcomes say „фармацевтот може да ве посоветува“.
- **Special populations**: infants (<3 months, 3–12 months), children, pregnancy/postpartum (`unsure` treated as pregnant), older adults (65+) and chronic conditions (`demo.conditions`) each either change routing in the flow or carry an internal note explaining why not (`populations` in each file).
- **Where to go**: `care.setting` + specialty keys from `licence_specialty_groups.php` (the linter's catalogue). Children's GP outcomes map to `pediatrics`; pregnancy to `gynecology`; dental to `dentist` (no physician specialty).
- **Language**: Macedonian, formal „вие“, medical terms explained in brackets, glossary of docs/mk-copy-review-2.md („избран лекар“, „установа“, „итна помош“). A native-speaker review of the wording is still needed.

## Mental-health crisis resources (verification)

- Checked on 2026-10-07: the Ministry of Health (zdravstvo.gov.mk) and the Institute of Public Health (iph.mk) search results list **no official suicide-prevention or mental-health crisis telephone line**. Web search was exhausted in this session, so a wider search was not possible.
- A third-party directory (findahelpline.com, not official) lists for North Macedonia: a University Clinic of Psychiatry line, the „Ало Бушавко“ child line, a domestic-violence SOS line (15 315) and others. **None of these were verified from an official source, so none is used in the content.**
- The crisis path therefore uses the engine's `global:crisis` outcome: **194 / 112 and the nearest emergency department**. If the owner verifies an official line, add it as `global/<name>.json` → `outcome_patches.crisis.call` (schema §11) after 194 and 112.

## Notes for the engine/integration

- `global/core.json` cites `https://www.nhs.uk/pregnancy/related-conditions/common-symptoms/vaginal-bleeding/`, which now redirects to `https://www.nhs.uk/pregnancy/common-symptoms/vaginal-bleeding/`.
- Specialty keys follow the licence groups (`opsta-medicina`, `gastroenterohepatologija`, `opsta-hirurgija`, `plasticna-hirurgija`), while the specialty catalogue created by the ФЗОМ import uses `opshta-medicina`, `gastroenterologija`, `opshta-hirurgija`, `plastichna-hirurgija`. **Bridged at integration (2026-10-07):** `OutcomePresenter` links a key to a staff link in `licence_specialty_mappings`, else the key itself, else the slug the ФЗОМ import gives the group's wordings (`SpecialtyGroups::catalogueSlugs`); `SpecialtyResolutionTest` checks every key used in a flow against the import's catalogue.
- No content seeder was added: the engine's `TriageFlowSeeder` already imports every file in `flows/`.

## Integration decisions (2026-10-07) — pending clinician review

The owner-approved principle „when unsure, escalate“ was applied to four
places where the draft was less urgent than the safest reading of the public
sources. Each is an integration decision, **not** a clinical sign-off: the
reviewing clinician confirms or reverses it (the checklist rows carry the same
note). `ConservativeClinicalDefaultsTest` pins them.

| Flow(s) | Before | Now | Why |
|---|---|---|---|
| `fever-infant-child` | <3 months, ≥38 °C → `urgent_same_day` („go now“) | `emergency_now` (194/112), outcome `o_emergency_infant_fever` — also when the temperature was **not measured** | NICE NG143 puts any infant under 3 months with ≥38 °C in the highest-risk group; a parent who reports fever without a reading is treated as fever. A measured temperature under 38 °C keeps the same-day outcome. |
| `crying-baby`, `ear-pain-child`, `rash-with-fever-child`, `vomiting-diarrhoea-child`, `child-breathing` | <3 months with fever (measured ≥38 °C in `crying-baby`, the „fever“ answer elsewhere) → `urgent_same_day` | the same `emergency_now` outcome | So the same baby gets the same answer whichever flow the parent opens. |
| `pregnancy-concerns` | reduced/changed fetal movements → `urgent_same_day` (go to the maternity unit) | `emergency_now`, outcome `o_emergency_movements` (194/112; go to the породилиште if the dispatcher says so; no home Doppler) | The flow author's own open question; NHS says to call immediately and not wait until the next day. MK has no maternity triage line, so 194 is the only immediate contact. |
| `vaginal-bleeding` | post-menopausal bleeding (or 65+) → `see_gp_this_week` | `see_doctor_24_48h` (gynaecologist in 1–2 days), outcome `o_doc48_gyn_1` | NHS treats it as an urgent referral; „this week“ could drift. |
| `breast-lump` | new lump, skin dimpling or nipple change → `see_gp_this_week` | `see_doctor_24_48h`, outcome `o_doc48_2` | Same reasoning (NHS urgent referral pathway). |

## Flows

| # | Key | Title | Group | Ages | Red flags | Outcome levels | Rank |
|---|-----|-------|-------|------|-----------|----------------|------|
| 1 | `mental-health` | Психичко здравје: вознемиреност, лошо расположение, криза | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 5 | urgent_same_day, see_doctor_24_48h, see_gp_this_week, self_care_with_safety_net | 96 |
| 2 | `chest-pain` | Болка во градите | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 7 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, pharmacy_advice, self_care_with_safety_net | 95 |
| 3 | `shortness-of-breath` | Отежнато дишење | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 7 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week | 94 |
| 4 | `allergic-reaction` | Алергиска реакција | Adults / all ages | infant_0_3m, infant_3_12m, child_1_4, child_5_12, teen_13_17, adult_18_64, older_65_plus | 4 | emergency_now, urgent_same_day, see_doctor_24_48h, pharmacy_advice, self_care_with_safety_net | 92 |
| 5 | `head-injury-adult` | Удар во главата кај возрасни | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 6 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, self_care_with_safety_net | 85 |
| 6 | `palpitations` | Чукање или прескокнување на срцето | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 3 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, self_care_with_safety_net | 82 |
| 7 | `headache` | Главоболка | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 8 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, pharmacy_advice | 80 |
| 8 | `dizziness-fainting` | Вртоглавица или несвестица | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 6 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, self_care_with_safety_net | 80 |
| 9 | `abdominal-pain` | Болка во стомакот | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 8 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, pharmacy_advice, self_care_with_safety_net | 78 |
| 10 | `burns` | Изгореници | Adults / all ages | infant_0_3m, infant_3_12m, child_1_4, child_5_12, teen_13_17, adult_18_64, older_65_plus | 4 | emergency_now, urgent_same_day, see_doctor_24_48h, self_care_with_safety_net | 76 |
| 11 | `wounds-bleeding` | Рани, посекотини и крварење | Adults / all ages | infant_0_3m, infant_3_12m, child_1_4, child_5_12, teen_13_17, adult_18_64, older_65_plus | 4 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, self_care_with_safety_net | 76 |
| 12 | `fever-adult` | Температура кај возрасни | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 7 | emergency_now, urgent_same_day, see_doctor_24_48h, self_care_with_safety_net | 72 |
| 13 | `eye-problems` | Проблеми со очите (црвено око, промена на видот) | Adults / all ages | infant_0_3m, infant_3_12m, child_1_4, child_5_12, teen_13_17, adult_18_64, older_65_plus | 5 | emergency_now, urgent_same_day, see_doctor_24_48h, pharmacy_advice, self_care_with_safety_net | 70 |
| 14 | `high-blood-pressure` | Висок крвен притисок (измерена вредност) | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 4 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, self_care_with_safety_net | 68 |
| 15 | `diarrhoea-vomiting-adult` | Пролив или повраќање кај возрасни | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 5 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, pharmacy_advice, self_care_with_safety_net | 60 |
| 16 | `limb-injury` | Повреда на рака или нога | Adults / all ages | infant_0_3m, infant_3_12m, child_1_4, child_5_12, teen_13_17, adult_18_64, older_65_plus | 5 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, self_care_with_safety_net | 58 |
| 17 | `back-pain` | Болка во грбот | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 6 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, self_care_with_safety_net | 55 |
| 18 | `urinary-symptoms` | Тегоби при мокрење | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 3 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, pharmacy_advice | 55 |
| 19 | `rash-skin` | Осип или промени на кожата | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 3 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, pharmacy_advice | 50 |
| 20 | `cough-cold-flu` | Кашлица, настинка или грип | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 5 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, pharmacy_advice, self_care_with_safety_net | 45 |
| 21 | `sore-throat` | Болка во грлото | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 3 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, pharmacy_advice | 40 |
| 22 | `ear-pain-adult` | Болка во увото | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 3 | emergency_now, urgent_same_day, see_doctor_24_48h, pharmacy_advice, self_care_with_safety_net | 35 |
| 23 | `toothache` | Забоболка и проблеми со забите | Adults / all ages | infant_0_3m, infant_3_12m, child_1_4, child_5_12, teen_13_17, adult_18_64, older_65_plus | 2 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, pharmacy_advice | 35 |
| 24 | `sleep-problems` | Проблеми со спиењето | Adults / all ages | teen_13_17, adult_18_64, older_65_plus | 1 | see_doctor_24_48h, see_gp_this_week, self_care_with_safety_net | 20 |
| 25 | `pregnancy-concerns` | Загриженост во бременост или по породување | Women's health | teen_13_17, adult_18_64 | 7 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week | 87 |
| 26 | `vaginal-bleeding` | Вагинално крварење | Women's health | teen_13_17, adult_18_64, older_65_plus | 3 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week | 80 |
| 27 | `emergency-contraception` | Итна контрацепција (по незаштитен однос) | Women's health | teen_13_17, adult_18_64 | 1 | emergency_now, urgent_same_day, see_gp_this_week, pharmacy_advice | 50 |
| 28 | `breast-lump` | Грутка или промени во дојката | Women's health | teen_13_17, adult_18_64, older_65_plus | 1 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, self_care_with_safety_net | 40 |
| 29 | `child-breathing` | Отежнато дишење кај дете | Children | infant_0_3m, infant_3_12m, child_1_4, child_5_12 | 6 | emergency_now, urgent_same_day, see_doctor_24_48h, self_care_with_safety_net | 94 |
| 30 | `rash-with-fever-child` | Осип кај дете (со или без температура) | Children | infant_0_3m, infant_3_12m, child_1_4, child_5_12 | 8 | emergency_now, urgent_same_day, see_doctor_24_48h, self_care_with_safety_net | 93 |
| 31 | `fever-infant-child` | Температура кај бебиња и деца | Children | infant_0_3m, infant_3_12m, child_1_4, child_5_12 | 9 | emergency_now, urgent_same_day, see_doctor_24_48h, self_care_with_safety_net | 88 |
| 32 | `head-injury-child` | Удар во главата кај дете | Children | infant_0_3m, infant_3_12m, child_1_4, child_5_12 | 7 | emergency_now, urgent_same_day, see_doctor_24_48h, self_care_with_safety_net | 86 |
| 33 | `abdominal-pain-child` | Болка во стомакот кај дете | Children | infant_0_3m, infant_3_12m, child_1_4, child_5_12 | 6 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, pharmacy_advice, self_care_with_safety_net | 80 |
| 34 | `crying-baby` | Бебе што многу плаче | Children | infant_0_3m, infant_3_12m | 9 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, self_care_with_safety_net | 80 |
| 35 | `vomiting-diarrhoea-child` | Повраќање или пролив кај дете | Children | infant_0_3m, infant_3_12m, child_1_4, child_5_12 | 7 | emergency_now, urgent_same_day, see_doctor_24_48h, self_care_with_safety_net | 74 |
| 36 | `ear-pain-child` | Болка во увото кај дете | Children | infant_0_3m, infant_3_12m, child_1_4, child_5_12 | 3 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, self_care_with_safety_net | 38 |
| 37 | `confusion-older` | Ненадејна збунетост кај постаро лице | Older adults | teen_13_17, adult_18_64, older_65_plus | 6 | emergency_now, urgent_same_day, see_gp_this_week | 88 |
| 38 | `falls-older` | Пад кај постаро лице | Older adults | adult_18_64, older_65_plus | 6 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, self_care_with_safety_net | 78 |
| 39 | `asthma-attack` | Напад на астма | Chronic conditions | child_1_4, child_5_12, teen_13_17, adult_18_64, older_65_plus | 4 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week | 92 |
| 40 | `diabetes-blood-sugar` | Дијабетес: низок или висок шеќер | Chronic conditions | infant_3_12m, child_1_4, child_5_12, teen_13_17, adult_18_64, older_65_plus | 3 | emergency_now, urgent_same_day, see_doctor_24_48h, see_gp_this_week, self_care_with_safety_net | 86 |

Total: 40 flows, 200 flow-specific red flags (plus the engine's global screen).

### Психичко здравје: вознемиреност, лошо расположение, криза (`mental-health`)

Mental health (teens and adults). Crisis red flags (suicidal plan/intent, self-harm now, risk to others, severe confusion/psychosis with danger) → global crisis outcome (194/112 + nearest ED). Frequent thoughts of death, psychotic or manic features, inability to function, teens or postpartum with any thoughts → same day; occasional thoughts without plan → 24–48h; low mood/anxiety >2 weeks → GP; mild → self-care with safety net (194/112 always repeated).

**Red flags (asked first):**

- Имате план или намера да си одземете живот, или сте подготвиле средства за тоа → crisis — source: `nhs-suicidal`
- Сте се повредиле или сте зеле прекумерно лекови/супстанции → crisis — source: `nhs-suicidal`
- Чувствувате дека можете да повредите некого друг → crisis — source: `nhs-mh-urgent`
- Не можете да се чувствувате безбедни сега → crisis — source: `nhs-suicidal`
- Слушате гласови што ви наредуваат да направите нешто опасно, или сте многу збунети и не знаете каде сте → crisis — source: `nhs-psychosis`

**Sources** (accessed 2026-10-07):

- `nhs-suicidal` — [NHS (Велика Британија) — Help for suicidal thoughts](https://www.nhs.uk/mental-health/feelings-symptoms-behaviours/behaviours/help-for-suicidal-thoughts/)
- `nhs-mh-urgent` — [NHS (Велика Британија) — Where to get urgent help for mental health](https://www.nhs.uk/nhs-services/mental-health-services/where-to-get-urgent-help-for-mental-health/)
- `who-suicide` — [Светска здравствена организација (WHO) — Suicide — fact sheet](https://www.who.int/news-room/fact-sheets/detail/suicide)
- `nhs-gad` — [NHS (Велика Британија) — Generalised anxiety disorder in adults](https://www.nhs.uk/mental-health/conditions/generalised-anxiety-disorder-gad/)
- `nhs-panic` — [NHS (Велика Британија) — Panic disorder](https://www.nhs.uk/mental-health/conditions/panic-disorder/)
- `nhs-depression` — [NHS (Велика Британија) — Depression in adults](https://www.nhs.uk/mental-health/conditions/depression-in-adults/)
- `nhs-psychosis` — [NHS (Велика Британија) — Psychosis](https://www.nhs.uk/mental-health/conditions/psychosis/overview/)
- `nhs-postnatal-depression` — [NHS (Велика Британија) — Postnatal depression](https://www.nhs.uk/mental-health/conditions/postnatal-depression/)

**Rationale:** NHS suicidal thoughts / urgent mental-health help / depression / anxiety / psychosis; WHO suicide fact sheet. Any self-harm or suicidal intent routes to global:crisis and never to self-care. Passive ideation without plan → 24–48h; frequent → same day; teens and postpartum have lower thresholds.

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.
- pregnancy: Referenced: pregnancy/postpartum low mood/anxiety → GP minimum; postpartum unusual experiences/elation/thoughts → same day (postpartum psychosis risk).
- older_adult: Same thresholds; new confusion in older adults is in confusion-older.
- chronic: No condition-specific threshold.

**Where to go:** settings `gp`, `mental_health`, `self_care`; specialties `opsta-medicina`, `psihijatrija`, `semejna-medicina`.

**Open questions for the clinician:**

- MK crisis line: no official helpline number could be verified on zdravstvo.gov.mk or iph.mk (2026-10-07). Crisis outcome uses 194/112 + nearest emergency/psychiatric service. Please verify any line before adding it.
- Is 'passive thoughts of death sometimes' → 24–48h acceptable, or should any thoughts → same day?
- Should the flow be offered to 13–17 year olds directly, or only via a parent?

### Болка во градите (`chest-pain`)

Adult chest pain. Any ongoing pressure-type pain, radiation, autonomic symptoms or breathlessness → 194. Pleuritic pain with DVT risk/leg swelling → emergency (possible PE). Otherwise short-lived/musculoskeletal pain → GP; reflux-type → pharmacy with safety net. Over-65, diabetes, known heart disease lower the threshold.

**Red flags (asked first):**

- Болка, притисок, стегање или печење во градите што трае подолго од неколку минути или доаѓа и си оди при мирување → 194/112 — source: `nhs-chest-pain`
- Болката се шири кон раката, вратот, вилицата, грбот или стомакот → 194/112 — source: `nhs-chest-pain`
- Болката е придружена со потење, гадење, вртоглавица или чувство на несвестица → 194/112 — source: `nhs-chest-pain`
- Ненадејно отежнато дишење или недостиг на воздух → 194/112 — source: `nhs-chest-pain`
- Искашлување крв → 194/112 — source: `nhs-pe`
- Несвестица, колапс или многу бледа, сива или студена и влажна кожа → 194/112 — source: `nhs-heart-attack`
- Ненадејна, многу силна болка што се чувствува како кинење и се шири кон грбот → 194/112 — source: `nhs-chest-pain`

**Sources** (accessed 2026-10-07):

- `nhs-chest-pain` — [NHS (Велика Британија) — Chest pain](https://www.nhs.uk/symptoms/chest-pain/)
- `nhs-heart-attack` — [NHS (Велика Британија) — Heart attack](https://www.nhs.uk/conditions/heart-attack/)
- `nhs-pe` — [NHS (Велика Британија) — Pulmonary embolism](https://www.nhs.uk/conditions/pulmonary-embolism/)
- `nhs-dvt` — [NHS (Велика Британија) — Deep vein thrombosis (DVT)](https://www.nhs.uk/conditions/deep-vein-thrombosis-dvt/)
- `nhs-shingles` — [NHS (Велика Британија) — Shingles](https://www.nhs.uk/conditions/shingles/)

**Rationale:** Any pressure/radiating/autonomic chest pain is treated as possible ACS (NHS 999 criteria). Pleuritic pain + Wells-type risk items (not a score) escalates for possible PE. Diabetics, older adults and known heart disease get a lower threshold because atypical presentations are common.

**Assumptions:**

- Users with ongoing pain of unclear character are better over-triaged to same-day care.
- The DVT-risk list is a plain-language checklist, not the Wells score (scores are not reproduced).

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.
- older_adult: Referenced: ≥65 with pain now → same day.
- chronic: Referenced via demo.conditions heart_disease/diabetes.

**Where to go:** settings `emergency_department`, `gp`, `on_call`, `pharmacy`, `self_care`; specialties `interna-medicina`, `kardiologija`, `opsta-medicina`, `semejna-medicina`, `urgentna-medicina`.

**Open questions for the clinician:**

- Should 'ongoing pain now' in any adult ≥40 without risk factors go to urgent_same_day rather than the default?
- Is 'cocaine/stimulant use + pain now' acceptable as emergency_now?
- Should women ≤6 weeks postpartum with pleuritic pain be red-flagged regardless of other answers? (currently yes via dvt_risk)

### Отежнато дишење (`shortness-of-breath`)

Adult breathlessness. Severe/sudden breathlessness, cyanosis, inability to speak, chest pain, collapse → 194. Gradual breathlessness with fever (possible pneumonia) or worsening known COPD/heart failure → same day. Chronic slow change → GP.

**Red flags (asked first):**

- Толку тешко дишете што не можете да кажете цела реченица → 194/112 — source: `nhs-sob`
- Посинети, сиви или многу бледи усни, јазик или кожа → 194/112 — source: `nhs-sob`
- Недостигот на воздух се појави нагло и е силен → 194/112 — source: `nhs-sob`
- Болка или притисок во градите → 194/112 — source: `nhs-sob`
- Збунетост, поспаност или тешко будење → 194/112 — source: `nhs-sob`
- Отекување на лицето, усните, јазикот или грлото → 194/112 — source: `nhs-anaphylaxis`
- Искашлување поголемо количество крв → 194/112 — source: `nhs-pe`

**Sources** (accessed 2026-10-07):

- `nhs-sob` — [NHS (Велика Британија) — Shortness of breath](https://www.nhs.uk/symptoms/shortness-of-breath/)
- `nhs-pe` — [NHS (Велика Британија) — Pulmonary embolism](https://www.nhs.uk/conditions/pulmonary-embolism/)
- `nhs-pneumonia` — [NHS (Велика Британија) — Pneumonia](https://www.nhs.uk/conditions/pneumonia/)
- `nhs-copd` — [NHS (Велика Британија) — Chronic obstructive pulmonary disease (COPD)](https://www.nhs.uk/conditions/chronic-obstructive-pulmonary-disease-copd/)
- `nhs-heart-failure` — [NHS (Велика Британија) — Heart failure](https://www.nhs.uk/conditions/heart-failure/)
- `nhs-asthma` — [NHS (Велика Британија) — Asthma (incl. asthma attacks)](https://www.nhs.uk/conditions/asthma/)
- `nhs-anaphylaxis` — [NHS (Велика Британија) — Anaphylaxis](https://www.nhs.uk/conditions/anaphylaxis/)
- `nhs-chest-pain` — [NHS (Велика Британија) — Chest pain](https://www.nhs.uk/symptoms/chest-pain/)
- `nhs-sepsis` — [NHS (Велика Британија) — Sepsis](https://www.nhs.uk/conditions/sepsis/)

**Rationale:** NHS breathlessness 999 criteria (unable to speak, blue lips, chest pain, confusion). SpO2 thresholds (≤91 emergency, ≤94 urgent) are conservative public-health thresholds used widely in COVID-19 home-monitoring advice; COPD patients may run lower, so the 94 rule is skipped for COPD.

**Assumptions:**

- Pulse oximeter values are optional and only escalate, never de-escalate.

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.

**Where to go:** settings `emergency_department`, `gp`, `on_call`; specialties `interna-medicina`, `kardiologija`, `opsta-medicina`, `pulmologija`, `semejna-medicina`, `urgentna-medicina`.

**Open questions for the clinician:**

- Confirm SpO2 thresholds (≤91% emergency, ≤94% same-day) for a lay audience.
- Should any new breathlessness in pregnancy be urgent_same_day by default?

### Алергиска реакција (`allergic-reaction`)

Allergic reaction (all ages). Airway/breathing/circulation signs or known anaphylaxis trigger with rapid symptoms → 194 (use own adrenaline auto-injector as prescribed). Lip/face swelling without breathing problem → same day. Mild hives/itch → pharmacy.

**Red flags (asked first):**

- Отекување на јазикот или грлото, засипнат глас, тешко голтање → 194/112 — source: `nhs-anaphylaxis`
- Отежнато или свирежно дишење, упорна кашлица → 194/112 — source: `nhs-anaphylaxis`
- Вртоглавица, несвестица, бледа и влажна кожа или збунетост → 194/112 — source: `nhs-anaphylaxis`
- Претходно сте имале анафилакса (тешка алергиска реакција) и сега имате симптоми по истиот предизвикувач → 194/112 — source: `nhs-anaphylaxis`

**Sources** (accessed 2026-10-07):

- `nhs-anaphylaxis` — [NHS (Велика Британија) — Anaphylaxis](https://www.nhs.uk/conditions/anaphylaxis/)
- `nhs-allergies` — [NHS (Велика Британија) — Allergies](https://www.nhs.uk/conditions/allergies/)
- `nhs-angioedema` — [NHS (Велика Британија) — Angioedema](https://www.nhs.uk/conditions/angioedema/)
- `nhs-hives` — [NHS (Велика Британија) — Hives](https://www.nhs.uk/conditions/hives/)

**Rationale:** NHS anaphylaxis ABC signs; prior anaphylaxis + symptoms → emergency. Use of own prescribed adrenaline auto-injector follows the user's prescription (not a dose instruction).

**Populations not referenced in routing (internal notes):**

- infant_0_3m: Referenced via child-band rule (local reactions in young children → paediatrician). Breathing/circulation red flags apply to all ages.
- infant_3_12m: Referenced via child-band rule.
- child: Referenced via child-band rule; red flags identical for children (NHS).
- pregnancy: Anaphylaxis management is the same in pregnancy (call 194); no separate threshold.
- older_adult: Same thresholds.
- chronic: Asthma increases anaphylaxis risk; breathing symptoms are red flags at any risk level.

**Where to go:** settings `emergency_department`, `on_call`, `pediatrics`, `pharmacy`, `self_care`; specialties `dermatologija`, `imunologija`, `opsta-medicina`, `pedijatrija`, `semejna-medicina`, `urgentna-medicina`.

**Open questions for the clinician:**

- Is it acceptable to instruct 'use your own prescribed adrenaline auto-injector' in a non-diagnostic tool? (Public NHS guidance does.)

### Удар во главата кај возрасни (`head-injury-adult`)

Adult head injury. NHS 999 criteria (unconsciousness, seizure, fall >1 m/5 stairs, CSF leak, focal deficit, high-speed) → 194. Vomiting, anticoagulants, alcohol, ≥65, amnesia → same day ED. Mild with no features → self-care with 48h observation advice.

**Red flags (asked first):**

- Беше без свест и сè уште не се освестил, или не може да остане буден → 194/112 — source: `nhs-head-injury`
- Имаше напад (грч) по ударот → 194/112 — source: `nhs-head-injury`
- Пад од повеќе од 1 метар или 5 скали, или удар при голема брзина (сообраќајка) → 194/112 — source: `nhs-head-injury`
- Бистра течност или крв од ушите или носот, модринка зад ушите → 194/112 — source: `nhs-head-injury`
- Ново трпнење или слабост, проблеми со одењето, говорот, разбирањето или видот → 194/112 — source: `nhs-head-injury`
- Рана со нешто внатре или вдлабнување на главата → 194/112 — source: `nhs-head-injury`

**Sources** (accessed 2026-10-07):

- `nhs-head-injury` — [NHS (Велика Британија) — Head injury and concussion](https://www.nhs.uk/conditions/head-injury-and-concussion/)
- `nice-ng232` — [NICE (Велика Британија) — NG232 Head injury: assessment and early management](https://www.nice.org.uk/guidance/ng232)

**Rationale:** NHS head injury 999/111/GP criteria (fetched 2026-10-07); NICE NG232 risk factors (anticoagulation, age ≥65) for CT within hours — expressed only as 'same-day ED'.

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.
- pregnancy: No pregnancy-specific threshold in NHS head injury guidance; falls in pregnancy with abdominal pain/bleeding are in pregnancy-concerns.
- chronic: Anticoagulation/bleeding disorders are asked directly (q_risk).

**Where to go:** settings `emergency_department`, `gp`, `self_care`; specialties `nevrohirurgija`, `nevrologija`, `opsta-medicina`, `semejna-medicina`, `traumatologija`, `urgentna-medicina`.

### Чукање или прескокнување на срцето (`palpitations`)

Adult palpitations. With chest pain, fainting, breathlessness → emergency. Ongoing fast palpitations now, known heart disease → same day. Brief, occasional, triggered by caffeine/stress → self-care/GP.

**Red flags (asked first):**

- Болка или притисок во градите → 194/112 — source: `nhs-palpitations`
- Несвестица или речиси несвестица → 194/112 — source: `nhs-palpitations`
- Силен недостиг на воздух → 194/112 — source: `nhs-palpitations`

**Sources** (accessed 2026-10-07):

- `nhs-palpitations` — [NHS (Велика Британија) — Heart palpitations](https://www.nhs.uk/symptoms/heart-palpitations/)
- `nhs-af` — [NHS (Велика Британија) — Atrial fibrillation](https://www.nhs.uk/conditions/atrial-fibrillation/)
- `nhs-fainting` — [NHS (Велика Британија) — Fainting](https://www.nhs.uk/symptoms/fainting/)

**Rationale:** NHS heart palpitations. Pulse thresholds (≥150 or ≤40 emergency; ≥120 same day) are conservative lay thresholds — clinician to confirm.

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.
- chronic: Referenced via demo.conditions heart_disease.

**Where to go:** settings `emergency_department`, `gp`, `on_call`, `self_care`; specialties `kardiologija`, `opsta-medicina`, `semejna-medicina`, `urgentna-medicina`.

**Open questions for the clinician:**

- Confirm pulse thresholds for lay self-measurement.

### Главоболка (`headache`)

Adult headache. Thunderclap, stroke signs, meningism, post-head-injury, visual loss, pregnancy/postpartum + pre-eclampsia features → emergency. New headache over 50, immunosuppression, progressive or worse on waking with vomiting → same day/48h. Typical recurrent headaches → pharmacy/self-care.

**Red flags (asked first):**

- Ненадејна, многу силна главоболка — „најсилната досега“, што достигнува врв за неколку секунди или минути → 194/112 — source: `nhs-sah`
- Опуштено лице, слабост или трпнење на рака или нога, тешкотии со говорот → 194/112 — source: `nhs-stroke`
- Температура со вкочанет врат, осетливост на светлина или осип што не бледнее при притисок → 194/112 — source: `nhs-meningitis`
- Главоболката започна по удар во главата → 194/112 — source: `nhs-headaches`
- Збунетост, тешко будење или напад (грч) → 194/112 — source: `nhs-headaches`
- Ненадејно губење на видот, двојно гледање или многу црвено и болно око → 194/112 — source: `nhs-headaches`
- Бременост или породување во последните 6 недели, со силна главоболка, нарушен вид, оток на лицето или рацете, или болка под ребрата → 194/112 — only when `{"demo": "pregnancy", "in": ["pregnant", "postpartum", "unsure"]}` — source: `nhs-pre-eclampsia`
- И други во домот (или миленичињата) имаат главоболка или им е лошо, особено покрај печка или бојлер на гас/дрва → 194/112 — source: `nhs-co`

**Sources** (accessed 2026-10-07):

- `nhs-headaches` — [NHS (Велика Британија) — Headaches](https://www.nhs.uk/symptoms/headaches/)
- `nhs-migraine` — [NHS (Велика Британија) — Migraine](https://www.nhs.uk/conditions/migraine/)
- `nhs-sah` — [NHS (Велика Британија) — Subarachnoid haemorrhage](https://www.nhs.uk/conditions/subarachnoid-haemorrhage/)
- `nhs-stroke` — [NHS (Велика Британија) — Stroke — symptoms](https://www.nhs.uk/conditions/stroke/symptoms/)
- `nhs-meningitis` — [NHS (Велика Британија) — Meningitis](https://www.nhs.uk/conditions/meningitis/)
- `nhs-pre-eclampsia` — [NHS (Велика Британија) — Pre-eclampsia](https://www.nhs.uk/conditions/pre-eclampsia/)
- `nhs-co` — [NHS (Велика Британија) — Carbon monoxide poisoning](https://www.nhs.uk/conditions/carbon-monoxide-poisoning/)
- `nhs-glaucoma` — [NHS (Велика Британија) — Glaucoma](https://www.nhs.uk/conditions/glaucoma/)
- `nhs-head-injury` — [NHS (Велика Британија) — Head injury and concussion](https://www.nhs.uk/conditions/head-injury-and-concussion/)
- `nhs-encephalitis` — [NHS (Велика Британија) — Encephalitis](https://www.nhs.uk/conditions/encephalitis/)

**Rationale:** Red flags follow NHS headache/SAH/stroke/meningitis/pre-eclampsia pages; jaw claudication/scalp tenderness (giant cell arteritis) escalated same-day because of sight risk (public NHS headache guidance lists this).

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.

**Where to go:** settings `emergency_department`, `gp`, `on_call`, `pharmacy`; specialties `nevrologija`, `oftalmologija`, `opsta-medicina`, `semejna-medicina`, `urgentna-medicina`.

**Open questions for the clinician:**

- Age cut-off for 'new headache' escalation: >50 is common in public guidance; we use the 65+ band from demographics plus the jaw/scalp item labelled 'над 50'. Is that acceptable?
- Should headache in pregnancy without other features go to see_doctor_24_48h or same day?

### Вртоглавица или несвестица (`dizziness-fainting`)

Adult dizziness/fainting. Stroke signs, chest pain/palpitations with faint, faint during exertion, not fully recovered → emergency. First faint >65, faint with known heart disease, pregnancy → same day. Vertigo with hearing loss → 48h. Simple faint with clear trigger → self-care.

**Red flags (asked first):**

- Опуштено лице, слабост или трпнење во рака или нога, нејасен говор, нарушен вид или губење рамнотежа што не поминува → 194/112 — source: `nhs-stroke`
- Болка во градите, силно чукање на срцето или недостиг на воздух → 194/112 — source: `nhs-fainting`
- Несвестица за време на физички напор → 194/112 — source: `nhs-fainting`
- Не се освести брзо или е сè уште збунет → 194/112 — source: `nhs-fainting`
- Удри со главата при паѓањето и зема лекови за разредување на крвта → 194/112 — source: `nhs-head-injury`
- Грчеви, гризење на јазикот или неконтролирано мокрење при несвестицата (ако е прв пат) → 194/112 — source: `nhs-fainting`

**Sources** (accessed 2026-10-07):

- `nhs-dizziness` — [NHS (Велика Британија) — Dizziness](https://www.nhs.uk/symptoms/dizziness/)
- `nhs-fainting` — [NHS (Велика Британија) — Fainting](https://www.nhs.uk/symptoms/fainting/)
- `nhs-vertigo` — [NHS (Велика Британија) — Vertigo](https://www.nhs.uk/conditions/vertigo/)
- `nhs-stroke` — [NHS (Велика Британија) — Stroke — symptoms](https://www.nhs.uk/conditions/stroke/symptoms/)
- `nhs-tia` — [NHS (Велика Британија) — Transient ischaemic attack (TIA)](https://www.nhs.uk/conditions/transient-ischaemic-attack-tia/)
- `nhs-palpitations` — [NHS (Велика Британија) — Heart palpitations](https://www.nhs.uk/symptoms/heart-palpitations/)
- `nhs-head-injury` — [NHS (Велика Британија) — Head injury and concussion](https://www.nhs.uk/conditions/head-injury-and-concussion/)

**Rationale:** NHS fainting/dizziness/vertigo/stroke; exertional syncope and family history of sudden death as high-risk features.

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.

**Where to go:** settings `emergency_department`, `gp`, `on_call`, `self_care`; specialties `interna-medicina`, `kardiologija`, `nevrologija`, `opsta-medicina`, `otorinolaringologija`, `semejna-medicina`, `urgentna-medicina`.

### Болка во стомакот (`abdominal-pain`)

Adult abdominal pain. Severe/sudden pain, rigid abdomen, vomiting blood, black stool, collapse, pregnancy-possible with one-sided pain/bleeding (ectopic) → emergency. Right lower quadrant migrating pain, persistent vomiting, fever with pain, kidney-stone type pain → same day. Mild indigestion → pharmacy.

**Red flags (asked first):**

- Ненадејна, многу силна болка во стомакот → 194/112 — source: `nhs-stomach-ache`
- Стомакот е тврд и многу болен на допир, или болката е толку силна што не можете да се движите → 194/112 — source: `nhs-stomach-ache`
- Повраќате крв или нешто што личи на талог од кафе → 194/112 — source: `nhs-stomach-ache`
- Црна, катранеста столица или многу крв во столицата → 194/112 — source: `nhs-stomach-ache`
- Несвестица, колапс, бледа и ладна, влажна кожа → 194/112 — source: `nhs-stomach-ache`
- Болката е во горниот дел и се шири кон градите, раката или вилицата → 194/112 — source: `nhs-heart-attack`
- Можна бременост (задоцнет циклус) со болка на едната страна долу, крварење или болка во врвот на рамото → 194/112 — only when `{"demo": "sex", "in": ["female", "unspecified"]}` — source: `nhs-ectopic`
- Ненадејна болка во тестисот (кај мажи) → 194/112 — only when `{"demo": "sex", "eq": "male"}` — source: `nhs-testicle-pain`

**Sources** (accessed 2026-10-07):

- `nhs-stomach-ache` — [NHS (Велика Британија) — Stomach ache](https://www.nhs.uk/symptoms/stomach-ache/)
- `nhs-appendicitis` — [NHS (Велика Британија) — Appendicitis](https://www.nhs.uk/conditions/appendicitis/)
- `nhs-ectopic` — [NHS (Велика Британија) — Ectopic pregnancy](https://www.nhs.uk/conditions/ectopic-pregnancy/)
- `nhs-gallstones` — [NHS (Велика Британија) — Gallstones](https://www.nhs.uk/conditions/gallstones/)
- `nhs-pancreatitis` — [NHS (Велика Британија) — Acute pancreatitis](https://www.nhs.uk/conditions/acute-pancreatitis/)
- `nhs-kidney-stones` — [NHS (Велика Британија) — Kidney stones](https://www.nhs.uk/conditions/kidney-stones/)
- `nhs-sepsis` — [NHS (Велика Британија) — Sepsis](https://www.nhs.uk/conditions/sepsis/)
- `nhs-heart-attack` — [NHS (Велика Британија) — Heart attack](https://www.nhs.uk/conditions/heart-attack/)
- `nhs-testicle-pain` — [NHS (Велика Британија) — Testicle pain](https://www.nhs.uk/symptoms/testicle-pain/)
- `nhs-preg-stomach-pain` — [NHS (Велика Британија) — Stomach pain in pregnancy](https://www.nhs.uk/pregnancy/common-symptoms/stomach-pain/)

**Rationale:** NHS stomach ache 999/A&E criteria; appendicitis migrating pain; ectopic pregnancy (shoulder tip pain, bleeding); testicular torsion for males; bowel obstruction features; older adults have lower threshold.

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.

**Where to go:** settings `emergency_department`, `gp`, `gynecology`, `on_call`, `pharmacy`, `self_care`; specialties `gastroenterohepatologija`, `ginekologija`, `opsta-hirurgija`, `opsta-medicina`, `semejna-medicina`, `urgentna-medicina`, `urologija`.

**Open questions for the clinician:**

- Should every possible-pregnancy + abdominal pain (without one-sided pain or bleeding) be same-day?
- Pain score cut-offs (≥8 same day; ≥7 for 65+) need clinician confirmation.

### Изгореници (`burns`)

Burns and scalds (all ages). Airway/smoke inhalation, electrical/chemical, large burns, full-thickness, face/hands/genitals, infants → emergency/same day. Small superficial → self-care with cooling first aid.

**Red flags (asked first):**

- Изгореници на лицето или устата, засипнат глас, кашлање чад или отежнато дишење → 194/112 — source: `nhs-burns`
- Изгореницата е голема (поголема од дланката на повредениот) или е длабока (бела, кафеава или црна, без болка) → 194/112 — source: `nhs-burns`
- Изгореница од струја или од хемикалија → 194/112 — source: `nhs-burns`
- Повредениот е збунет, поспан или има несвестица → 194/112 — source: `nhs-burns`

**Sources** (accessed 2026-10-07):

- `nhs-burns` — [NHS (Велика Британија) — Burns and scalds](https://www.nhs.uk/conditions/burns-and-scalds/)

**Rationale:** NHS burns and scalds: A&E for large/deep/chemical/electrical/face/hands/feet/genitals/joints, and for children under 5 and older people with blistered burns; 20 min cool running water.

**Populations not referenced in routing (internal notes):**

- infant_0_3m: Referenced via under-5 rule.
- infant_3_12m: Referenced via under-5 rule.
- child: Referenced via under-5 rule; 5–17 follow adult thresholds.
- older_adult: Referenced (slower healing → 24–48h).

**Where to go:** settings `emergency_department`, `gp`, `self_care`; specialties `opsta-hirurgija`, `opsta-medicina`, `plasticna-hirurgija`, `semejna-medicina`, `urgentna-medicina`.

### Рани, посекотини и крварење (`wounds-bleeding`)

Cuts, wounds, bites and nosebleeds (all ages). Uncontrolled bleeding, spurting, deep wounds to chest/abdomen/neck, amputation → 194. Gaping wounds, embedded objects, animal/human bites, numbness, poor tetanus status → same day. Small clean cuts → self-care.

**Red flags (asked first):**

- Крварење што не престанува по 10 минути силен притисок, или крвта шприца → 194/112 — source: `nhs-cuts`
- Длабока рана на градите, стомакот, вратот или главата → 194/112 — source: `nhs-cuts`
- Отсечен дел од прст или екстремитет → 194/112 — source: `nhs-cuts`
- Повредениот е блед, ладен, збунет или се онесвестува → 194/112 — source: `nhs-cuts`

**Sources** (accessed 2026-10-07):

- `nhs-cuts` — [NHS (Велика Британија) — Cuts and grazes](https://www.nhs.uk/conditions/cuts-and-grazes/)
- `nhs-bites` — [NHS (Велика Британија) — Animal and human bites](https://www.nhs.uk/conditions/animal-and-human-bites/)
- `nhs-tetanus` — [NHS (Велика Британија) — Tetanus](https://www.nhs.uk/conditions/tetanus/)
- `nhs-rabies` — [NHS (Велика Британија) — Rabies](https://www.nhs.uk/conditions/rabies/)
- `nhs-nosebleed` — [NHS (Велика Британија) — Nosebleed](https://www.nhs.uk/conditions/nosebleed/)

**Rationale:** NHS cuts/grazes, bites, tetanus, rabies, nosebleed. Rabies: animal bites go same day (MK has rabies risk in wildlife; post-exposure decisions are for the clinician).

**Populations not referenced in routing (internal notes):**

- infant_0_3m: Same first aid; any wound needing more than a plaster in an infant is covered by the same-day rules. Clinician to confirm.
- infant_3_12m: As infant_0_3m.
- child: Same thresholds as adults in NHS public guidance.
- older_adult: Referenced via anticoagulant option in nosebleed; skin tears in older adults follow the same rules.
- pregnancy: No pregnancy-specific threshold; tetanus vaccination in pregnancy is a clinician decision.
- chronic: Diabetes/immunosuppression raise infection risk; infected wounds go to 24–48h regardless.

**Where to go:** settings `emergency_department`, `gp`, `self_care`; specialties `infektologija`, `opsta-hirurgija`, `opsta-medicina`, `otorinolaringologija`, `plasticna-hirurgija`, `semejna-medicina`, `urgentna-medicina`.

**Open questions for the clinician:**

- Confirm MK pathway for animal bites (антирабична амбуланта / Институт за јавно здравје / центар за јавно здравје) — we only say 'same-day doctor'.
- Tetanus booster wording uses 'засилувачка вакцина' (avoids the banned word 'доза'); confirm it reads naturally.

### Температура кај возрасни (`fever-adult`)

Adult fever. Sepsis signs, meningism, non-blanching rash, confusion → emergency. Immunosuppression/chemo, pregnancy, ≥65 with high fever, travel → same day. Fever >3 days → 48h. Otherwise self-care with safety net.

**Red flags (asked first):**

- Многу забрзано дишење или недостиг на воздух → 194/112 — source: `nhs-sepsis`
- Кожа, усни или јазик се посинети, сиви, многу бледи или со дамки → 194/112 — source: `nhs-sepsis`
- Осип што не бледнее кога ќе притиснете чаша врз него → 194/112 — source: `nhs-sepsis`
- Збунетост, нејасен говор или тешко будење → 194/112 — source: `nhs-sepsis`
- Вкочанет врат или многу пречи светлината → 194/112 — source: `nhs-meningitis`
- Не сте мокреле цел ден (околу 18 часа или повеќе) → 194/112 — source: `nhs-sepsis`
- Напад (грч) → 194/112 — source: `nhs-fever-adults`

**Sources** (accessed 2026-10-07):

- `nhs-fever-adults` — [NHS (Велика Британија) — Fever in adults](https://www.nhs.uk/symptoms/fever-in-adults/)
- `nhs-sepsis` — [NHS (Велика Британија) — Sepsis](https://www.nhs.uk/conditions/sepsis/)
- `nhs-meningitis` — [NHS (Велика Британија) — Meningitis](https://www.nhs.uk/conditions/meningitis/)

**Rationale:** NHS fever in adults + sepsis signs (public). Hypothermia <36 treated as concerning. Pregnancy and immunosuppression lower thresholds.

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.

**Where to go:** settings `emergency_department`, `gp`, `on_call`, `self_care`; specialties `infektologija`, `interna-medicina`, `opsta-medicina`, `semejna-medicina`, `urgentna-medicina`.

**Open questions for the clinician:**

- Is ≥39 °C in ≥65 the right same-day threshold, or should any fever in ≥65 with any risk factor be same day?

### Проблеми со очите (црвено око, промена на видот) (`eye-problems`)

Red eye / vision change (all ages, adult wording). Sudden vision loss, chemical injury, penetrating injury, curtain/flashes, painful red eye with vomiting/halos → emergency/same day. Contact lens + painful red eye → same day. Sticky red eye → pharmacy. Newborn red sticky eye → same day.

**Red flags (asked first):**

- Ненадејно губење или затемнување на видот на едното или двете очи → 194/112 — source: `nhs-vision-loss`
- Хемикалија (на пр. средство за чистење, вар) влезе во окото → 194/112 — source: `nhs-eye-injuries`
- Нешто се заби во окото или удар со голема сила → 194/112 — source: `nhs-eye-injuries`
- Нарушен вид заедно со опуштено лице, слабост во рака или тешкотии со говорот → 194/112 — source: `nhs-stroke`
- Многу силна болка во окото со гадење/повраќање или гледање ореоли (кругови) околу светлата → 194/112 — source: `nhs-glaucoma`

**Sources** (accessed 2026-10-07):

- `nhs-red-eye` — [NHS (Велика Британија) — Red eye](https://www.nhs.uk/symptoms/red-eye/)
- `nhs-vision-loss` — [NHS (Велика Британија) — Vision loss](https://www.nhs.uk/conditions/vision-loss/)
- `nhs-eye-injuries` — [NHS (Велика Британија) — Eye injuries](https://www.nhs.uk/conditions/eye-injuries/)
- `nhs-conjunctivitis` — [NHS (Велика Британија) — Conjunctivitis](https://www.nhs.uk/conditions/conjunctivitis/)
- `nhs-glaucoma` — [NHS (Велика Британија) — Glaucoma](https://www.nhs.uk/conditions/glaucoma/)
- `nhs-retinal-detachment` — [NHS (Велика Британија) — Detached retina](https://www.nhs.uk/conditions/detached-retina-retinal-detachment/)
- `nhs-stroke` — [NHS (Велика Британија) — Stroke — symptoms](https://www.nhs.uk/conditions/stroke/symptoms/)

**Rationale:** NHS red eye / vision loss / eye injuries / retinal detachment / acute glaucoma. Contact lens + painful eye → same day (keratitis risk).

**Populations not referenced in routing (internal notes):**

- child: Same red flags for children; NHS red-eye advice is not age-specific beyond newborns (covered).
- older_adult: Acute glaucoma and retinal detachment are commoner with age but the same thresholds apply.
- chronic: No condition-specific threshold; contact lens wear is asked directly.

**Where to go:** settings `emergency_department`, `pharmacy`, `self_care`, `specialist`; specialties `oftalmologija`, `opsta-medicina`, `semejna-medicina`, `urgentna-medicina`.

### Висок крвен притисок (измерена вредност) (`high-blood-pressure`)

Adult with a high home BP reading. Stroke/chest pain/breathlessness/confusion/severe headache/vision change → 194. ≥180/120 without symptoms → same day recheck; pregnancy/postpartum ≥140/90 → same-day maternity; 160–179 or 100–119 → 48h; 140–159/90–99 → GP week.

**Red flags (asked first):**

- Опуштено лице, слабост или трпнење на рака или нога, нејасен говор → 194/112 — source: `nhs-stroke`
- Болка или притисок во градите, или силен недостиг на воздух → 194/112 — source: `nhs-chest-pain`
- Ненадејна силна главоболка, збунетост или нагло нарушен вид → 194/112 — source: `nhs-high-bp`
- Бременост или неодамнешно породување, со силна главоболка, нарушен вид, болка под ребрата или ненадеен оток на лицето и рацете → 194/112 — only when `{"demo": "pregnancy", "in": ["pregnant", "postpartum", "unsure"]}` — source: `nhs-pre-eclampsia`

**Sources** (accessed 2026-10-07):

- `nhs-high-bp` — [NHS (Велика Британија) — High blood pressure](https://www.nhs.uk/conditions/high-blood-pressure/)
- `nhs-stroke` — [NHS (Велика Британија) — Stroke — symptoms](https://www.nhs.uk/conditions/stroke/symptoms/)
- `nhs-pre-eclampsia` — [NHS (Велика Британија) — Pre-eclampsia](https://www.nhs.uk/conditions/pre-eclampsia/)
- `nhs-chest-pain` — [NHS (Велика Британија) — Chest pain](https://www.nhs.uk/symptoms/chest-pain/)

**Rationale:** NHS high blood pressure: ≥180/120 needs same-day assessment; ≥140/90 at home is raised. Pregnancy ≥140/90 is the public pre-eclampsia threshold. Bands are plain cut-offs, not a guideline table.

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.
- older_adult: Same thresholds; NHS uses 150/90 as a treatment target over 80, not as a triage threshold. Clinician to confirm.
- chronic: Kidney disease is asked directly; heart disease symptoms are covered by red flags.

**Where to go:** settings `emergency_department`, `gp`, `gynecology`, `on_call`, `self_care`; specialties `ginekologija`, `interna-medicina`, `kardiologija`, `opsta-medicina`, `semejna-medicina`, `urgentna-medicina`.

**Open questions for the clinician:**

- Home-BP thresholds (NHS uses 135/85 for home averages). We use 140/90 for a single reading — conservative enough?
- Is low BP (≤89 systolic) → 24–48h right, or should symptoms decide?

### Пролив или повраќање кај возрасни (`diarrhoea-vomiting-adult`)

Adult D&V. Vomiting blood, black stools, severe abdominal pain, confusion, no urine 12h+, stiff neck → emergency/same day. Unable to keep fluids >24h, bloody diarrhoea, risk groups, pregnancy → same day/48h. >7 days diarrhoea or >2 days vomiting → GP.

**Red flags (asked first):**

- Повраќате крв или нешто што личи на талог од кафе → 194/112 — source: `nhs-dv`
- Црна, катранеста столица или многу крв во столицата → 194/112 — source: `nhs-dv`
- Многу силна болка во стомакот што не попушта → 194/112 — source: `nhs-dv`
- Збунетост, многу тешко будење или несвестица → 194/112 — source: `nhs-dv`
- Вкочанет врат, осетливост на светлина или осип што не бледнее → 194/112 — source: `nhs-meningitis`

**Sources** (accessed 2026-10-07):

- `nhs-dv` — [NHS (Велика Британија) — Diarrhoea and vomiting](https://www.nhs.uk/symptoms/diarrhoea-and-vomiting/)
- `nhs-dehydration` — [NHS (Велика Британија) — Dehydration](https://www.nhs.uk/conditions/dehydration/)
- `nhs-sepsis` — [NHS (Велика Британија) — Sepsis](https://www.nhs.uk/conditions/sepsis/)
- `nhs-meningitis` — [NHS (Велика Британија) — Meningitis](https://www.nhs.uk/conditions/meningitis/)
- `nhs-stomach-ache` — [NHS (Велика Британија) — Stomach ache](https://www.nhs.uk/symptoms/stomach-ache/)

**Rationale:** NHS diarrhoea and vomiting: >2 days vomiting / >7 days diarrhoea → GP; dehydration and blood → urgent. 'Sick day' medication note is generic (no drug instructions).

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.
- older_adult: Referenced: dehydration signs in ≥65 → same day.

**Where to go:** settings `emergency_department`, `gp`, `on_call`, `pharmacy`, `self_care`; specialties `gastroenterohepatologija`, `infektologija`, `interna-medicina`, `opsta-medicina`, `semejna-medicina`, `urgentna-medicina`.

### Повреда на рака или нога (`limb-injury`)

Limb/joint injury (all ages). Open fracture, obvious deformity, cold/pale/pulseless limb, major trauma → 194/ED. Unable to bear weight, severe swelling, suspected fracture, hip injury in older adults → same day. Sprain → self-care. Swollen calf without injury → DVT pathway.

**Red flags (asked first):**

- Коската се гледа или прободува кожата → 194/112 — source: `nhs-broken-leg`
- Раката или ногата е видливо искривена или во неприроден положај → 194/112 — source: `nhs-broken-leg`
- Делот под повредата е студен, бел или син, или трне и не може да се движи → 194/112 — source: `nhs-broken-arm`
- Повредата е од сообраќајка, пад од висина или има повеќе тешки повреди → 194/112 — source: `nhs-broken-leg`
- Постаро лице падна и не може да стане или да стапне на ногата (можна скршеница на колкот) → 194/112 — only when `{"demo": "age_band", "in": ["older_65_plus"]}` — source: `nhs-broken-leg`

**Sources** (accessed 2026-10-07):

- `nhs-broken-arm` — [NHS (Велика Британија) — Broken arm or wrist](https://www.nhs.uk/conditions/broken-arm-or-wrist/)
- `nhs-broken-leg` — [NHS (Велика Британија) — Broken leg](https://www.nhs.uk/conditions/broken-leg/)
- `nhs-broken-ankle` — [NHS (Велика Британија) — Broken ankle](https://www.nhs.uk/conditions/broken-ankle/)
- `nhs-sprains` — [NHS (Велика Британија) — Sprains and strains](https://www.nhs.uk/conditions/sprains-and-strains/)
- `nhs-dvt` — [NHS (Велика Британија) — Deep vein thrombosis (DVT)](https://www.nhs.uk/conditions/deep-vein-thrombosis-dvt/)
- `nhs-gout` — [NHS (Велика Британија) — Gout](https://www.nhs.uk/conditions/gout/)
- `nhs-falls` — [NHS (Велика Британија) — Falls](https://www.nhs.uk/conditions/falls/)

**Rationale:** NHS broken arm/leg/ankle and sprains; DVT (swollen calf) and septic arthritis/gout (hot joint) included as non-traumatic presentations. Ottawa rules are public but not reproduced; 'cannot bear weight' and 'bone tenderness' approximate them conservatively.

**Populations not referenced in routing (internal notes):**

- infant_0_3m: Referenced via under-5 injury rule.
- infant_3_12m: Referenced via under-5 injury rule.
- child: Under-5 referenced; older children follow adult thresholds. Non-accidental injury is out of scope — clinician to advise on wording.
- pregnancy: Swollen calf (DVT) already same day; pregnancy raises DVT risk but does not change routing.

**Where to go:** settings `emergency_department`, `gp`, `on_call`, `self_care`; specialties `fizikalna-medicina`, `interna-medicina`, `opsta-medicina`, `ortopedija`, `revmatologija`, `semejna-medicina`, `traumatologija`, `urgentna-medicina`, `vaskularna-hirurgija`.

**Open questions for the clinician:**

- Should we add a gentle safeguarding note for injuries in infants (non-mobile babies with bruises/fractures)?

### Болка во грбот (`back-pain`)

Adult back pain. Cauda equina signs (saddle numbness, bladder/bowel change, bilateral leg weakness), trauma in elderly, tearing pain → emergency. Fever/immunosuppression/cancer history, night pain → same day/48h. Sciatica without red flags → GP. Simple mechanical → self-care.

**Red flags (asked first):**

- Трпнење или губење на чувството околу анусот, половите органи или внатрешната страна на бутовите → 194/112 — source: `nhs-back-pain`
- Ново тешко мокрење, неможност да мокрите или неконтролирано испуштање урина или столица → 194/112 — source: `nhs-back-pain`
- Слабост или трпнење во двете нозе или нестабилен од, што се влошува → 194/112 — source: `nhs-back-pain`
- Болката почна по сериозен пад или незгода → 194/112 — source: `nhs-back-pain`
- Ненадејна, многу силна болка во средината на грбот или стомакот, со несвестица → 194/112 — source: `nhs-back-pain`
- Болка во градите или отежнато дишење → 194/112 — source: `nhs-chest-pain`

**Sources** (accessed 2026-10-07):

- `nhs-back-pain` — [NHS (Велика Британија) — Back pain](https://www.nhs.uk/conditions/back-pain/)
- `nhs-sciatica` — [NHS (Велика Британија) — Sciatica](https://www.nhs.uk/conditions/sciatica/)
- `nhs-kidney-stones` — [NHS (Велика Британија) — Kidney stones](https://www.nhs.uk/conditions/kidney-stones/)
- `nhs-chest-pain` — [NHS (Велика Британија) — Chest pain](https://www.nhs.uk/symptoms/chest-pain/)

**Rationale:** NHS back pain/sciatica: cauda equina emergency signs; infection/cancer/osteoporosis as yellow-orange flags.

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.
- pregnancy: Back pain is common in pregnancy; flank pain/urinary blood in pregnancy is referenced (same day). Labour-type pain is in the pregnancy-concerns flow.

**Where to go:** settings `emergency_department`, `gp`, `on_call`, `self_care`; specialties `fizikalna-medicina`, `nevrologija`, `opsta-medicina`, `ortopedija`, `semejna-medicina`, `urgentna-medicina`, `urologija`.

**Open questions for the clinician:**

- Should 'cancer history + new back pain' be same day (metastatic cord compression risk) rather than 24–48h?

### Тегоби при мокрење (`urinary-symptoms`)

Adult urinary symptoms. Unable to pass urine with pain, sepsis signs → emergency/same day. Loin pain + fever, men, pregnancy, visible blood, catheter → same day/48h. Uncomplicated cystitis in women → pharmacy/GP. Testicular pain in males → emergency red flag.

**Red flags (asked first):**

- Не можете да мокрите, а мочниот меур е полн и боли → 194/112 — source: `nhs-uti`
- Треска со тресење, збунетост, многу забрзано дишење или многу бледа кожа со дамки → 194/112 — source: `nhs-sepsis`
- Ненадејна, силна болка во тестисот → 194/112 — only when `{"demo": "sex", "eq": "male"}` — source: `nhs-testicle-pain`

**Sources** (accessed 2026-10-07):

- `nhs-uti` — [NHS (Велика Британија) — Urinary tract infections (UTIs)](https://www.nhs.uk/conditions/urinary-tract-infections-utis/)
- `nhs-kidney-infection` — [NHS (Велика Британија) — Kidney infection](https://www.nhs.uk/conditions/kidney-infection/)
- `nhs-blood-in-urine` — [NHS (Велика Британија) — Blood in urine](https://www.nhs.uk/symptoms/blood-in-urine/)
- `nhs-sepsis` — [NHS (Велика Британија) — Sepsis](https://www.nhs.uk/conditions/sepsis/)
- `nhs-testicle-pain` — [NHS (Велика Британија) — Testicle pain](https://www.nhs.uk/symptoms/testicle-pain/)
- `nhs-sti` — [NHS (Велика Британија) — Sexually transmitted infections](https://www.nhs.uk/conditions/sexually-transmitted-infections-stis/)

**Rationale:** NHS UTI / kidney infection / blood in urine. Men, pregnancy, catheter, fever and loin pain exclude self-care.

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.
- older_adult: Referenced: ≥65 with fever → same day; any ≥65 → GP minimum. Older adults with new confusion use the confusion flow.

**Where to go:** settings `emergency_department`, `gp`, `gynecology`, `on_call`, `pharmacy`; specialties `dermatologija`, `ginekologija`, `infektologija`, `nefrologija`, `opsta-medicina`, `semejna-medicina`, `urgentna-medicina`, `urologija`.

**Open questions for the clinician:**

- In MK, urinary antibiotics are prescription-only; pharmacy outcome is for symptom advice only. Confirm wording.

### Осип или промени на кожата (`rash-skin`)

Adult rash. Non-blanching rash with fever/unwell, rapidly spreading rash with blistering/skin peeling, anaphylaxis signs → emergency. Spreading red hot skin with fever (cellulitis), rash near eye (shingles), immunosuppressed → same day/48h. Hives/itch → pharmacy.

**Red flags (asked first):**

- Осип што не бледнее под притисок на чаша, а лицето е болно или има температура → 194/112 — source: `nhs-meningitis`
- Отекување на усните, јазикот или грлото, или отежнато дишење → 194/112 — source: `nhs-anaphylaxis`
- Брзо ширење на осип со меури, лупење на кожата или рани во устата и очите (често по нов лек) → 194/112 — source: `nhs-hives`

**Sources** (accessed 2026-10-07):

- `nhs-cellulitis` — [NHS (Велика Британија) — Cellulitis](https://www.nhs.uk/conditions/cellulitis/)
- `nhs-shingles` — [NHS (Велика Британија) — Shingles](https://www.nhs.uk/conditions/shingles/)
- `nhs-hives` — [NHS (Велика Британија) — Hives](https://www.nhs.uk/conditions/hives/)
- `nhs-impetigo` — [NHS (Велика Британија) — Impetigo](https://www.nhs.uk/conditions/impetigo/)
- `nhs-scabies` — [NHS (Велика Британија) — Scabies](https://www.nhs.uk/conditions/scabies/)
- `nhs-meningitis` — [NHS (Велика Британија) — Meningitis](https://www.nhs.uk/conditions/meningitis/)
- `nhs-anaphylaxis` — [NHS (Велика Британија) — Anaphylaxis](https://www.nhs.uk/conditions/anaphylaxis/)
- `nhs-sepsis` — [NHS (Велика Британија) — Sepsis](https://www.nhs.uk/conditions/sepsis/)
- `nhs-chickenpox` — [NHS (Велика Британија) — Chickenpox](https://www.nhs.uk/conditions/chickenpox/)

**Rationale:** NHS cellulitis/shingles/hives/impetigo/scabies; glass test and anaphylaxis signs as red flags; SJS/TEN-type features (blistering, mucosal involvement after new drug) emergency.

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.
- older_adult: Same thresholds; shingles is commoner with age but routing is by presentation.
- pregnancy: Referenced: fever/blistering rash in pregnancy → same day maternity/GP.

**Where to go:** settings `emergency_department`, `gp`, `gynecology`, `on_call`, `pharmacy`, `specialist`; specialties `dermatologija`, `ginekologija`, `infektologija`, `oftalmologija`, `opsta-medicina`, `semejna-medicina`, `urgentna-medicina`.

### Кашлица, настинка или грип (`cough-cold-flu`)

Adult cough/cold/flu. Breathing difficulty, chest pain, coughing blood, confusion → emergency. Risk groups with flu-like illness, high fever + breathlessness → same day/48h. Cough >3 weeks → GP. Otherwise pharmacy/self-care.

**Red flags (asked first):**

- Многу отежнато дишење или не можете да зборувате во цели реченици → 194/112 — source: `nhs-cough`
- Болка или притисок во градите → 194/112 — source: `nhs-flu`
- Искашлувате повеќе крв (не само траги во слузта) → 194/112 — source: `nhs-cough`
- Посинети или сиви усни или кожа → 194/112 — source: `nhs-sob`
- Збунетост или многу тешко будење → 194/112 — source: `nhs-sepsis`

**Sources** (accessed 2026-10-07):

- `nhs-cough` — [NHS (Велика Британија) — Cough](https://www.nhs.uk/symptoms/cough/)
- `nhs-flu` — [NHS (Велика Британија) — Flu](https://www.nhs.uk/conditions/flu/)
- `nhs-cold` — [NHS (Велика Британија) — Common cold](https://www.nhs.uk/conditions/common-cold/)
- `nhs-pneumonia` — [NHS (Велика Британија) — Pneumonia](https://www.nhs.uk/conditions/pneumonia/)
- `nhs-sob` — [NHS (Велика Британија) — Shortness of breath](https://www.nhs.uk/symptoms/shortness-of-breath/)
- `nhs-sepsis` — [NHS (Велика Британија) — Sepsis](https://www.nhs.uk/conditions/sepsis/)

**Rationale:** NHS cough/flu/cold pages; risk groups for flu complications; cough >3 weeks → GP.

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.

**Where to go:** settings `emergency_department`, `gp`, `on_call`, `pharmacy`, `self_care`; specialties `infektologija`, `opsta-medicina`, `pulmologija`, `semejna-medicina`, `urgentna-medicina`.

### Болка во грлото (`sore-throat`)

Adult sore throat. Stridor, drooling/can't swallow saliva, breathing difficulty → emergency. One-sided severe swelling/trismus (quinsy), immunosuppression/carbimazole-type drugs → same day. High fever + pus no cough → 48h. Otherwise pharmacy/self-care.

**Red flags (asked first):**

- Отежнато или бучно дишење (свирење при вдишување) → 194/112 — source: `nhs-sore-throat`
- Не можете да ја голтате плунката, течат плунки → 194/112 — source: `nhs-sore-throat`
- Отекување на јазикот, усните или вратот што брзо напредува → 194/112 — source: `nhs-anaphylaxis`

**Sources** (accessed 2026-10-07):

- `nhs-sore-throat` — [NHS (Велика Британија) — Sore throat](https://www.nhs.uk/symptoms/sore-throat/)
- `nhs-tonsillitis` — [NHS (Велика Британија) — Tonsillitis (incl. quinsy)](https://www.nhs.uk/conditions/tonsillitis/)
- `nhs-glandular-fever` — [NHS (Велика Британија) — Glandular fever](https://www.nhs.uk/conditions/glandular-fever/)
- `nhs-anaphylaxis` — [NHS (Велика Британија) — Anaphylaxis](https://www.nhs.uk/conditions/anaphylaxis/)

**Rationale:** NHS sore throat/tonsillitis/quinsy. FeverPAIN/Centor scores are public but not reproduced; a plain 'fever + pus, no cough' item approximates them conservatively.

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.
- pregnancy: No pregnancy-specific threshold in public sore-throat guidance; fever in pregnancy is handled by the fever flow.
- older_adult: No age-specific threshold in public guidance; immunosuppression (demo.conditions) is referenced.

**Where to go:** settings `emergency_department`, `gp`, `on_call`, `pharmacy`; specialties `opsta-medicina`, `otorinolaringologija`, `semejna-medicina`, `urgentna-medicina`.

**Open questions for the clinician:**

- Should we encode FeverPAIN explicitly once reviewed (public scoring)?

### Болка во увото (`ear-pain-adult`)

Adult earache. Swelling/redness behind the ear with protruding ear, facial weakness, severe headache + confusion → emergency/same day. Discharge, hearing loss, >3 days → 48h. Sudden hearing loss in one ear → same day. Otherwise self-care/pharmacy.

**Red flags (asked first):**

- Оток, црвенило и болка зад увото, а увото е одбиено нанапред → 194/112 — source: `nhs-ear-infections`
- Опуштено лице или слабост на едната страна на лицето → 194/112 — source: `nhs-stroke`
- Силна главоболка со вкочанет врат, збунетост или поспаност → 194/112 — source: `nhs-meningitis`

**Sources** (accessed 2026-10-07):

- `nhs-earache` — [NHS (Велика Британија) — Earache](https://www.nhs.uk/symptoms/earache/)
- `nhs-ear-infections` — [NHS (Велика Британија) — Ear infections](https://www.nhs.uk/conditions/ear-infections/)
- `nhs-stroke` — [NHS (Велика Британија) — Stroke — symptoms](https://www.nhs.uk/conditions/stroke/symptoms/)
- `nhs-meningitis` — [NHS (Велика Британија) — Meningitis](https://www.nhs.uk/conditions/meningitis/)

**Rationale:** NHS earache / ear infections. Sudden sensorineural hearing loss escalated same day (time-sensitive treatment).

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.
- pregnancy: No pregnancy-specific threshold in public earache guidance.
- older_adult: No age-specific threshold; sudden hearing loss is same day at any age.
- chronic: Diabetes with severe ear pain is asked directly in q_features (necrotising otitis externa risk).

**Where to go:** settings `emergency_department`, `gp`, `on_call`, `pharmacy`, `self_care`; specialties `opsta-medicina`, `otorinolaringologija`, `semejna-medicina`, `urgentna-medicina`.

### Забоболка и проблеми со забите (`toothache`)

Toothache/dental (all ages). Facial swelling spreading to eye/neck with breathing/swallowing difficulty → emergency. Facial swelling with fever, knocked-out adult tooth, uncontrolled bleeding after extraction → same day. Toothache >2 days → dentist.

**Red flags (asked first):**

- Оток на лицето или вратот со отежнато дишење, голтање или неможност да ја отворите устата → 194/112 — source: `nhs-dental-abscess`
- Отокот се шири кон окото, а окото тешко се отвора → 194/112 — source: `nhs-dental-abscess`

**Sources** (accessed 2026-10-07):

- `nhs-toothache` — [NHS (Велика Британија) — Toothache](https://www.nhs.uk/symptoms/toothache/)
- `nhs-dental-abscess` — [NHS (Велика Британија) — Dental abscess](https://www.nhs.uk/conditions/dental-abscess/)

**Rationale:** NHS toothache/dental abscess: facial swelling affecting eye/neck or breathing → A&E; dentist otherwise.

**Populations not referenced in routing (internal notes):**

- infant_0_3m: Teething/dental issues are not expected; infants with mouth swelling and fever are covered by red flags and the infant fever flow.
- infant_3_12m: Teething discomfort is self-care; fever in infants is not caused by teething and is routed by the fever flow.
- child: Knocked-out baby teeth should NOT be replaced (NHS); the instruction above names permanent teeth only. Clinician to confirm wording.
- pregnancy: Dental care in pregnancy is the same; no separate threshold.
- older_adult: Same thresholds.
- chronic: Immunosuppression with facial swelling would reach same day via fever; clinician to confirm whether swelling alone should be same day.

**Where to go:** settings `dentist`, `emergency_department`, `pharmacy`; specialties `maksilofacijalna-hirurgija`, `urgentna-medicina`.

### Проблеми со спиењето (`sleep-problems`)

Adult sleep problems. Crisis features handled by mental-health red flags (repeated here only as 'not safe'); falling asleep while driving or witnessed apnoeas → GP (doc48 if driving). Insomnia <4 weeks → self-care; >3 months or affecting life → GP.

**Red flags (asked first):**

- Поради расположението имате мисли да се повредите или не се чувствувате безбедни → crisis — source: `nhs-suicidal`

**Sources** (accessed 2026-10-07):

- `nhs-insomnia` — [NHS (Велика Британија) — Insomnia](https://www.nhs.uk/conditions/insomnia/)
- `nhs-sleep-apnoea` — [NHS (Велика Британија) — Sleep apnoea](https://www.nhs.uk/conditions/sleep-apnoea/)
- `nhs-suicidal` — [NHS (Велика Британија) — Help for suicidal thoughts](https://www.nhs.uk/mental-health/feelings-symptoms-behaviours/behaviours/help-for-suicidal-thoughts/)

**Rationale:** NHS insomnia (>3 months → GP) and sleep apnoea (snoring + witnessed apnoeas, daytime sleepiness, driving risk).

**Populations not referenced in routing (internal notes):**

- child: Teenagers (13–17) follow the adult path for this symptom: the public adult guidance applies and the thresholds are not lower for teens. Children under 13 are not in this flow's audience and are offered the child flows.
- chronic: No condition-specific threshold; medication side effects are a GP question.

**Where to go:** settings `gp`, `self_care`; specialties `nevrologija`, `opsta-medicina`, `psihijatrija`, `pulmologija`, `semejna-medicina`.

### Загриженост во бременост или по породување (`pregnancy-concerns`)

Pregnancy and postpartum concerns. Seizure, heavy bleeding, severe abdominal pain, pre-eclampsia features, chest pain/breathlessness → 194. Reduced/absent fetal movements → 194 (integration decision 2026-10-07). Waters breaking, regular contractions <37 weeks, fever, severe vomiting, calf swelling → same day (go now) to maternity. Itching of hands/feet → 24–48h. Postpartum: heavy bleeding, fever, wound infection, mood (crisis → global crisis).

**Red flags (asked first):**

- Напад (грч) или губење свест → 194/112 — source: `nhs-pre-eclampsia`
- Обилно вагинално крварење → 194/112 — source: `nhs-preg-bleeding`
- Силна, постојана болка во стомакот што не попушта меѓу контракциите → 194/112 — source: `nhs-preg-stomach-pain`
- Силна главоболка, нарушен вид (треперење, заматување), болка под ребрата или ненадеен оток на лицето и рацете → 194/112 — source: `nhs-pre-eclampsia`
- Болка во градите или ненадеен недостиг на воздух → 194/112 — source: `nhs-dvt`
- Пукна водењакот и се гледа или се чувствува папочна врвца во вагината → 194/112 — source: `nhs-labour-signs`
- Мисли да се повредите себеси или бебето → crisis — source: `nhs-postnatal-depression`

**Sources** (accessed 2026-10-07):

- `nhs-pre-eclampsia` — [NHS (Велика Британија) — Pre-eclampsia](https://www.nhs.uk/conditions/pre-eclampsia/)
- `nhs-preg-bleeding` — [NHS (Велика Британија) — Vaginal bleeding in pregnancy](https://www.nhs.uk/pregnancy/common-symptoms/vaginal-bleeding/)
- `nhs-baby-movements` — [NHS (Велика Британија) — Your baby's movements](https://www.nhs.uk/pregnancy/keeping-well/your-babys-movements/)
- `nhs-labour-signs` — [NHS (Велика Британија) — Signs that labour has begun](https://www.nhs.uk/pregnancy/labour-and-birth/signs-that-labour-has-begun/)
- `nhs-hyperemesis` — [NHS (Велика Британија) — Severe vomiting in pregnancy](https://www.nhs.uk/pregnancy/complications/severe-vomiting/)
- `nhs-preg-stomach-pain` — [NHS (Велика Британија) — Stomach pain in pregnancy](https://www.nhs.uk/pregnancy/common-symptoms/stomach-pain/)
- `nhs-dvt` — [NHS (Велика Британија) — Deep vein thrombosis (DVT)](https://www.nhs.uk/conditions/deep-vein-thrombosis-dvt/)
- `nhs-postnatal-depression` — [NHS (Велика Британија) — Postnatal depression](https://www.nhs.uk/mental-health/conditions/postnatal-depression/)
- `nhs-mastitis` — [NHS (Велика Британија) — Mastitis](https://www.nhs.uk/conditions/mastitis/)
- `nhs-pe` — [NHS (Велика Британија) — Pulmonary embolism](https://www.nhs.uk/conditions/pulmonary-embolism/)

**Rationale:** NHS pregnancy pages: reduced movements, waters breaking, bleeding, pre-eclampsia, severe vomiting, labour signs; postnatal danger signs (bleeding, fever, DVT, mood/psychosis). Cord prolapse knee-chest position is standard public first aid.

**Populations not referenced in routing (internal notes):**

- child: Teen pregnancies use the same flow and thresholds.
- pregnancy: This flow is the pregnancy flow; stage is asked directly.

**Where to go:** settings `emergency_department`, `gynecology`; specialties `ginekologija`, `opsta-medicina`, `psihijatrija`, `semejna-medicina`, `urgentna-medicina`.

**Open questions for the clinician:**

- MK has no maternity triage phone line equivalent; we tell users to go to the nearest породилиште. Confirm.
- Is the knee-chest instruction for visible cord appropriate for lay users?
- Should reduced fetal movements be emergency_now (194) instead of urgent_same_day 'go now'? **Integration decision 2026-10-07: changed to emergency_now** (see § Integration decisions) — please confirm.

### Вагинално крварење (`vaginal-bleeding`)

Vaginal bleeding (teens/adults/older). Heavy bleeding with faintness, possible pregnancy with one-sided pain/shoulder-tip pain → 194. Any bleeding in pregnancy, heavy postpartum bleeding, very heavy bleeding → same day gynaecology. Postmenopausal bleeding → gynaecologist within 24–48 h (integration decision 2026-10-07; urgent referral pathway). Bleeding between periods/after sex → GP.

**Red flags (asked first):**

- Многу обилно крварење (полна влошка за помалку од час, со грутки) со вртоглавица, бледило или несвестица → 194/112 — source: `nhs-heavy-periods`
- Можна бременост и силна болка на едната страна долу, болка во врвот на рамото или несвестица → 194/112 — source: `nhs-ectopic`
- Во бременост: обилно крварење или крварење со силна болка во стомакот → 194/112 — only when `{"demo": "pregnancy", "in": ["pregnant", "postpartum", "unsure"]}` — source: `nhs-preg-bleeding`

**Sources** (accessed 2026-10-07):

- `nhs-heavy-periods` — [NHS (Велика Британија) — Heavy periods](https://www.nhs.uk/conditions/heavy-periods/)
- `nhs-pmb` — [NHS (Велика Британија) — Post-menopausal bleeding](https://www.nhs.uk/symptoms/post-menopausal-bleeding/)
- `nhs-miscarriage` — [NHS (Велика Британија) — Miscarriage](https://www.nhs.uk/conditions/miscarriage/)
- `nhs-ectopic` — [NHS (Велика Британија) — Ectopic pregnancy](https://www.nhs.uk/conditions/ectopic-pregnancy/)
- `nhs-preg-bleeding` — [NHS (Велика Британија) — Vaginal bleeding in pregnancy](https://www.nhs.uk/pregnancy/common-symptoms/vaginal-bleeding/)
- `nhs-pid` — [NHS (Велика Британија) — Pelvic inflammatory disease](https://www.nhs.uk/conditions/pelvic-inflammatory-disease-pid/)
- `nhs-sti` — [NHS (Велика Британија) — Sexually transmitted infections](https://www.nhs.uk/conditions/sexually-transmitted-infections-stis/)

**Rationale:** NHS heavy periods / post-menopausal bleeding / miscarriage / ectopic / bleeding in pregnancy. Any bleeding in pregnancy → same day. Postmenopausal bleeding → this-week gynaecology (NHS: urgent referral).

**Populations not referenced in routing (internal notes):**

- child: Teen menstrual bleeding uses the same questions; heavy bleeding with faintness is a red flag at any age.
- chronic: Anticoagulant use is asked directly; no other condition-specific threshold.

**Where to go:** settings `emergency_department`, `gynecology`; specialties `ginekologija`, `opsta-medicina`, `semejna-medicina`, `urgentna-medicina`.

**Open questions for the clinician:**

- NHS treats postmenopausal bleeding as a 2-week-wait referral. Is see_gp_this_week (to gynaecologist) the right level in MK? **Integration decision 2026-10-07: raised to see_doctor_24_48h** — please confirm.

### Итна контрацепција (по незаштитен однос) (`emergency-contraception`)

Emergency contraception guidance: where to go and how soon. Red flag: possible ectopic (pain/bleeding). <72h → pharmacy or gynaecologist today; 72–120h → gynaecologist today (some methods work up to 5 days); >120h → pregnancy test and gynaecologist. Sexual violence → same-day support (police 192 / emergency department).

**Red flags (asked first):**

- Силна болка на едната страна долу во стомакот, несвестица или болка во врвот на рамото → 194/112 — source: `nhs-ectopic`

**Sources** (accessed 2026-10-07):

- `nhs-ec` — [NHS (Велика Британија) — Emergency contraception](https://www.nhs.uk/contraception/emergency-contraception/)
- `who-ec` — [Светска здравствена организација (WHO) — Emergency contraception — fact sheet](https://www.who.int/news-room/fact-sheets/detail/emergency-contraception)
- `nhs-sti` — [NHS (Велика Британија) — Sexually transmitted infections](https://www.nhs.uk/conditions/sexually-transmitted-infections-stis/)
- `nhs-ectopic` — [NHS (Велика Британија) — Ectopic pregnancy](https://www.nhs.uk/conditions/ectopic-pregnancy/)

**Rationale:** NHS emergency contraception (fetched 2026-10-07): levonorgestrel ≤72h, ulipristal ≤120h, copper IUD ≤5 days; WHO EC fact sheet. No drug names or doses in public copy.

**Populations not referenced in routing (internal notes):**

- child: Teens 13–17 are included; same timing applies. Consent/confidentiality for minors is an open question.
- pregnancy: Late period / >5 days routes to a pregnancy test first.
- chronic: No condition-specific threshold; the pharmacist/gynaecologist checks interactions.

**Where to go:** settings `emergency_department`, `gynecology`, `pharmacy`; specialties `ginekologija`, `urgentna-medicina`.

**Open questions for the clinician:**

- Availability in MK: is emergency contraception sold in pharmacies without a prescription? Wording deliberately says the pharmacist will advise whether a doctor is needed — please confirm MK status (МАЛМЕД).
- Sexual-violence pathway in MK: is there a designated centre (e.g. кризен центар за жртви на сексуално насилство at specific hospitals)? Not verified — we only name police 192/112 and emergency/gynaecology services.
- Minors (<18): confidentiality and consent rules in MK for EC — clinician/legal to advise.

### Грутка или промени во дојката (`breast-lump`)

Breast lump/changes (all adults incl. men). Sepsis signs → 194. Red hot breast with fever while breastfeeding → 24–48h (same day if unwell). Any new lump, nipple inversion/discharge with blood, skin dimpling → GP/gynaecologist within 24–48 h (integration decision 2026-10-07) with instruction to request breast ultrasound/mammography referral without delay.

**Red flags (asked first):**

- Црвена, болна дојка со висока температура и тресење, збунетост или забрзано дишење → 194/112 — source: `nhs-mastitis`

**Sources** (accessed 2026-10-07):

- `nhs-breast-lump` — [NHS (Велика Британија) — Breast lump](https://www.nhs.uk/symptoms/breast-lump/)
- `nhs-breast-cancer-symptoms` — [NHS (Велика Британија) — Symptoms of breast cancer in women](https://www.nhs.uk/conditions/breast-cancer-in-women/symptoms-of-breast-cancer-in-women/)
- `nhs-mastitis` — [NHS (Велика Британија) — Mastitis](https://www.nhs.uk/conditions/mastitis/)
- `nhs-sepsis` — [NHS (Велика Британија) — Sepsis](https://www.nhs.uk/conditions/sepsis/)

**Rationale:** NHS breast lump / breast cancer symptoms / mastitis. Any new lump → prompt GP/gynaecology (NHS urgent referral). Men are included (labels are sex-neutral).

**Populations not referenced in routing (internal notes):**

- child: Teens: breast lumps are very rarely serious; same routing (this week) is conservative.
- pregnancy: Lactational mastitis is covered via the breastfeeding question; pregnancy itself does not change routing (a new lump still needs assessment).
- older_adult: Same routing; risk rises with age but every new lump already goes to this-week assessment.

**Where to go:** settings `emergency_department`, `gp`, `on_call`, `self_care`; specialties `ginekologija`, `opsta-hirurgija`, `opsta-medicina`, `radiologija`, `semejna-medicina`, `urgentna-medicina`.

**Open questions for the clinician:**

- NHS uses a 2-week urgent referral for breast lumps over 30; we route all new lumps to 'this week'. Confirm for MK (mammography access via упат). **Integration decision 2026-10-07: raised to see_doctor_24_48h** — please confirm.

### Отежнато дишење кај дете (`child-breathing`)

Breathing difficulty in children. Severe distress, apnoea, cyanosis, stridor at rest, exhaustion → 194. Infants <3 months with any breathing difficulty, bronchiolitis with poor feeding, croup with stridor that settles → same day. Mild croup/cold → home with safety net. Wheeze in known asthma → asthma plan + same-day if no relief.

**Red flags (asked first):**

- Многу тешко дише, стенка при дишење, ноздрите му се шират или меѓу ребрата/под ребрата се вовлекува → 194/112 — source: `nhs-under5-urgent`
- Паузи во дишењето (престанува да дише за 10 секунди или подолго) → 194/112 — source: `nhs-bronchiolitis`
- Сини или сиви усни, јазик или кожа → 194/112 — source: `nhs-under5-urgent`
- Висок, бучен звук при вдишување и кога мирува, или е многу вознемирено/исцрпено → 194/112 — source: `nhs-croup`
- Тешко се буди, млитаво е или не реагира → 194/112 — source: `nhs-under5-urgent`
- Можеби вдишало (голтнало) мал предмет и се гуши или кашла силно → 194/112 — source: `who-pocketbook`

**Sources** (accessed 2026-10-07):

- `nhs-under5-urgent` — [NHS (Велика Британија) — When to get urgent medical help for babies and children under 5](https://www.nhs.uk/baby/health/when-to-get-urgent-medical-help-for-babies-and-children-under-5/)
- `nhs-bronchiolitis` — [NHS (Велика Британија) — Bronchiolitis](https://www.nhs.uk/conditions/bronchiolitis/)
- `nhs-croup` — [NHS (Велика Британија) — Croup](https://www.nhs.uk/conditions/croup/)
- `nhs-asthma` — [NHS (Велика Британија) — Asthma (incl. asthma attacks)](https://www.nhs.uk/conditions/asthma/)
- `who-pocketbook` — [Светска здравствена организација (WHO) — Pocket book of hospital care for children (2nd ed.)](https://www.who.int/publications/i/item/978-92-4-154837-3)

**Rationale:** NHS under-5 urgent signs, bronchiolitis (poor feeding <50%, apnoea), croup (stridor at rest), asthma; WHO pocket book for choking/inhaled foreign body.

**Populations not referenced in routing (internal notes):**

- child: Child flow; older children (5–12) use the same signs; teenagers use the adult shortness-of-breath flow.
- chronic: Asthma is asked directly; immunosuppressed/heart/lung disease children: clinician to decide whether to add a lower threshold.

**Where to go:** settings `emergency_department`, `on_call`, `pediatrics`, `self_care`; specialties `pedijatrija`, `urgentna-medicina`.

### Осип кај дете (со или без температура) (`rash-with-fever-child`)

Rash in children with focus on meningitis/sepsis signs. Non-blanching rash, stiff neck, light sensitivity, drowsy, cold extremities → 194. Infants, unwell child, scarlet-fever pattern, rash near eyes/blisters in eczema → same day/48h. Chickenpox/HFMD/viral rash in a well child → home care with safety net.

**Red flags (asked first):**

- Дише многу брзо или тешко, стенка при дишење или стомачето/меѓу ребрата му се вовлекува → 194/112 — source: `nhs-under5-urgent`
- Кожата, усните или јазикот се сини, сиви, многу бледи или со дамки → 194/112 — source: `nhs-under5-urgent`
- Тешко се буди, не реагира или е млитаво → 194/112 — source: `nhs-under5-urgent`
- Напад (грч) → 194/112 — source: `nhs-under5-urgent`
- Осип што не бледнее кога ќе притиснете чаша врз него → 194/112 — source: `nhs-under5-urgent`
- Вкочанет врат или многу му пречи светлината → 194/112 — source: `nhs-meningitis`
- Студени раце и стапала со температура, или болки во нозете поради кои не сака да оди → 194/112 — source: `nhs-meningitis`
- Отекување на усните, јазикот или лицето → 194/112 — source: `nhs-under5-urgent`

**Sources** (accessed 2026-10-07):

- `nhs-rashes-children` — [NHS (Велика Британија) — Rashes in babies and children](https://www.nhs.uk/symptoms/rashes-babies-and-children/)
- `nhs-meningitis` — [NHS (Велика Британија) — Meningitis](https://www.nhs.uk/conditions/meningitis/)
- `nhs-under5-urgent` — [NHS (Велика Британија) — When to get urgent medical help for babies and children under 5](https://www.nhs.uk/baby/health/when-to-get-urgent-medical-help-for-babies-and-children-under-5/)
- `nhs-scarlet-fever` — [NHS (Велика Британија) — Scarlet fever](https://www.nhs.uk/conditions/scarlet-fever/)
- `nhs-chickenpox` — [NHS (Велика Британија) — Chickenpox](https://www.nhs.uk/conditions/chickenpox/)
- `nhs-hfmd` — [NHS (Велика Британија) — Hand, foot and mouth disease](https://www.nhs.uk/conditions/hand-foot-mouth-disease/)
- `nhs-sepsis` — [NHS (Велика Британија) — Sepsis](https://www.nhs.uk/conditions/sepsis/)
- `nhs-febrile-seizures` — [NHS (Велика Британија) — Febrile seizures](https://www.nhs.uk/conditions/febrile-seizures/)

**Rationale:** NHS rashes in babies and children; meningitis (glass test, dark skin advice); scarlet fever; chickenpox (newborn/immunosuppressed exceptions); hand, foot and mouth disease; eczema herpeticum as same-day.

**Populations not referenced in routing (internal notes):**

- child: Child flow.

**Where to go:** settings `emergency_department`, `on_call`, `pediatrics`, `self_care`; specialties `dermatologija`, `oftalmologija`, `pedijatrija`, `urgentna-medicina`.

### Температура кај бебиња и деца (`fever-infant-child`)

Fever in children under 13. NHS under-5 999 signs as red flags. <3 months ≥38 °C (or not measured) → 194 (integration decision 2026-10-07); <36 °C → go now; 3–6 months ≥39 °C → go now; dehydration, not feeding, fever ≥5 days → same day/48h; otherwise home care with safety net. Based on NHS fever in children and NICE NG143 risk features (not reproduced as a table).

**Red flags (asked first):**

- Дише многу брзо или тешко, стенка при дишење или стомачето/меѓу ребрата му се вовлекува → 194/112 — source: `nhs-under5-urgent`
- Кожата, усните или јазикот се сини, сиви, многу бледи или со дамки → 194/112 — source: `nhs-under5-urgent`
- Тешко се буди, не реагира или е млитаво → 194/112 — source: `nhs-under5-urgent`
- Слаб, писклив или непрекинат плач што е невообичаен за детето → 194/112 — source: `nhs-under5-urgent`
- Напад (грч) → 194/112 — source: `nhs-under5-urgent`
- Осип што не бледнее кога ќе притиснете чаша врз него → 194/112 — source: `nhs-under5-urgent`
- Не мокрело 12 часа или повеќе (суви пелени) → 194/112 — source: `nhs-under5-urgent`
- Вкочанет врат или многу му пречи светлината → 194/112 — source: `nhs-fever-children`
- Температура, а рацете и стапалата се многу студени → 194/112 — source: `nhs-fever-children`

**Sources** (accessed 2026-10-07):

- `nhs-fever-children` — [NHS (Велика Британија) — Fever in children](https://www.nhs.uk/symptoms/fever-in-children/)
- `nhs-under5-urgent` — [NHS (Велика Британија) — When to get urgent medical help for babies and children under 5](https://www.nhs.uk/baby/health/when-to-get-urgent-medical-help-for-babies-and-children-under-5/)
- `nice-ng143` — [NICE (Велика Британија) — NG143 Fever in under 5s: assessment and initial management](https://www.nice.org.uk/guidance/ng143)
- `who-imci` — [Светска здравствена организација (WHO) — Integrated Management of Childhood Illness (IMCI) chart booklet](https://www.who.int/publications/i/item/9789241506823)
- `nhs-febrile-seizures` — [NHS (Велика Британија) — Febrile seizures](https://www.nhs.uk/conditions/febrile-seizures/)
- `nhs-meningitis` — [NHS (Велика Британија) — Meningitis](https://www.nhs.uk/conditions/meningitis/)

**Rationale:** NHS fever in children + 'urgent help for under 5s' (both fetched 2026-10-07): <3 m ≥38 °C, 3–6 m ≥39 °C, <36 °C, no urine 12 h, non-blanching rash, etc. NICE NG143 used for amber features (fever ≥5 days, poor feeding, reduced urine, swelling of a limb/joint). WHO IMCI general danger signs (unable to drink, convulsions, lethargy) are covered by red flags.

**Populations not referenced in routing (internal notes):**

- chronic: Referenced: immunosuppressed children → 24–48h minimum.

**Where to go:** settings `emergency_department`, `on_call`, `pediatrics`, `self_care`; specialties `pedijatrija`, `urgentna-medicina`.

**Open questions for the clinician:**

- Infants <3 months with any fever concern are routed to same day even without a measured 38 °C (conservative). Confirm. **Integration decision 2026-10-07: ≥38 °C or not measured → emergency_now (194/112)**; a measured temperature under 38 °C stays same day — please confirm.
- Under-1 fever >24 h → 24–48h: confirm (not in NHS; our addition).

### Удар во главата кај дете (`head-injury-child`)

Child head injury. NHS 999 signs (LOC not woken, seizure, fall >1 m/5 stairs, CSF/blood from ears, neuro signs, behaviour change, under 1 with bruise/swelling/cut) → emergency. Vomiting, LOC, drowsiness, irritability → same-day ED. Minor bump in a child behaving normally → home with 24-48h observation advice.

**Red flags (asked first):**

- Беше без свест и не се освести, или не може да остане будно → 194/112 — source: `nhs-head-injury`
- Имаше напад (грч) → 194/112 — source: `nhs-head-injury`
- Падна од повеќе од 1 метар или 5 скали, или удар при голема брзина → 194/112 — source: `nhs-head-injury`
- Бистра течност или крв од ушите или носот, модринка зад ушите → 194/112 — source: `nhs-head-injury`
- Слабост, нестабилен од, тешкотии во говорот или видот → 194/112 — source: `nhs-head-injury`
- Бебе помладо од 1 година со модринка, оток или поголема рана на главата → 194/112 — only when `{"demo": "age_band", "in": ["infant_0_3m", "infant_3_12m"]}` — source: `nhs-head-injury`
- Однесувањето е изменето — многу раздразливо, расеано или не е како порано → 194/112 — source: `nhs-head-injury`

**Sources** (accessed 2026-10-07):

- `nhs-head-injury` — [NHS (Велика Британија) — Head injury and concussion](https://www.nhs.uk/conditions/head-injury-and-concussion/)
- `nice-ng232` — [NICE (Велика Британија) — NG232 Head injury: assessment and early management](https://www.nice.org.uk/guidance/ng232)

**Rationale:** NHS head injury (fetched 2026-10-07): under-1 bruise/swelling/large cut → 999; NICE NG232 child risk factors (LOC, vomiting, drowsiness, abnormal behaviour) → same-day ED.

**Populations not referenced in routing (internal notes):**

- child: Child flow.
- infant_0_3m: Referenced via infant red flag and infant rules.
- chronic: Bleeding disorders asked directly.

**Where to go:** settings `emergency_department`, `pediatrics`, `self_care`; specialties `nevrohirurgija`, `pedijatrija`, `urgentna-medicina`.

**Open questions for the clinician:**

- Should a gentle safeguarding note be added for unexplained injuries in non-mobile babies?

### Болка во стомакот кај дете (`abdominal-pain-child`)

Child abdominal pain. Green vomit, severe/constant pain, testicular pain, blood in stool with episodic screaming (intussusception), rigid abdomen, after injury → emergency. Migrating RLQ pain, pain + fever + vomiting, infant pain → same day. Urinary symptoms → 24–48h. Constipation/recurrent pain in a well child → pharmacy/GP.

**Red flags (asked first):**

- Повраќа зелена (жолчна) течност → 194/112 — source: `nhs-dv`
- Ненадејна болка во тестисот или препоните → 194/112 — only when `{"demo": "sex", "in": ["male", "unspecified"]}` — source: `nhs-testicle-pain`
- Напади на силен плач со подигнување на нозете и столица со крв или слуз (кај мало дете) → 194/112 — source: `nhs-stomach-ache`
- Многу силна, постојана болка — детето не сака да се мрдне, стомакот е тврд → 194/112 — source: `nhs-appendicitis`
- Болката почна по удар во стомакот → 194/112 — source: `nhs-stomach-ache`
- Тешко се буди, млитаво е или е многу бледо → 194/112 — source: `nhs-under5-urgent`

**Sources** (accessed 2026-10-07):

- `nhs-stomach-ache` — [NHS (Велика Британија) — Stomach ache](https://www.nhs.uk/symptoms/stomach-ache/)
- `nhs-appendicitis` — [NHS (Велика Британија) — Appendicitis](https://www.nhs.uk/conditions/appendicitis/)
- `nhs-testicle-pain` — [NHS (Велика Британија) — Testicle pain](https://www.nhs.uk/symptoms/testicle-pain/)
- `nhs-dv` — [NHS (Велика Британија) — Diarrhoea and vomiting](https://www.nhs.uk/symptoms/diarrhoea-and-vomiting/)
- `nhs-uti` — [NHS (Велика Британија) — Urinary tract infections (UTIs)](https://www.nhs.uk/conditions/urinary-tract-infections-utis/)
- `nhs-under5-urgent` — [NHS (Велика Британија) — When to get urgent medical help for babies and children under 5](https://www.nhs.uk/baby/health/when-to-get-urgent-medical-help-for-babies-and-children-under-5/)

**Rationale:** NHS stomach ache/appendicitis/testicular pain; intussusception classic triad as red flag (public NHS descriptions of bowel obstruction in babies).

**Populations not referenced in routing (internal notes):**

- child: Child flow.
- chronic: Referenced: diabetes + vomiting → same day (DKA risk).

**Where to go:** settings `emergency_department`, `pediatrics`, `pharmacy`, `self_care`; specialties `detska-hirurgija`, `pedijatrija`, `urgentna-medicina`.

**Open questions for the clinician:**

- Intussusception red-flag wording for lay parents — confirm.
- Pain scale is unreliable under ~7 years; should it be hidden for under-5s?

### Бебе што многу плаче (`crying-baby`)

Crying baby (0–12 months). Weak/high-pitched/continuous cry with illness signs, fever thresholds, bulging fontanelle, floppy, green vomit, not feeding → emergency/go now. Caregiver at risk of harming the baby → safety step + global crisis. Colic pattern in a well, feeding baby → self-care with soothing; parental exhaustion → paediatrician/GP.

**Red flags (asked first):**

- Дише многу брзо или тешко, стенка при дишење или стомачето/меѓу ребрата му се вовлекува → 194/112 — source: `nhs-under5-urgent`
- Кожата, усните или јазикот се сини, сиви, многу бледи или со дамки → 194/112 — source: `nhs-under5-urgent`
- Тешко се буди, не реагира или е млитаво → 194/112 — source: `nhs-under5-urgent`
- Слаб, писклив или непрекинат плач што е невообичаен за детето → 194/112 — source: `nhs-under5-urgent`
- Напад (грч) → 194/112 — source: `nhs-under5-urgent`
- Не мокрело 12 часа или повеќе (суви пелени) → 194/112 — source: `nhs-under5-urgent`
- Испакнато (издигнато) теме кај бебето → 194/112 — source: `nhs-meningitis`
- Повраќа зелено → 194/112 — source: `nhs-dv`
- Се плашите дека можете да му наштетите на бебето → crisis — source: `nhs-crying-baby`

**Sources** (accessed 2026-10-07):

- `nhs-crying-baby` — [NHS (Велика Британија) — Soothing a crying baby](https://www.nhs.uk/baby/caring-for-a-newborn/soothing-a-crying-baby/)
- `nhs-colic` — [NHS (Велика Британија) — Colic](https://www.nhs.uk/conditions/colic/)
- `nhs-under5-urgent` — [NHS (Велика Британија) — When to get urgent medical help for babies and children under 5](https://www.nhs.uk/baby/health/when-to-get-urgent-medical-help-for-babies-and-children-under-5/)
- `nhs-fever-children` — [NHS (Велика Британија) — Fever in children](https://www.nhs.uk/symptoms/fever-in-children/)
- `nhs-jaundice-babies` — [NHS (Велика Британија) — Jaundice in babies](https://www.nhs.uk/conditions/jaundice-in-babies/)
- `nhs-febrile-seizures` — [NHS (Велика Британија) — Febrile seizures](https://www.nhs.uk/conditions/febrile-seizures/)
- `nhs-meningitis` — [NHS (Велика Британија) — Meningitis](https://www.nhs.uk/conditions/meningitis/)
- `nhs-dv` — [NHS (Велика Британија) — Diarrhoea and vomiting](https://www.nhs.uk/symptoms/diarrhoea-and-vomiting/)

**Rationale:** NHS soothing a crying baby / colic / under-5 urgent signs / fever thresholds / newborn jaundice. Includes shaken-baby prevention wording and a caregiver-harm red flag to the global crisis outcome.

**Populations not referenced in routing (internal notes):**

- chronic: Babies with known conditions: clinician to decide whether to add a lower threshold.

**Where to go:** settings `emergency_department`, `on_call`, `pediatrics`, `self_care`; specialties `detska-hirurgija`, `opsta-medicina`, `pedijatrija`, `psihijatrija`, `urgentna-medicina`.

**Open questions for the clinician:**

- Is it right to route 'afraid I might harm the baby' to the global crisis outcome (194/112)? Alternative: same-day support. We chose the safer option.
- Патронажна сестра: confirm this is the right MK term and service to name.

### Повраќање или пролив кај дете (`vomiting-diarrhoea-child`)

Childhood vomiting/diarrhoea and dehydration. Green/bloody vomit, blood in stool with pain, drowsy, no urine 12h → emergency. Infants <3 m vomiting, dehydration signs, unable to keep fluids → same day. Vomiting >2 days / diarrhoea >7 days → paediatrician. NICE CG84 dehydration features (not reproduced).

**Red flags (asked first):**

- Кожата, усните или јазикот се сини, сиви, многу бледи или со дамки → 194/112 — source: `nhs-under5-urgent`
- Тешко се буди, не реагира или е млитаво → 194/112 — source: `nhs-under5-urgent`
- Напад (грч) → 194/112 — source: `nhs-under5-urgent`
- Не мокрело 12 часа или повеќе (суви пелени) → 194/112 — source: `nhs-under5-urgent`
- Повраќа зелена (жолчна) течност → 194/112 — source: `nhs-dv`
- Крв во повраќаното или столицата, или црна столица → 194/112 — source: `nhs-dv`
- Силна болка во стомакот што не попушта → 194/112 — source: `nhs-dv`

**Sources** (accessed 2026-10-07):

- `nhs-dv` — [NHS (Велика Британија) — Diarrhoea and vomiting](https://www.nhs.uk/symptoms/diarrhoea-and-vomiting/)
- `nhs-dehydration` — [NHS (Велика Британија) — Dehydration](https://www.nhs.uk/conditions/dehydration/)
- `nice-cg84` — [NICE (Велика Британија) — CG84 Diarrhoea and vomiting caused by gastroenteritis in under 5s](https://www.nice.org.uk/guidance/cg84)
- `nhs-under5-urgent` — [NHS (Велика Британија) — When to get urgent medical help for babies and children under 5](https://www.nhs.uk/baby/health/when-to-get-urgent-medical-help-for-babies-and-children-under-5/)
- `who-imci` — [Светска здравствена организација (WHO) — Integrated Management of Childhood Illness (IMCI) chart booklet](https://www.who.int/publications/i/item/9789241506823)
- `nhs-febrile-seizures` — [NHS (Велика Британија) — Febrile seizures](https://www.nhs.uk/conditions/febrile-seizures/)

**Rationale:** NHS D&V and dehydration; NICE CG84 (gastroenteritis in under 5s) dehydration/shock features; WHO IMCI (unable to drink). Bilious vomiting as red flag (malrotation/obstruction).

**Populations not referenced in routing (internal notes):**

- child: Child flow; ages 1–12 use the same rules; infants have extra rules.

**Where to go:** settings `emergency_department`, `on_call`, `pediatrics`, `self_care`; specialties `pedijatrija`, `urgentna-medicina`.

### Болка во увото кај дете (`ear-pain-child`)

Child earache. Swelling behind ear/protruding ear, stiff neck, drowsy, facial weakness → emergency. Infants <3 m with fever → fever flow rules (same day). Discharge, >3 days, <2 years both ears with fever, very unwell → 24–48h. Most → home care, improve in 3 days.

**Red flags (asked first):**

- Оток, црвенило или болка зад увото, или увото е одбиено нанапред → 194/112 — source: `nhs-ear-infections`
- Вкочанет врат, многу пречи светлината, тешко се буди или е збунето → 194/112 — source: `nhs-meningitis`
- Опуштено лице на едната страна → 194/112 — source: `nhs-earache`

**Sources** (accessed 2026-10-07):

- `nhs-earache` — [NHS (Велика Британија) — Earache](https://www.nhs.uk/symptoms/earache/)
- `nhs-ear-infections` — [NHS (Велика Британија) — Ear infections](https://www.nhs.uk/conditions/ear-infections/)
- `nhs-under5-urgent` — [NHS (Велика Британија) — When to get urgent medical help for babies and children under 5](https://www.nhs.uk/baby/health/when-to-get-urgent-medical-help-for-babies-and-children-under-5/)
- `nhs-meningitis` — [NHS (Велика Британија) — Meningitis](https://www.nhs.uk/conditions/meningitis/)

**Rationale:** NHS earache/ear infections in children; NICE NG91 criteria (under 2 bilateral, otorrhoea) are well known but not cited directly — clinician to confirm.

**Populations not referenced in routing (internal notes):**

- child: Child flow.
- infant_3_12m: Referenced via under-2 bilateral rule.

**Where to go:** settings `emergency_department`, `on_call`, `pediatrics`, `self_care`; specialties `otorinolaringologija`, `pedijatrija`, `urgentna-medicina`.

### Ненадејна збунетост кај постаро лице (`confusion-older`)

New or worsening confusion (delirium). Stroke signs, unresponsive, seizure, sepsis signs, head injury, low sugar in diabetes not improving → emergency. Sudden confusion over hours/days → same day (delirium is a medical emergency in public guidance). Gradual memory problems over months → GP.

**Red flags (asked first):**

- Опуштено лице, слабост на рака или нога, нејасен говор → 194/112 — source: `nhs-stroke`
- Не реагира или многу тешко се буди → 194/112 — source: `nhs-confusion`
- Напад (грч) → 194/112 — source: `nhs-confusion`
- Висока или многу ниска температура со забрзано дишење, дамки на кожата или не мокри → 194/112 — source: `nhs-sepsis`
- Неодамна удри со главата → 194/112 — source: `nhs-head-injury`
- Има дијабетес, шеќерот е низок и не се подобрува по внесување шеќер → 194/112 — source: `nhs-hypo`

**Sources** (accessed 2026-10-07):

- `nhs-confusion` — [NHS (Велика Британија) — Confusion](https://www.nhs.uk/symptoms/confusion/)
- `nice-cg103` — [NICE (Велика Британија) — CG103 Delirium: prevention, diagnosis and management](https://www.nice.org.uk/guidance/cg103)
- `nhs-stroke` — [NHS (Велика Британија) — Stroke — symptoms](https://www.nhs.uk/conditions/stroke/symptoms/)
- `nhs-sepsis` — [NHS (Велика Британија) — Sepsis](https://www.nhs.uk/conditions/sepsis/)
- `nhs-hypo` — [NHS (Велика Британија) — Low blood sugar (hypoglycaemia)](https://www.nhs.uk/conditions/low-blood-sugar-hypoglycaemia/)
- `nhs-dementia-symptoms` — [NHS (Велика Британија) — Dementia — symptoms and diagnosis](https://www.nhs.uk/conditions/dementia/symptoms-and-diagnosis/)
- `nhs-uti` — [NHS (Велика Британија) — Urinary tract infections (UTIs)](https://www.nhs.uk/conditions/urinary-tract-infections-utis/)
- `nhs-head-injury` — [NHS (Велика Британија) — Head injury and concussion](https://www.nhs.uk/conditions/head-injury-and-concussion/)

**Rationale:** NHS confusion; NICE CG103 delirium (acute onset/fluctuating course as key features). Sudden confusion is never routed below same day.

**Populations not referenced in routing (internal notes):**

- child: Teenagers with sudden confusion are covered by red flags/same-day routing; no lower age-specific threshold.
- older_adult: Written for 65+; also applies to younger adults (any sudden confusion → same day).
- pregnancy: Confusion in pregnancy/postpartum routes to same day like everyone; postpartum psychosis is also in mental-health.
- chronic: Diabetes hypoglycaemia is a red flag item; dementia worsening is asked directly.

**Where to go:** settings `emergency_department`, `gp`; specialties `interna-medicina`, `nevrologija`, `opsta-medicina`, `psihijatrija`, `semejna-medicina`, `urgentna-medicina`.

### Пад кај постаро лице (`falls-older`)

Falls in older adults. Cannot get up / suspected hip fracture, head injury on anticoagulants, blackout before fall, chest pain/palpitations, stroke signs, long lie → emergency/same day. Injury or new confusion → same day. Recurrent falls (≥2 in 12 months), fear of falling → GP for a falls assessment (NICE NG249). Uninjured single trip → self-care with prevention tips.

**Red flags (asked first):**

- Не може да стане или да стапне на ногата, или ногата е скусена и свртена нанадвор → 194/112 — source: `nhs-falls`
- Удри со главата, а пие лекови за разредување на крвта → 194/112 — source: `nhs-head-injury`
- По ударот во главата: поспаност, повраќање, збунетост или губење свест → 194/112 — source: `nhs-head-injury`
- Опуштено лице, слабост на рака или нога, нејасен говор → 194/112 — source: `nhs-stroke`
- Болка во градите или силно чукање на срцето пред или по падот → 194/112 — source: `nhs-fainting`
- Лежеше на подот повеќе од еден час пред да биде пронајден/а → 194/112 — source: `nhs-falls`

**Sources** (accessed 2026-10-07):

- `nhs-falls` — [NHS (Велика Британија) — Falls](https://www.nhs.uk/conditions/falls/)
- `nice-ng249` — [NICE (Велика Британија) — NG249 Falls: assessment and prevention in older people](https://www.nice.org.uk/guidance/ng249)
- `nhs-head-injury` — [NHS (Велика Британија) — Head injury and concussion](https://www.nhs.uk/conditions/head-injury-and-concussion/)
- `nhs-broken-leg` — [NHS (Велика Британија) — Broken leg](https://www.nhs.uk/conditions/broken-leg/)
- `nhs-fainting` — [NHS (Велика Британија) — Fainting](https://www.nhs.uk/symptoms/fainting/)
- `nhs-stroke` — [NHS (Велика Британија) — Stroke — symptoms](https://www.nhs.uk/conditions/stroke/symptoms/)

**Rationale:** NHS falls; NICE NG249 (2025) falls risk assessment triggers (≥2 falls/12 months, fear of falling, injury); head injury with anticoagulation; syncope.

**Populations not referenced in routing (internal notes):**

- older_adult: This flow is written for adults 65+ and the questions assume them.
- pregnancy: Falls in pregnancy with abdominal pain/bleeding are covered by the pregnancy-concerns flow.
- chronic: Anticoagulation is a red flag item; other conditions do not change routing.

**Where to go:** settings `emergency_department`, `gp`, `self_care`; specialties `fizikalna-medicina`, `interna-medicina`, `nevrologija`, `opsta-medicina`, `ortopedija`, `semejna-medicina`, `traumatologija`, `urgentna-medicina`.

### Напад на астма (`asthma-attack`)

Asthma attack (children ≥1 and adults). Too breathless to speak/eat, blue lips, drowsy/exhausted, reliever not helping per plan, peak flow <50% best with no response → 194. Partial response → same day. Resolved attack → see GP/paediatrician within 2 days (NHS). Poor control (reliever >3x/week, night waking) → GP.

**Red flags (asked first):**

- Толку тешко дише што не може да зборува, јаде или спие → 194/112 — source: `nhs-asthma`
- Сини или сиви усни, поспаност, збунетост или исцрпеност → 194/112 — source: `nhs-asthma`
- Пумпичката (инхалаторот) за олеснување не помага ни по максималниот број вдишувања според вашиот план → 194/112 — source: `nhs-asthma`
- Тешко дише, а нема пумпичка за олеснување при рака → 194/112 — source: `nhs-asthma`

**Sources** (accessed 2026-10-07):

- `nhs-asthma` — [NHS (Велика Британија) — Asthma (incl. asthma attacks)](https://www.nhs.uk/conditions/asthma/)
- `nhs-sob` — [NHS (Велика Британија) — Shortness of breath](https://www.nhs.uk/symptoms/shortness-of-breath/)

**Rationale:** NHS asthma attack (fetched 2026-10-07): sit upright, reliever per plan, call 999 if worse/no improvement/no inhaler, see GP within 2 days after an attack. Puff counts deliberately not reproduced (user follows own plan). Peak-flow bands (<50%, 50–75%) are widely published public thresholds.

**Populations not referenced in routing (internal notes):**

- child: Referenced (child_1_4 lower threshold); school-age children use the same rules with a parent.
- older_adult: Same thresholds; COPD in older adults uses shortness-of-breath.
- chronic: Flow is for people with asthma; prior severe attacks are asked directly.

**Where to go:** settings `emergency_department`, `gp`, `on_call`; specialties `opsta-medicina`, `pedijatrija`, `pulmologija`, `semejna-medicina`, `urgentna-medicina`.

**Open questions for the clinician:**

- Should the 'up to 10 puffs' NHS instruction be shown, or is 'according to your asthma plan' better (current)?
- Peak-flow thresholds: confirm <50% with no response → 194.

### Дијабетес: низок или висок шеќер (`diabetes-blood-sugar`)

Diabetes high/low blood glucose (children and adults). Unconscious/unable to swallow, seizure, hypo not improving after two treatments with drowsiness, DKA signs (vomiting, abdominal pain, deep breathing, ketones ≥3) → 194. Hypo treated and recovered → self-care; ketones 1.5–2.9, vomiting with type 1, persistent high glucose → same day; repeated hypos or persistently high → GP/endocrinology.

**Red flags (asked first):**

- Не реагира, не може безбедно да голта или е многу збунет/а → 194/112 — source: `nhs-hypo`
- Напад (грч) → 194/112 — source: `nhs-hypo`
- Повраќање, болка во стомакот, длабоко и брзо дишење, мирис на овошје (ацетон) од устата и поспаност → 194/112 — source: `nhs-dka`

**Sources** (accessed 2026-10-07):

- `nhs-hypo` — [NHS (Велика Британија) — Low blood sugar (hypoglycaemia)](https://www.nhs.uk/conditions/low-blood-sugar-hypoglycaemia/)
- `nhs-hyper` — [NHS (Велика Британија) — High blood sugar (hyperglycaemia)](https://www.nhs.uk/conditions/high-blood-sugar-hyperglycaemia/)
- `nhs-dka` — [NHS (Велика Британија) — Diabetic ketoacidosis](https://www.nhs.uk/conditions/diabetic-ketoacidosis/)

**Rationale:** NHS hypoglycaemia (fetched 2026-10-07: recheck after 10–15 min, <4 mmol/L repeat), hyperglycaemia and DKA (ketone bands 0.6/1.5/3.0 from public NHS diabetes pages). No insulin dose advice.

**Populations not referenced in routing (internal notes):**

- infant_3_12m: Referenced via child high-sugar rule.
- child: Referenced: children with high sugar → same day.
- pregnancy: Referenced: high sugar in pregnancy → same day.
- older_adult: Same thresholds; hypoglycaemia in older adults may present as confusion (also in confusion-older).
- chronic: The flow is for people with diabetes.

**Where to go:** settings `emergency_department`, `gp`, `on_call`, `self_care`, `specialist`; specialties `endokrinologija`, `interna-medicina`, `opsta-medicina`, `pedijatrija`, `semejna-medicina`, `urgentna-medicina`.

**Open questions for the clinician:**

- Ketone bands (0.6 / 1.5 / 3.0 mmol/L) are from public NHS advice; confirm for MK patients.
- Glucose ≥20 → same day, ≥15 → GP: confirm thresholds.
- Children with type 1: should all high-sugar contacts go to same-day paediatric endocrinology?

## Cross-cutting open questions

- Lay numeric thresholds used across flows (temperature by age, SpO2 bands, pulse ≥150/≤40, BP 180/120 and 140/90 in pregnancy, glucose ≥20, ketone bands, peak flow <50 %) — please confirm each.
- Level wording: `urgent_same_day` is used both for „денес“ and for „сега, без чекање“ (e.g. infants with fever, reduced fetal movements). Should the latter be emergency_now?
- Teenagers (13–17) follow adult flows; children 1–12 follow child flows. Is 13 the right cut-off for e.g. abdominal pain and head injury?
- The engine asks chronic conditions once (`demo.conditions`); flows only add flow-specific risks (anticoagulants, cancer). Is anything missing (e.g. sickle cell, splenectomy)?
- Safeguarding: none of the child flows mention non-accidental injury. Should a neutral note be added for unexplained injuries in non-mobile babies?
- MK service names: „избран лекар“, „дежурна служба“, „итна амбуланта“, „ургентен центар“, „породилиште“, „патронажна сестра“, „УНГ“ — confirm they match how patients find these services.

## Claims not verified in this session

- Official North Macedonian crisis helplines — none found on official sites (see above); 194/112 + nearest emergency department used.
- Emergency-contraception availability without prescription in MK pharmacies — wording avoids the claim; needs MK confirmation (МАЛМЕД / clinician).
- MK pathway for animal bites (rabies post-exposure care) and for sexual violence (designated centres) — content only names same-day doctor / emergency services and police 192/112.
- That psychiatric departments accept walk-in emergencies — not claimed; content says „психијатриско одделение или итната служба на најблиската болница“.
- NHS pages were read through an automated fetch summariser for the key thresholds; other pages were cited from the author's knowledge of their public content and checked only for URL availability.
- NICE NG91 (otitis media) criteria are mentioned in the ear-pain-child rationale but not cited as a source.
