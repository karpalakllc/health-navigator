# Zdravje360: legal research memo (North Macedonia)

Date: 2026-10-06. Author: engineering research (AI-assisted), for the owner and
for counsel.

**This memo is not legal advice.** It is desk research from public sources by
non-lawyers, and it is meant to speed up review. A lawyer admitted in North
Macedonia must review the memo and the three pages built from it
(`apps/web/src/content/legal/{privacy,terms,disclaimer}.tsx`) before public
launch, and before any paid placement is sold. Where we could not read the
primary text ourselves, the item is marked **UNVERIFIED**.

The product facts used here come from the code and
[`docs/data-inventory.md`](../data-inventory.md) as of 2026-10-06.

---

## 0. Top risks and recommendations at a glance

| # | Risk | Severity | Recommendation |
|---|---|---|---|
| R1 | **Prior AZLP approval for health data (ЗЗЛП чл. 84).** Processing "data concerning health" needs the Agency's prior approval, even with explicit consent. The forum (health questions), reviews that describe treatment, and the anonymous guidance answers may all count. | High | Get counsel's view first. Then either (a) apply to AZLP for approval, or (b) document why each flow is outside чл. 84 (guidance is anonymous; forum content is made public by the data subject, чл. 13(2) т. 5). Do this before launch. |
| R2 | **Paid „Спонзорирано“ doctor placement.** It meets the statutory definition of advertising health services (ЗЗЗ чл. 2 т. 28). It is risky under чл. 277 (misleading or comparative advertising). The Лекарска комора code (чл. 18, 85, 87) forbids doctors' self-promotion, so paying doctors face disciplinary risk. | Medium–high | Do not sell doctor sponsorship until counsel and, informally, the Комора have answered. If it is sold, follow the neutrality rules in §5.4. Replace the current hover text that ties sponsorship to good reviews (done in `featured-disclosure.md`; WP2 implements it). |
| R3 | **„Истакнат“ ranks first in lists** (directory lists sort `is_featured` first; search results are ordered by relevance, with featured only as a later tie-break). Unpaid editorial ranking is lawful, but it must not be bought or look like a quality verdict (consumer law чл. 71(1) т. 11–12, чл. 75(5)). | Medium | Write and publish objective featuring criteria. Keep a log of who was featured and why. Keep a hard rule that featuring is never linked to payment or any commercial relationship. The toggletip and terms explain this. |
| R4 | **Defamation via reviews:** factual allegations of crimes (bribes, malpractice). | Medium | Use the content policy in §4.4: opinion yes, factual accusation of a crime no. Pre-moderation (already in place) plus the report button and staff-posted official reply. |
| R5 | **Notice-and-takedown speed.** The hosting safe harbour (Закон за електронска трговија чл. 17) requires acting "веднаш / брзо" once notified. The defamation law gives the editor of an electronic publication a defence only if offending information is removed **within 24 hours** of becoming aware (ЗГОНК чл. 11(2)). Pre-moderation makes "unaware" hard to argue. | Medium–high | Review every report within 24 hours (the terms now state this as the goal; staff are emailed about new reports every 10 minutes) and remove content that breaks the rules or the law. The product has no interim hide: a report is resolved keep or remove. Log every notice and decision for at least a year (reports are never deleted). |
| R6 | **Transfers to processors outside MK** (Sentry in the US, the email provider, hosting). Transfers to EU, EEA or NATO countries are outside the transfer chapter, but each must be **notified to AZLP 15 days in advance** (ЗЗЛП чл. 48(3) plus the transfer rulebook). | Medium | Choose EU regions where possible (e.g. Sentry EU). Sign a DPA with each processor (чл. 32). File the чл. 48(3) notifications. |
| R7 | **Reviews and posts are kept indefinitely; rejected content is kept.** Self-service deletion (anonymisation) and export now exist under Account → „Ваши податоци“. Deadlines are short: erasure within 30 days (чл. 21), rectification within 15 days (чл. 20). | Medium | Handle other requests by email within those deadlines (the privacy page says so). Set a retention period for rejected content. |
| R8 | **DPO, records of processing, DPIA, high-risk notice.** Health-related content probably triggers the DPIA list (Сл. весник 122/20, т. 3) and removes the under-50-employees exemption from records of processing (чл. 34(5)). A DPO is arguably mandatory (чл. 41(1)(в)). | Medium | Appoint a DPO (an external contract is allowed, чл. 41(6)), notify AZLP, write the records of processing and a DPIA before launch, and check whether a чл. 71 high-risk notice is needed. |
| R9 | **Operator identification:** Закон за електронска трговија чл. 7 requires the provider's name, address, registration and e-mail to be easy to find. | Low (easy) | Fill the placeholders in privacy and terms (list in §8). |
| R10 | **No terms acceptance or age check at sign-up.** The children's consent age is 14 (ЗЗЛП чл. 12). | Done | Registration has a required "I accept the terms and confirm I am at least 14" checkbox, stored with the terms version and time. Terms and privacy state the minimum age of 14 (the legal floor). |

**What must never be done**

- Never sell, or link to payment, any of: organic ranking, „Истакнат“, review
  publication or rejection, the rating, or the speed or outcome of complaints.
- Never show competitors' ads or paid profiles on a non-paying doctor's page,
  and never visually downgrade non-payers. This is what lost Jameda its
  neutral status (BGH VI ZR 30/17).
