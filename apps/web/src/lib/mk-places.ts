import { foldScript } from "@/lib/script-fold";

/*
 * The territorial organisation of North Macedonia, for the city picker:
 * the 80 municipalities (10 of which make up the City of Skopje) grouped
 * under the 8 statistical regions, each region shown under its main town.
 *
 * Sources (see docs/mk-territorial-data.md):
 * - Закон за територијалната организација на локалната самоуправа во
 *   Република Македонија, Сл. весник 55/2004 (амандмани 12/2005, 98/2008,
 *   106/2008, 149/2014); the Kičevo merger (Другово, Зајас, Осломеј,
 *   Вранештица → Кичево) took effect in 2013, leaving 80 municipalities.
 * - Закон за градот Скопје, Сл. весник 55/2004: the ten municipalities of
 *   the City of Skopje.
 * - Државен завод за статистика, Номенклатура на територијални единици за
 *   статистика (НТЕС 3): the eight statistical regions and their members.
 * Latin names follow the official romanisation (š, č, ž, gj, kj, dž).
 */

export type Place = {
  /** Stable id: the Latin name, lower-case, hyphenated. */
  id: string;
  name: string;
  latin: string;
  /** Seat, when it differs from the municipality's name. */
  seat?: string;
  /** One of the ten municipalities of the City of Skopje. */
  cityOfSkopje?: boolean;
};

export type PlaceGroup = {
  /** The group's main town: selecting it filters by that town. */
  city: Place;
  region: string;
  regionLatin: string;
  /** The region's other municipalities (for Skopje: its own ten first). */
  places: Place[];
};

function place(
  name: string,
  latin: string,
  extra: Omit<Place, "id" | "name" | "latin"> = {},
): Place {
  return {
    id: latin
      .toLowerCase()
      .normalize("NFD")
      .replace(/[̀-ͯ]/g, "")
      .replace(/[^a-z0-9]+/g, "-"),
    name,
    latin,
    ...extra,
  };
}

const skopje = (name: string, latin: string) =>
  place(name, latin, { cityOfSkopje: true });

