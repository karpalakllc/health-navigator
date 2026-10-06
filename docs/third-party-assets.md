# Third-party assets and data

Content shipped in this repository that was not written for the project, with
its source and licence. Code dependencies (Composer, npm) are not listed here;
their licences are in their own packages.

## Username lists

| What | Where | Source | Licence | How it is used |
|------|-------|--------|---------|----------------|
| English list of obscene and offensive words | `apps/api/database/seeders/data/username_terms_ldnoobw_en.php` | [List of Dirty, Naughty, Obscene, and Otherwise Bad Words](https://github.com/LDNOOBW/List-of-Dirty-Naughty-Obscene-and-Otherwise-Bad-Words), file `en`, commit `5faf2ba42d7b1c0977169ec3611df25a3c08eb13` (2020-07-13), © its contributors | [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/) | Reproduced unmodified as seed data for the blocked-username list (`username_terms`, note „LDNOOBW (CC BY 4.0)“). `App\Support\Usernames\UsernameTermSeedList` decides how each entry is matched and leaves out a few that would refuse ordinary names or words; staff can edit the stored list in the admin panel („Username rules“). |

Everything else in the username lists (Macedonian and Albanian terms, English
slurs, hate, drug and scam terms, reserved names, and the exceptions) was
compiled for this project in `apps/api/database/seeders/data/username_terms.php`
and carries no third-party licence. No list under a restrictive or unclear
licence was used.