- Never sponsor or feature prescription medicines (Закон за лековите чл. 95).
  Sponsoring OTC medicines or devices requires prior МАЛМЕД approval (чл. 94).
- Never publish health workers' private data (home address, private phone,
  ЕМБГ), even when it can be found elsewhere.
- Never claim reviews are "verified" unless a verification step really exists
  (consumer law чл. 71(1) т. 26–27).
- Never let a staff-posted official reply reveal a patient's health data or
  confirm that someone was a patient (professional secrecy). Moderate replies
  like reviews.
- Never link guidance sessions to accounts or add free-text symptoms without a
  new legal review.

---

## 1. Sources actually read

| Instrument | Gazette | Where we read it |
|---|---|---|
| Закон за заштита на личните податоци (ЗЗЛП) | Сл. весник на РСМ 42/2020, 294/2021, 101/2025 (consolidated) | Consolidated text: https://gazibaba.gov.mk/wp-content/uploads/2026/08/zakon-za-zashtita-na-licnite-podatoci.pdf (a municipal copy of the official consolidated text; AZLP lists 42/20 and 294/21 at https://azlp.mk/en/pdpa/regulations-and-documents/laws/) |
| Листа на видови операции што бараат проценка на влијание (DPIA list) | Сл. весник 122/2020 | https://azlp.mk/wp-content/uploads/2022/11/f5e96b1d7da548eba2f30797d555a3c8.pdf |
| Правилник за пренос на лични податоци | Сл. весник 122/2020 | https://azlp.mk/wp-content/uploads/2022/11/052e8e10cf2e4bd48e7827e7bc85fb62.pdf |
| Закон за електронските комуникации | Сл. весник на РСМ 135/2025 (applies from 1 June 2026) | https://portal.mdt.gov.mk/post-body-files/zakoni-mdt-file-dXJ8.pdf |
| Закон за здравствената заштита (ЗЗЗ) | 43/2012 … 30/2024 (consolidated); 170/2024 does not touch the articles cited | https://fzo.org.mk/sites/default/files/fzo/legislativa/zakon-zz/zakon-zdravstvena-zastita-precisten-30-2024.pdf |
| Кодекс на медицинската деонтологија (Лекарска комора) | adopted under ЗЗЗ чл. 261(5) | https://lkm.org.mk/upload/documents/Kodeks%20na%20medicinska%20deontologija.pdf |
| Закон за лековите и медицинските средства | 106/2007 (original text read; amendments UNVERIFIED) | https://lekovi.zdravstvo.gov.mk (original text); amendment list per the CMS expert guide |
| Закон за заштита на потрошувачите | Сл. весник на РСМ 236/2022 | text via ener.gov.mk (final text of the law as published) |
| Закон за електронска трговија | 133/2007 (original text read; amendments UNVERIFIED) | https://opm.org.mk/wp-content/uploads/2010/10/Zakon%20za%20elektronska%20trgovija.pdf |
| Лекарска комора: list of doctors with valid licences | — | https://lkm.org.mk/mk/record/121/962/lista-na-doktori-so-vazhechki-licenci |
| BGH press release 34/2018 (Jameda, VI ZR 30/17) | — | https://www.bundesgerichtshof.de/SharedDocs/Pressemitteilungen/DE/2018/2018034.html |

**Not verified:** which articles the amendments 294/2021 and 101/2025 to the
ЗЗЛП changed, including whether "NATO" in чл. 48(2) came from 101/2025; the
2025 amendment 128/25 to the transfer rulebook; amendments to the
e-commerce and medicines laws after the original texts; the pharmacy and dental
chambers' codes (not found online). Whether any AZLP adequacy decision exists.
No AZLP opinion on rating sites or doctor directories was found.

---

## 2. Personal data: Закон за заштита на личните податоци (42/2020)

The law closely follows the GDPR, but **its article numbers differ**. The table
maps the GDPR concepts to the MK articles we read.

