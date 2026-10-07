# Third-party assets and data

Content shipped in this repository that was not written for the project, with
its source and licence. Code dependencies (Composer, npm) are not listed here —
their licences are in their own packages — except the one copyleft library
below.

## Username lists

| What | Where | Source | Licence | How it is used |
|------|-------|--------|---------|----------------|
| English list of obscene and offensive words | `apps/api/database/seeders/data/username_terms_ldnoobw_en.php` | [List of Dirty, Naughty, Obscene, and Otherwise Bad Words](https://github.com/LDNOOBW/List-of-Dirty-Naughty-Obscene-and-Otherwise-Bad-Words), file `en`, commit `5faf2ba42d7b1c0977169ec3611df25a3c08eb13` (2020-07-13), © its contributors | [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/) | Reproduced unmodified as seed data for the blocked-username list (`username_terms`, note „LDNOOBW (CC BY 4.0)“). `App\Support\Usernames\UsernameTermSeedList` decides how each entry is matched and leaves out a few that would refuse ordinary names or words; staff can edit the stored list in the admin panel („Username rules“). |

Everything else in the username lists (Macedonian and Albanian terms, English
slurs, hate, drug and scam terms, reserved names, and the exceptions) was
compiled for this project in `apps/api/database/seeders/data/username_terms.php`
and carries no third-party licence. No list under a restrictive or unclear
licence was used.

## Dependencies with a copyleft licence

Most Composer and npm dependencies are under permissive licences (MIT, BSD,
Apache-2.0) and are not listed. The exception is recorded here so it is not
overlooked when the app is distributed or hosted:

| Package | Version | Licence | Used for | What the licence asks |
|---------|---------|---------|----------|-----------------------|
| [`smalot/pdfparser`](https://github.com/smalot/pdfparser) | 2.12.x (`apps/api/composer.lock`) | [LGPL-3.0](https://www.gnu.org/licenses/lgpl-3.0.html) | Reading the Лекарска комора licence-list PDFs (`App\Support\Licences\KomoraLicenceListParser`, `import:komora-licences`); server side only, never sent to browsers | Used unmodified as a separately installed Composer library, which LGPL-3.0 allows in a proprietary application. If the app (with `vendor/`) is ever distributed to others, include the licence text and keep the library replaceable (it is: Composer); if the library itself is modified, publish those changes under LGPL-3.0. Running it on our own server is not distribution. The owner approved the package in 2026-10 believing it MIT-licensed, was told it is LGPL-3.0, and acknowledged keeping it on 2026-10-07. |

## Anti-bot proof of work (ALTCHA)

Approved by the owner in 2026-10. Both halves are self-hosted: no request
goes to altcha.org or any other third party, no cookie is set, and the
widget's optional „human interaction signature“ collector is switched off.

| Package | Version | Licence | Used for |
|---------|---------|---------|----------|
| [`altcha`](https://github.com/altcha-org/altcha) (npm, by Daniel Regeci / altcha.org) | 3.3.x (`apps/web/package-lock.json`) | [MIT](https://github.com/altcha-org/altcha/blob/main/LICENSE) — `node_modules/altcha/LICENSE.txt` | The browser widget (`altcha/external` build plus its PBKDF2 worker, `src/components/altcha/`): fetches a challenge from our own API through `/api/altcha/challenge` and solves it in a Web Worker. Its only dependency, `hash-wasm` (MIT), is for the Argon2/scrypt workers we do not load. |
| [`altcha-org/altcha`](https://github.com/altcha-org/altcha-lib-php) (Composer, by Daniel Regeci) | 2.3.x (`apps/api/composer.lock`; requires PHP ≥ 8.1, runs on our 8.4/8.5) | [MIT](https://github.com/altcha-org/altcha-lib-php/blob/main/LICENSE.txt) — `vendor/altcha-org/altcha/LICENSE.txt` | Server side: issuing signed PBKDF2 challenges and verifying solutions (`App\Support\Altcha\AltchaGuard`, middleware `altcha`). |

MIT asks only that the copyright and licence notice stay with copies of the
code; both packages ship it in their own directory. The widget's „Protected by
ALTCHA“ footer link is hidden — the licence does not require attribution in
the interface.

## Images from institutions' websites

Facility logos and cover photos imported by `import:institutions-json` come
from each institution's own public website, with the source URL kept per image
(`facility_media.source_url`). Logos are used to identify the institution;
cover photos show its building or premises. Neither is shipped in this
repository (they live on the media disk of the running app). An institution
can ask for any image to be removed: staff remove it in one click on the
facility page (**Website images → Remove image**), and a removed image is never
imported again (`docs/data-import.md` §6).

## Public CA certificate for the ФЗОМ download

`apps/api/resources/tls/import-extra-ca.crt` is the public intermediate CA
certificate **GeoTrust TLS RSA CA G1** (issued by DigiCert Global Root G2;
valid 2017-11-02 – 2027-11-02; SHA-256
`C0:6E:30:7F:7C:FC:1D:32:FA:72:A4:C0:33:C8:7B:90:01:9A:F2:16:F0:77:5D:64:97:8A:2E:CA:6C:8A:23:0E`).
DigiCert publishes it for anyone to install; it carries no licence terms.
Only the import fetchers trust it, in addition to the system store, because
arhiva.fzo.org.mk omits it from its chain (`docs/data-import.md` §3, TLS).
Replace it when it expires or when ФЗОМ's certificate changes issuer.
