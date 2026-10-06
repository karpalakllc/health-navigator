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
| [`smalot/pdfparser`](https://github.com/smalot/pdfparser) | 2.12.x (`apps/api/composer.lock`) | [LGPL-3.0](https://www.gnu.org/licenses/lgpl-3.0.html) | Reading the Лекарска комора licence-list PDFs (`App\Support\Licences\KomoraLicenceListParser`, `import:komora-licences`); server side only, never sent to browsers | Used unmodified as a separately installed Composer library, which LGPL-3.0 allows in a proprietary application. If the app (with `vendor/`) is ever distributed to others, include the licence text and keep the library replaceable (it is: Composer); if the library itself is modified, publish those changes under LGPL-3.0. Running it on our own server is not distribution. The owner approved the package in 2026-10 as MIT-licensed; the LGPL-3.0 licence still needs the owner's acknowledgement. |

## Images from institutions' websites

Facility logos and cover photos imported by `import:institutions-json` come
from each institution's own public website, with the source URL kept per image
(`facility_media.source_url`). Logos are used to identify the institution;
cover photos show its building or premises. Neither is shipped in this
repository (they live on the media disk of the running app). An institution
can ask for any image to be removed: staff remove it in one click on the
facility page (**Website images → Remove image**), and a removed image is never
imported again (`docs/data-import.md` §6).