| Topic | ЗЗЛП article | Note |
|---|---|---|
| Principles; accountability | чл. 9 | |
| Lawful bases | чл. 10(1) | Indent 6 is legitimate interest: „освен кога таквите интереси не преовладуваат над интересите или основните права и слободи на субјектот“. Not available to public authorities (чл. 10(2)). |
| Consent | чл. 4(1) т. 11, чл. 11 | |
| Children | чл. 12 | Information-society services: lawful if the child is **at least 14**; below 14, the parent consents, and the controller must make a reasonable effort to verify (чл. 12(2)). |
| Special categories, health | чл. 4(1) т. 13, т. 16; чл. 13 | Prohibited unless чл. 13(2) applies: т. 1 explicit consent, т. 5 data „очигледно објавени јавно од субјектот“, т. 6 legal claims, т. 8 health care, т. 9 public health. |
| **Prior approval for health data (MK-specific)** | **чл. 84** | „Само по претходно добиено одобрение од страна на Агенцијата се врши обработка на … податоци што се однесуваат на здравјето“. Applies **even with explicit consent** (чл. 84(2)). Not needed where a law with safeguards provides for the processing (чл. 84(3)). AZLP decides within 90 days (чл. 84(4)). Breach is a 4%-tier offence (чл. 111(1) т. 26). |
| Information duty (data from the subject) | чл. 17 | GDPR Art. 13 equivalent. |
| Information duty (data not from the subject) | чл. 18 | Must state the source and „дали податоците се од јавно достапни извори“ (чл. 18(2) т. 6), within one month (чл. 18(3)). Exemption: „невозможно или бара несразмерно големи напори“ (чл. 18(5) т. 2). The controller must then take measures, including making the information publicly available. |
| Handling requests | чл. 16 | Respond within **one month**, extendable by two (чл. 16(3)). A refusal must give reasons and mention the right to complain to AZLP and to go to court (чл. 16(4)). Manifestly unfounded or excessive requests may be refused (чл. 16(5)). |
| Access | чл. 19 | A copy must not adversely affect others' rights (чл. 19(4)). Relevant when a doctor asks who wrote a review. |
| Rectification | **чл. 20: within 15 days** | MK-specific deadline. |
| Erasure | **чл. 21: within 30 days** | Grounds in чл. 21(1). Refusal grounds in чл. 21(3), first among them „за остварувањето на правото на слобода на изразување и информирање“. |
| Restriction, notification, portability | чл. 22, 23, 24 | |
| Objection | чл. 25 | Against legitimate-interest processing. The controller must stop „освен ако докаже дека постојат релевантни легитимни интереси … кои преовладуваат“. The subject must be told of the right at first communication (чл. 25(4)). |
| Direct marketing | чл. 96 | Only with prior explicit consent (MK-specific). |
| Freedom of expression | чл. 81 | Derogations for journalistic and similar purposes; balancing criteria in чл. 81(4) (nature of the data, contribution to public debate, how well known the person is, and so on). Whether a review platform is "journalistic" is untested. |
| Processors | чл. 32 | Written contract with the mandatory terms (чл. 32(3), (8)). AZLP standard clauses: Сл. весник 280/21 (titles only, not read). |
| Records of processing | чл. 34 | Exemption for under 50 employees does **not** apply when special categories are processed (чл. 34(5)). |
| Breach | чл. 37, 38 | Notify AZLP within 72 hours; tell data subjects when the risk is high. |
| DPIA | чл. 39 + list in Сл. весник 122/20 | List т. 3: processing of special categories, health data expressly included; т. 4: large-scale special categories; т. 8: new technologies that analyse health (relevant to guidance). |
| Prior consultation | чл. 40 | MK-specific чл. 40(5)–(6). How far they reach for a private platform is UNVERIFIED. |
| DPO | чл. 41 | Mandatory under чл. 41(1)(в) where core activities involve large-scale special categories. MK-specific qualifications (чл. 41(5)). Can be on a service contract (чл. 41(6)). Contacts must be published and notified (чл. 41(7)). |
| Transfers | **чл. 48** | „Одредбите од оваа глава нема да се применуваат за пренос … во земја членка на Европската Унија, во земја членка на НАТО или во Европскиот економски простор“ (чл. 48(2)). Such transfers **must be notified to the Agency** (чл. 48(3)). The rulebook (122/20, чл. 2) requires notification 15 days before the transfer, on Образец бр. 1. Other countries: чл. 49–53 (AZLP adequacy, SCCs, derogations). |
| High-risk notice | чл. 71 | MK-specific notification of high-risk processing. Rulebook 122/20 not read. |
| Fines | чл. 110 (up to 2%), чл. 111 (up to 4%) | Imposed by AZLP's own misdemeanour commission (чл. 114). |
| Complaints | чл. 97 (to AZLP), чл. 99 (lawsuit), чл. 101 (damages) | |

**Supervisory authority:** Агенција за заштита на личните податоци, бул.
„Гоце Делчев“ бр. 18, 1000 Скопје, info@privacy.mk, https://azlp.mk (from
https://azlp.mk/en/contact/, read 2026-10-06).

### 2.1 Listing health professionals without their consent

- **Basis:** legitimate interest, чл. 10(1) indent 6. Patients have an
  interest in finding health workers and knowing where they practise. The
  platform has an interest in running a complete directory. Professionals have
  a reduced expectation of privacy in their professional role. The data is
  limited to professional facts, which mostly match what ЗЗЗ чл. 279 lets an
  institution publish anyway (name, address, activity, specialty,
  qualifications of health workers, working hours).
- **AZLP's FAQ** says publicly available data still needs a lawful basis under
  чл. 10. "It was public" is not enough on its own. **Write and keep a
  documented balancing test.**
