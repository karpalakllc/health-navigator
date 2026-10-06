# Territorial data for the city picker

`apps/web/src/lib/mk-places.ts` holds the static list behind the home search's
city picker: the **80 municipalities** of North Macedonia (10 of which make up
the **City of Skopje**), grouped under the **8 statistical regions**, each
region shown under its main town (Скопје, Битола, Велес, Куманово, Охрид,
Струмица, Тетово, Штип). Names are in Cyrillic and in the official Latin
romanisation.

## Sources

- **Закон за територијалната организација на локалната самоуправа во
  Република Македонија**, Службен весник на РМ 55/2004, with amendments
  12/2005 (Constitutional Court decision), 98/2008, 106/2008 (correction)
  and 149/2014. The merger of Другово, Зајас, Осломеј and Вранештица into
  Кичево took effect in 2013, which leaves 80 municipalities.
  Consolidated texts published by Општина Гази Баба:
  <https://business.gazibaba.gov.mk/media/pdf/zakonska_regulativa/ogb/zakon_za_teritorijalnata_organizacija_na_lokalnata_samouprava_vo_rm/1_teritorijalna_organizacija_na_lokalnata_samouprava_na_RM.pdf>,
  <https://business.gazibaba.gov.mk/media/pdf/zakonska_regulativa/ogb/zakon_za_teritorijalnata_organizacija_na_lokalnata_samouprava_vo_rm/6_id_zakon_za_teritorijalna_organizacija_na_ls_vo_rm_149_13102014.pdf>
- **Закон за градот Скопје**, Службен весник на РМ 55/2004: the ten
  municipalities of the City of Skopje (Аеродром, Бутел, Гази Баба, Ѓорче
  Петров, Карпош, Кисела Вода, Сарај, Центар, Чаир, Шуто Оризари).
- **Државен завод за статистика**, Номенклатура на територијални единици за
  статистика (НТЕС 3): the eight statistical regions and their member
  municipalities. Cross-checked against the region listing in
  <https://en.wikipedia.org/wiki/Municipalities_of_North_Macedonia>.

Seats are recorded only where they differ from the municipality's name and
are well established (Дебарца → Белчишта, Дојран → Стар Дојран, Маврово и
Ростуше → Ростуше, Чешиново-Облешево → Облешево).

`apps/web/src/lib/mk-places.test.ts` guards the counts (8 regions, 80
municipalities, 10 of the City of Skopje, unique names and ids, Macedonian
alphabet only).

## How a choice becomes a search filter

Doctors and facilities store a town (`city`), never a municipality, and the
API's `city` filter is a script-insensitive substring match. So:

- a region's main town filters by itself;
- a City of Skopje municipality (Карпош …) filters by „Скопје“;
- any other municipality filters by its own name (or seat) when
  `GET /api/v1/locations/cities` lists that town, and by its region's main
  town when it holds no listings — the picker says so on the row
  („резултати за Битола“).

The picker is used on the home hero only. The directory filter sheets keep
their free-text city field: reusing the picker there would change the
directory filter forms owned by another package, so it is left as a
follow-up.