export const MK_PLACE_GROUPS: PlaceGroup[] = [
  {
    city: place("Скопје", "Skopje"),
    region: "Скопски регион",
    regionLatin: "Skopje Region",
    places: [
      skopje("Аеродром", "Aerodrom"),
      skopje("Бутел", "Butel"),
      skopje("Гази Баба", "Gazi Baba"),
      skopje("Ѓорче Петров", "Gjorče Petrov"),
      skopje("Карпош", "Karpoš"),
      skopje("Кисела Вода", "Kisela Voda"),
      skopje("Сарај", "Saraj"),
      skopje("Центар", "Centar"),
      skopje("Чаир", "Čair"),
      skopje("Шуто Оризари", "Šuto Orizari"),
      place("Арачиново", "Aračinovo"),
      place("Зелениково", "Zelenikovo"),
      place("Илинден", "Ilinden"),
      place("Петровец", "Petrovec"),
      place("Сопиште", "Sopište"),
      place("Студеничани", "Studeničani"),
      place("Чучер-Сандево", "Čučer-Sandevo"),
    ],
  },
  {
    city: place("Битола", "Bitola"),
    region: "Пелагониски регион",
    regionLatin: "Pelagonia Region",
    places: [
      place("Демир Хисар", "Demir Hisar"),
      place("Долнени", "Dolneni"),
      place("Кривогаштани", "Krivogaštani"),
      place("Крушево", "Kruševo"),
      place("Могила", "Mogila"),
      place("Новаци", "Novaci"),
      place("Прилеп", "Prilep"),
      place("Ресен", "Resen"),
    ],
  },
  {
    city: place("Велес", "Veles"),
    region: "Вардарски регион",
    regionLatin: "Vardar Region",
    places: [
      place("Градско", "Gradsko"),
      place("Демир Капија", "Demir Kapija"),
      place("Кавадарци", "Kavadarci"),
      place("Лозово", "Lozovo"),
      place("Неготино", "Negotino"),
      place("Росоман", "Rosoman"),
      place("Свети Николе", "Sveti Nikole"),
      place("Чашка", "Čaška"),
    ],
  },
  {
    city: place("Куманово", "Kumanovo"),
    region: "Североисточен регион",
    regionLatin: "Northeastern Region",
    places: [
      place("Кратово", "Kratovo"),
      place("Крива Паланка", "Kriva Palanka"),
      place("Липково", "Lipkovo"),
      place("Ранковце", "Rankovce"),
      place("Старо Нагоричане", "Staro Nagoričane"),
    ],
  },
  {
    city: place("Охрид", "Ohrid"),
    region: "Југозападен регион",
    regionLatin: "Southwestern Region",
    places: [
      place("Вевчани", "Vevčani"),
      place("Дебар", "Debar"),
      place("Дебарца", "Debarca", { seat: "Белчишта" }),
      place("Кичево", "Kičevo"),
      place("Македонски Брод", "Makedonski Brod"),
      place("Пласница", "Plasnica"),
      place("Струга", "Struga"),
      place("Центар Жупа", "Centar Župa"),
    ],
  },
  {
    city: place("Струмица", "Strumica"),
    region: "Југоисточен регион",
    regionLatin: "Southeastern Region",
    places: [
      place("Богданци", "Bogdanci"),
      place("Босилово", "Bosilovo"),
      place("Валандово", "Valandovo"),
      place("Василево", "Vasilevo"),
      place("Гевгелија", "Gevgelija"),
      place("Дојран", "Dojran", { seat: "Стар Дојран" }),
      place("Конче", "Konče"),
      place("Ново Село", "Novo Selo"),
      place("Радовиш", "Radoviš"),
    ],
  },
  {
    city: place("Тетово", "Tetovo"),
    region: "Полошки регион",
    regionLatin: "Polog Region",
    places: [
      place("Боговиње", "Bogovinje"),
      place("Брвеница", "Brvenica"),
      place("Врапчиште", "Vrapčište"),
      place("Гостивар", "Gostivar"),
      place("Желино", "Želino"),
      place("Јегуновце", "Jegunovce"),
      place("Маврово и Ростуше", "Mavrovo i Rostuše", { seat: "Ростуше" }),
      place("Теарце", "Tearce"),
    ],
  },
  {
    city: place("Штип", "Štip"),
    region: "Источен регион",
    regionLatin: "Eastern Region",
    places: [
      place("Берово", "Berovo"),
      place("Виница", "Vinica"),
      place("Делчево", "Delčevo"),
      place("Зрновци", "Zrnovci"),
      place("Карбинци", "Karbinci"),
      place("Кочани", "Kočani"),
      place("Македонска Каменица", "Makedonska Kamenica"),
      place("Пехчево", "Pehčevo"),
      place("Пробиштип", "Probištip"),
      place("Чешиново-Облешево", "Češinovo-Obleševo", { seat: "Облешево" }),
    ],
  },
];

/** What a city choice puts in the search: a label and the `city` filter. */
export type CityChoice = {
  /** What the picker shows („Карпош“). */
  label: string;
  /** The `city` query value („Скопје“ for Карпош). */
  city: string;
};

/**
 * The `city` filter for a place. Listings store a town, never a
 * municipality, so:
 * - a group's main town filters by itself;
 * - a municipality of the City of Skopje filters by „Скопје“;
 * - any other municipality filters by its own name (or seat) when that town
 *   holds listings, and by its group's main town when it holds none.
 *   With `known` unknown (the cities list failed), its own name is used.
 */
export function cityFilterFor(
  target: Place,
  group: PlaceGroup,
  known?: readonly string[],
): string {
  if (target === group.city) {
    return group.city.name;
  }
  if (target.cityOfSkopje) {
    return group.city.name;
  }
  const own = target.seat ?? target.name;
  if (!known || known.length === 0) {
    return own;
  }
  for (const candidate of [target.seat, target.name]) {
    if (!candidate) continue;
    const match = known.find(
      (city) => foldScript(city) === foldScript(candidate),
    );
    if (match) {
      return match;
    }
  }
  return group.city.name;
}

/** Does the place match a typed filter, in either script? */
export function placeMatches(target: Place, query: string): boolean {
  const q = foldScript(query);
  if (q === "") return true;
  return [target.name, target.latin, target.seat ?? ""].some((value) =>
    foldScript(value).includes(q),
  );
}

/** The group and place for a stored `city` value, if it names one. */
export function findPlaceByName(
  name: string,
): { group: PlaceGroup; place: Place } | undefined {
  const folded = foldScript(name);
  if (!folded) return undefined;
  for (const group of MK_PLACE_GROUPS) {
    for (const candidate of [group.city, ...group.places]) {
      if (
        foldScript(candidate.name) === folded ||
        foldScript(candidate.latin) === folded
      ) {
        return { group, place: candidate };
      }
    }
  }
  return undefined;
}