- **Sources:** two public official sources are imported (runbook:
  [`docs/data-import.md`](../data-import.md)):
  - **ФЗОМ „Шифрарник на лекари“**: two XML files the Fund publishes and
    regenerates daily, listing doctors with a ФЗО contract (name, specialty,
    institution, work unit, address, town, facsimile number, contract
    dates). It is the seed for who works where.
  - **Лекарска комора list of doctors with valid licences**: PDFs (name,
    surname, licence number, specialty, expiry; updated every four months,
    last on 2.7.2026). Used to verify the licence.

  The register of health institutions is public by law (ЗЗЗ чл. 67). The
  register of health workers kept by the Институт за јавно здравје (ЗЗЗ
  чл. 116) holds ЕМБГ and addresses and is **not** public. Never use it as a
  source. Private clinic websites and aggregators are not copied (database
  maker's right, ЗАПСП чл. 118–128; photos and bios are copyrighted).
- **Information duty (чл. 18):** the privacy page names both sources, says
  they are publicly available (чл. 18(2) т. 6) and lists what is not taken.
  Writing to every listed doctor is arguably
  disproportionate effort (чл. 18(5) т. 2). The fallback is a public notice,
  which is the "За здравствените работници во именикот" section of the privacy
  page. It names the sources, the basis and the rights. **Recommendation:**
  when a profile is claimed, edited at a professional's request, or first
  contacted, send the чл. 18 information within one month.
- **Objection and erasure:** objection is under чл. 25(1), erasure under
  чл. 21. The platform can refuse where its interests and freedom of
  information prevail (чл. 21(3)(а), чл. 81(4) criteria). It must answer
  within one month with reasons and mention AZLP (чл. 16(4)). Jameda (§6)
  supports keeping profiles **only while the platform stays a neutral
  intermediary**. A paid model that disadvantages non-payers weakens the case
  for refusing deletion. **Process:** (1) verify identity; (2) correct facts
  within 15 days (чл. 20); (3) for objections, record the balancing, decide
  within 30 days, give reasons; (4) remove private data at once; (5) always
  remove when the person no longer practises, or the listing is wrong.
  **Implemented (W6-C):** every profile has „Пријави грешка во профилот“
  (anyone; 15-day due date) and doctor profiles „Барање за приговор /
  отстранување“ (the listed doctor, with a contact for verification; 30-day
  due date). Both land in one admin queue with the due date fixed at
  receipt; closing needs a staff note and is audit-logged. Replies with
  reasons (and the АЗЛП / court mention for a refusal) are sent by staff.

#### 2.1.1 Balancing test for the source imports (recorded 2026-10-06)

Legitimate interest (чл. 10(1) indent 6) needs a purpose, necessity and a
balancing of interests. This is the written record AZLP's FAQ asks for.
Counsel should confirm it.

1. **Purpose.** A complete, neutral public directory so patients can find
   which doctor works where and in which specialty, and so reviews attach to
   the right person. Without a complete list, the directory favours whoever
   signs up, which harms neutrality (§6).
2. **Necessity and minimisation.** Only professional facts patients need are
   taken: name, specialty, workplace, address, town; and whether the licence
   is valid.
   - **Not imported:** pharmacists (not doctors; a separate vertical), the
     team nurse's name (`ClenNaTim`: outside our purpose), absence reasons
     (`PricinaOtsustvo` / `StatusValidnostID`: can reveal sick or maternity
     leave, i.e. health data), substitution links (`RedovnaZamena`), ЕМБГ,
     any private phone or e-mail.
   - **Internal only:** the ФЗО facsimile number (printed on prescriptions;
     a professional identifier) and the licence number and expiry. They are
     used to match records and avoid duplicates, never displayed, never in
     the public API or exports. The public profile may show only that the
     licence is valid.
   - Raw downloads are kept privately, the newest few per source, for audit
     of what was imported.
3. **The professionals' interests.** Professional role, reduced expectation of
   privacy in it; the same facts ЗЗЗ чл. 279 lets institutions publish
   themselves; both sources are published by public bodies for the public.
   Risks: errors (wrong workplace, a doctor who left), and being listed
   against one's will. "Public" alone is not a basis; the minimisation above
   and the safeguards below are what tip the balance.
4. **Safeguards.** New profiles from an import stay hidden until staff
   review and publish them; an import never deletes or unpublishes, and a
   doctor missing from the source twice goes to staff review; editor locks
   survive re-imports; correction (15 days) and objection (30 days, reasons,
   АЗЛП) on every profile; the platform stays neutral: no paid ranking or
   paid review handling (§5.4, §6), which is what makes refusing a removal
   defensible.
5. **Outcome.** The interest prevails for the professional facts listed,
   with these safeguards. It does not for the excluded fields. Revisit when
   a new source or field is added.
6. **Reuse of the sources.** ФЗОМ's files are published for public use
   (Закон за користење на податоците од јавниот сектор, 27/2014, as
   amended — reuse conditions not verified); the Комора's list is published
   by the holder of the public licensing power. Whether either can assert the
   database maker's right against reuse is untested in MK; ask both in
   writing (FOI drafts in the source research) and name them as sources on
   the site (done on `/transparency` and the privacy page).

### 2.2 Health data in reviews, the forum and guidance

- **Guidance:** anonymous by design. No account link, structured options only,
  no IP stored, deleted after 90 days. Counsel should confirm whether these
  rows are personal data at all. The session can be continued only by the
  holder of the per-session secret in that browser tab. If they are
  personal data, they are health data, and **чл. 84 is the open question**.
- **Forum and reviews about oneself:** the author makes the data public
  (чл. 13(2) т. 5). Whether that also lifts the чл. 84 prior-approval duty is
  **not addressed by the text and is unresolved** (R1).
- **Third parties' health data** (another patient, a family member named in a
  review): no exemption fits. **Policy: reject or remove.** The terms say so.
- **Official replies by doctors:** a doctor revealing a patient's data in a
  reply would breach professional secrecy and data protection. The terms
  forbid it, and staff must check it before publishing.

### 2.3 Cookies and device storage

- **Law:** Закон за електронските комуникации, Сл. весник на РСМ 135/2025.
  It applies from 1 June 2026 (чл. 228) and replaces the old law (чл. 227).
  **Чл. 198(5):** storing or reading information on the terminal needs consent
  after clear information, **except** where it is „неопходно заради
  обезбедување на услуга на информатичкото општество која е изречно
  побарана од корисникот“. AZLP's FAQ says the same (it still cites the old
  чл. 168). No standalone AZLP cookie guideline was found.
- **What the product uses:**
  - `zdravje_api_token`: httpOnly login cookie, up to 30 days. Strictly necessary.
  - `zdravje_password_reset`: httpOnly, 1 hour. Strictly necessary.
  - `sessionStorage` `guidance_session`: strictly necessary for the tool the user started.
  - `localStorage` `z360:recently-viewed:v1`: up to 8 profiles, from branch `feat/home-sections`. **Borderline.** It is a convenience the visitor did not explicitly ask for. It is kept only on the device and never sent to us, and it can be cleared („Исчисти“). We disclose it plainly. Counsel should confirm it does not need consent; the safe alternative is to start recording only after the visitor turns the feature on.
  - Plausible is cookieless.
- So: **no consent banner** is needed for the current set, if counsel agrees
  on recently viewed.

### 2.4 Processors and transfers

| Processor | Location | Status |
|---|---|---|
| Hosting / object storage | TBD (placeholder) | Pick an MK or EU location. DPA required. |
| Sentry | US company; EU data region available | NATO country, so чл. 48(2)–(3) applies: notify AZLP. Prefer the EU region. Sign the DPA. |
| Plausible | EU | Cookieless; receives IP and user agent transiently. DPA. Notify (EU). |
| Mail (SMTP) provider | TBD | DPA; notify if it is outside MK. |

AZLP's FAQ still advises Schrems-style supplementary measures for the US. That
answer seems to predate the NATO exemption. Be conservative.

### 2.5 Retention and gaps (from the code)

- Guidance: 90 days. Activity events: 180 days. Search term counts: 365 days.
  All are purged on a schedule. Expired tokens are pruned daily (24 h grace,
  `routes/console.php`). Password-reset rows are replaced, not pruned.
- Reviews and posts, including **rejected ones**, are kept indefinitely.
  **Recommendation:** set a period, e.g. 12 months for rejected content.
  `failed_jobs` is pruned after 30 days (`queue:prune-failed --hours=720`).
- Self-service deletion and export are built (Account → „Ваши податоци“).
  Deletion anonymises the account in place (`restrict` foreign keys keep the
  row): published reviews and posts stay under „Избришан корисник“, pending
  ones are withdrawn, report notes are cleared. Other requests are handled by
  email (the privacy page says so).

---

## 3. Information duties of the operator

- **Закон за електронска трговија (133/2007) чл. 7(1):** the provider must
  make „лесно, директно и постојано достапни“ its name or company name, seat
  or address, contact details including e-mail, the Central Register entry,
  and its VAT number if VAT-registered. A free service could be argued to fall
  outside "за надомест" (чл. 3(1)), but selling sponsorship brings it in.
  **Comply anyway.** The pages carry placeholders.
- **Чл. 8:** commercial communication must be „јасно да се идентификува како
  таква“, and the advertiser must be identifiable. This applies directly to
  „Спонзорирано“.

---

## 4. Liability for user content and defamation

### 4.1 Закон за електронска трговија (133/2007, 17/2011; later amendments UNVERIFIED)

Read in the 2007 text; the 2010 proposal (which became 17/2011) did not touch
чл. 15–20.

- **чл. 17 „Складирање“ (hosting):** the provider „не одговара за
  содржината“ if it did not know of the unlawful activity or data, or,
  „веднаш штом дознае, да дејствува брзо со цел да го отстрани или да го
  оневозможи пристапот“. No immunity if the user acts under the provider's
  authority or control (чл. 17(2)). **There is no fixed deadline and no formal
  notice procedure.**
- **чл. 19:** courts and competent bodies can still order removal.
- **чл. 20:** no general duty to monitor (20(1)); a duty to tell authorities of
  a „основано сомнение“ of unlawful activity (20(2)); a duty to give
  identifying data about users to competent authorities on request (20(3)).
  The privacy page says data is disclosed to authorities only when the law
  requires it.
- **Caveats:**
  - An information-society service is one provided „за надомест“ (чл. 3). A
    wholly free site could be argued to fall outside the safe harbour; sponsorship
    brings it in.
  - **Pre-moderation** means we have seen and approved every review. That
    weakens the "no knowledge" defence for what we publish.
- **DSA:** not transposed. The EU 2025 report says alignment is still needed;
  the screening report targets 2028. Building DSA-style statements of reasons
  and an appeal now is cheap future-proofing.

### 4.2 Закон за граѓанска одговорност за навреда и клевета (ЗГОНК, 143/2012; amended 2022)

Read in the 2012 Gazette text. The 2022 amendments (adopted 17.11.2022) cut the
damages caps. Their Gazette number is **UNVERIFIED**, and so is **whether
чл. 11 survived** (a 2020 draft proposed deleting it). Assume it applies until
counsel checks.

- **чл. 5(2):** health („здравството“) is expressly an area of public
  interest. That helps protect critical reviews.
- **чл. 6, 7: insult.** An insulting opinion said to demean. **No liability**
  for „сериозна критика“ in the public interest (7(2)) or a negative opinion
  given „со искрена намера“ (7(4)).
- **чл. 8, 9: defamation.** Stating or spreading **untrue facts** about an
  identifiable person, knowing they are false or when one should have known.
  The defendant must prove truth or well-founded belief (9(1)–(2)).
  **Accusing someone of a crime** is excused only if it is in the public
  interest and proven true or believed on well-founded grounds (9(5)).
  **This is the legal line behind our review policy:** opinions are broadly
  protected; factual accusations, especially of crimes, are where liability
  starts.
- **чл. 11 „Одговорност на електронската публикација“:** the editor of an
  electronic publication shares liability with the author for giving access
  to insulting or defamatory information. The editor is not liable if it
  proves that the author did not act under its control **and** that it was
  not aware, „или во рок од 24 часа откако станал свесен … ги презел сите
  технички и други мерки за отстранување“. The injured person can request
  removal.
- **чл. 13:** before suing, the injured person asks for an apology and
  retraction; it must be published in the same place within **48 hours**.
- **чл. 14:** where the statement was made through „компјутерски систем“,
  the injured person can ask within 7 days for a reply or correction. It must
  be published within **2 days**, unless it is late, itself insulting, or
  harmful to third parties.
- **чл. 18:** damages caps (2022 values per secondary sources: author €400,
  editor €2,000, legal entity €5,000). **чл. 20:** sue within 3 months of
  learning, and at most 1 year after publication. **чл. 23:** interim ban
  within 3 days.

### 4.3 Other law

- **Criminal Code:** defamation and insult were decriminalised in 2012
  (Сл. весник 142/12 deleted чл. 172–177 and 180). **чл. 366** (false report of
  a crime) covers reports to authorities only. **чл. 319** (incitement to
  hatred) has been applied to Facebook comments.
- **Закон за облигационите односи (18/2001 … 215/21):** чл. 141 (fault
  liability), **чл. 144** (court order to stop a violation of personality
  rights), **чл. 187** (damages for false statements about another's
  „способноста“, i.e. a doctor's competence), чл. 188 (publication of a
  correction), чл. 189 (non-material damages, also for legal persons, i.e.
  clinics).
- **Закон за медиуми:** the directory is most likely **not a „медиум“**
  (чл. 2(1) requires editorially shaped content; portals register under
  чл. 5(4)). The media right of correction (чл. 17–19) and reply (чл. 26) do
  not apply directly, but ЗГОНК чл. 13–14 may. **Build an equivalent reply
  mechanism anyway.** The staff-posted official reply is that mechanism.
- **ECtHR** (MK courts must follow it under ЗГОНК чл. 2–3, 7(5)): *Delfi v.
  Estonia* (2015), *MTE and Index.hu v. Hungary* (2016), *Høiness v. Norway*
  (2019), *Sanchez v. France* (2023). The common thread: a working notice
  system plus fast removal protects the operator; liability is reserved for
  clearly unlawful content left online. **No Macedonian or ECtHR judgment on
  comment or platform liability against MK was found.**

### 4.4 Review and forum content policy (implemented in the terms)

1. **Allowed:** first-hand experiences and value judgments. "Waited two
   hours", "nothing was explained", "unpleasant manner", star ratings.
2. **Not allowed, rejected at moderation:**
   - factual allegations of crimes or misconduct stated as fact: bribes
     („зема мито / пари на рака“), forged sick notes, working without a
     licence, "a wrong diagnosis killed my mother";
   - factual claims about competence or qualifications (ЗОО чл. 187);
   - health or private details of the doctor, other patients or family
     members;
   - insults, slurs, hate speech, threats; private contact data;
   - conflicts of interest and paid or incentivised reviews (also consumer
     law чл. 71(1) т. 27).
3. **Rejection reason** for crime allegations: point people to the competent
   bodies. The terms list police, the public prosecutor, the State Sanitary
   and Health Inspectorate and the Лекарска комора. The ДКСК (anti-corruption
   commission) is another option for bribery.
4. **Do not edit reviews** (the code only approves or rejects). Rejection with
   a reason, and the author can resubmit. If a "reframe" feature is added
   later, it must be the author who edits.
5. **Moderation evidence:** write down the criteria, train moderators on them,
   and keep the audit trail (who, when, why). The code already stores
   `moderated_by_id` and `rejection_note`.

### 4.5 Notice-and-takedown and right of reply (product)

- **„Пријави“** on every review and post (being built in WP1), plus an e-mail
  route. Capture: who reports (role), which content, the disputed sentence,
  why it is false or insulting.
- **Within 24 hours** of a plausible report of insult, defamation or
  third-party health data: act (ЗГОНК чл. 11). The product resolves a report
  as keep or remove (no interim hide); the terms state a 24-hour review goal.
  The author gets the reason, the reporter gets the outcome (both by email;
  the reporter is never named to the author, the moderator never to either).
- For a doctor's complaint about a review's factual basis, follow the Jameda
  VI ZR 34/15 model: forward the complaint to the author, ask for a
  description of the visit (without health detail being published), and
  decide on what comes back.
- **Official reply** (being built in WP1): only after verifying that the
  request comes from the doctor or institution; staff post it; the same rules
  apply; **no patient data and no confirmation that someone was a patient**.
  Aim to publish within 2 days of verification (mirrors ЗГОНК чл. 14).
- Keep notices and decisions for at least **1 year** (ЗГОНК чл. 20).
- Hand over user-identifying data only to competent authorities on a formal
  request (ЗЕТ чл. 20(3)).

---

## 5. Health-specific rules: advertising, featured and sponsored

### 5.1 Закон за здравствената заштита (consolidated through 30/2024)

- **чл. 2 т. 28:** „Рекламирање на здравствената дејност“ means advertising
  messages and other forms of notice as part of marketing „чија крајна цел е
  користење на здравствената услуга“.
- **чл. 277(2):** forbidden is advertising of health activity or institutions
  „кое е залажувачко, недостојно или преку кое се вршат споредби со други
  здравствени дејности или установи“. Misleading includes presenting health
  workers in a way that may mislead patients, and exploiting their
  inexperience (чл. 277(3)). Comparative includes anything with a „штетно
  влијание на изборот на здравствената установа“ (чл. 277(5)). Articles
  written to promote health workers count as advertising; preventive and
  professional articles do not (чл. 277(6)).
- **чл. 279 „Информирање на јавноста“:** an institution may tell the public,
  including on the internet, only: name and address, type of activity, level
  of care and specialty, „обученост и квалификации на здравствените
  работници“, working hours, actual waiting time, price list and logo. The
  information must be true.
- **Penalties:** чл. 307(1) т. 19–20 and чл. 307(3)–(5): €500–10,000
  depending on size; a repeat offence means mandatory licence withdrawal.
  чл. 308(1) т. 15 for чл. 279. **These bind institutions and health
  workers, not the platform**, but the platform carries their advertising and
  shares the reputational and contractual risk.

### 5.2 Лекарска комора: Кодекс на медицинската деонтологија

- **чл. 18:** „Здравството не смее да биде комерцијализирана дејност. Секој
  непосреден или посреден публицитет или реклама кои немаат
  воспитно-заштитен или образовен карактер … е забранет за лекарот.“
- **чл. 85:** stressing one's own work and person is inconsistent with the
  profession. **чл. 87:** propaganda for clinics or for individual health
  workers is inconsistent with being a doctor. **чл. 86:** no arranged public
  thank-you notices. That last one is a warning sign for solicited reviews.

### 5.3 Medicines (products module)

- **Закон за лековите и медицинските средства (106/2007) чл. 95:** „Се
  забранува огласувањето на лековите што се издаваат на лекарски рецепт преку
  медиумите, за широката јавност“. It also bans comparison with other
  medicines and exaggeration.
- **чл. 94:** OTC medicines may be presented to the public only objectively,
  in line with the SmPC or leaflet, and „по претходно одобрение од
  Агенцијата“ (МАЛМЕД).
- **чл. 139:** the same applies to medical devices.
- **Product:** a neutral catalogue with reference prices is low-risk. **No
  featured or sponsored products, ever, for prescription medicines.**
  Product pages should show neutral, leaflet-based facts only. Food-supplement
  rules were not checked.

### 5.4 Consumer law and ranking

**Закон за заштита на потрошувачите (236/2022)** transposes the UCPD and the
Omnibus Directive:

- **чл. 71(1) т. 11:** undisclosed paid editorial content is always misleading.
- **т. 12:** search results „без јасно да обелодени какво било платено
  огласување или плаќање за да се постигне повисоко рангирање“ are always
  misleading.
- **т. 26–27:** claiming reviews come from real users without reasonable
  checks, or posting or commissioning fake reviews, is always misleading.
- **чл. 75(5):** the main ranking parameters must be explained in a place
  reachable directly from the results.

Its application to a free directory is arguable, but paid placements make the
platform a trader. **Treat it as binding.**

### 5.5 Assessment and neutrality rules

**„Истакнат“ (editorial, unpaid, ranked first): low–medium risk.** Without
payment it is not commercial communication. The risk is that being first with
a badge reads as a quality verdict.

Rules:

1. Publish objective criteria, e.g. licence verified against the Комора list
   and a complete profile. The terms carry a placeholder for them.
2. Never feature anyone with a commercial relationship (sponsor, partner,
   discount). Keep a written log of every featuring decision.
3. Explain "why first" in the toggletip (done) and add a short "how results
   are ordered" note linked from the lists (чл. 75(5)). The terms explain it
   now; a dedicated link from the lists is still a TODO for WP2.
4. Never use „најдобар“, „препорачан“ or „топ“. Cap or rotate the number of
   featured profiles per list.

**„Спонзорирано“ (paid doctor placement): medium–high risk.** It meets the
definition of advertising health services. If shown first, it is arguably
misleading or comparative (чл. 277). For doctors it conflicts with the code
(чл. 18, 85, 87).

**Recommendation:** do not sell to individual doctors until counsel and the
Комора have answered. If selling at all, prefer institutions. If doing it
anyway:

1. Label every appearance „Спонзорирано“, next to the name, with an
   explanation (done in the toggletip copy).
2. Sponsored content is limited to the чл. 279 facts. No claims, superlatives
   or comparisons.
3. **Payment never affects** organic order, featuring, which reviews are
   published, the rating, moderation, or complaint handling. In today's code
   `is_sponsored` affects no ordering. Keep it so, or show sponsored entries
   in a separate, labelled band, never as "result #1".
4. **No hidden advantages** (Jameda): no competitors or sponsored profiles on
   non-payers' pages; same photo, layout and completeness for everyone.
5. Remove the current hover text claiming sponsorship is given only to
   well-rated doctors and withdrawn on weak reviews. It ties payment to
   reviews and quality and conflates the two badges. Replacement copy is in
   [`featured-disclosure.md`](featured-disclosure.md).

---

## 6. Comparable EU case law: German BGH, Jameda

- **VI ZR 358/13 (23.9.2014):** a doctor must in principle accept being listed
  and rated. Freedom of communication prevails over the doctor's right of
  informational self-determination for a neutral portal. (Confirmed as cited
  in press release 34/2018.)
- **VI ZR 34/15 (1.3.2016)** (secondary sources): on a doctor's complaint, the
  portal must forward it to the reviewer, ask for a description of the
  treatment and evidence of contact, and pass on what it can to the doctor.
  If it does not check, it is liable. **This is the model for our report
  flow.**
- **VI ZR 30/17 (20.2.2018)** (press release read): profiles of non-paying
  doctors showed paying competitors; paying doctors' profiles did not, and
  users were not told. By that, the portal stepped back from its role as a
  „neutraler Informationsmittler“, and the non-paying doctor won deletion.
- **OLG Köln 15 U 89/19 and 126/19 (14.11.2019)** (secondary source): hidden
  advantages were unlawful, including a photo versus a grey silhouette in
  lists. Fuller service descriptions for payers were allowed.
- **VI ZR 488/19 and 489/19 (12.10.2021)** (secondary sources): after Jameda's
  redesign, deletion was refused. There is no general equal-treatment duty,
  but there must be no hidden advantages, judged case by case.
  (VI ZR 692/20 of 15.2.2022, also a refused deletion, was not read.)

**For Zdravje360:** these are persuasive, not binding, in MK. They map onto the
same balancing under ЗЗЛП чл. 21(3)(а), 25 and 81(4). The lesson: **our
right to refuse a doctor's removal request is only as strong as our
neutrality.** Every paid feature weakens it unless it is openly labelled and
gives no hidden advantage.

---

## 7. How the pages implement this

- **Privacy:** operator; anonymous use; guidance (anonymous, 90 days); account
  data (private name, public display name); activity events (180 days); health
  data rules; a section for listed health professionals (data, sources — ФЗОМ
  and the Лекарска комора by name — what is not taken, review before
  publication, basis with the balancing, correction within 15 days through
  „Пријави грешка во профилот“, objection and removal with balancing and
  reasons within 30 days, report and reply); the data kept for a correction
  or objection request (one year after closing); cookies and storage (exact names and
  durations); processors and transfers; retention; security; rights with MK
  deadlines; AZLP contact; minimum age (14).
- **Terms:** operator imprint (e-commerce чл. 7); informational purpose;
  account rules; review rules (opinion vs factual accusation of a crime, no
  third-party health data, conflicts of interest, no paid reviews);
  forum rules (no dosing advice, no selling or advertising of medicines);
  moderation and notice-and-takedown with a turnaround placeholder; official
  reply by a health professional (staff-posted after identity check; no
  patient data); featured vs sponsored; licence to user content; forbidden
  uses; liability (not excluding intent or gross negligence, consumer rights
  kept); governing law.
- **Disclaimer:** informational directory, not medical advice; one calm
  194/112 line; guidance limits; directory listing is not a recommendation;
  badges are not quality verdicts; reviews are opinions; product prices are
  reference data. `/disclaimer` now renders this text through `LegalPage`
  like the other two documents; the earlier card layout with two separate
  emergency mentions is gone.

---

## 8. Open questions for counsel

1. Does чл. 84 (prior approval) apply to (a) the forum, (b) reviews describing
   one's own treatment, (c) anonymous guidance answers? Should we apply?
2. Is a DPO mandatory (чл. 41(1)(в))? Is a чл. 71 high-risk notice needed? Is
   a prior consultation (чл. 40) needed?
3. Is the recently-viewed localStorage list "explicitly requested" under ЗЕК
   чл. 198(5)?
4. Is selling „Спонзорирано“ to doctors compatible with ЗЗЗ чл. 277 and the
   Комора code? Is it better limited to institutions?
5. Does the Закон за заштита на потрошувачите apply to a free directory with
   paid placements?
6. The minimum age for accounts: the pages now say 14 (the legal floor);
   should it be 16 for a health forum?
7. Which notice-and-takedown turnaround should we promise?
8. Is the directory a "медиум" under the Закон за медиуми (right of reply)? See §4.
9. Does relying on чл. 18(5) т. 2 (public notice instead of individual
   letters to listed doctors) hold?
10. Is the balancing test in §2.1.1 sufficient for importing the ФЗОМ and
    Лекарска комора lists, and may either body assert the database maker's
    right (ЗАПСП чл. 118–128) against this reuse?

### Placeholders in the pages

`[НАЗИВ НА ОПЕРАТОР]`, `[АДРЕСА]`, `[ЕДБ]`, `[ЕМБС]`, `[Е-ПОШТА]`,
`[ХОСТИНГ-ДАВАТЕЛ И ЛОКАЦИЈА]`, `[ДАВАТЕЛ НА Е-ПОШТА]`,
`[РОК ЗА ОДГОВОР НА ПРИЈАВА]`, `[НАЦРТ: КРИТЕРИУМИТЕ ЧЕКААТ ОДОБРУВАЊЕ]` (the draft
criteria are on `/transparency#kriteriumi`),
`[ОФИЦЕР ЗА ЗАШТИТА НА ЛИЧНИТЕ ПОДАТОЦИ]`.
