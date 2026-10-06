import { describe, expect, it } from "vitest";
import {
  cityFilterFor,
  findPlaceByName,
  MK_PLACE_GROUPS,
  placeMatches,
} from "@/lib/mk-places";
import { foldScript, scriptIncludes } from "@/lib/script-fold";

function group(name: string) {
  const found = MK_PLACE_GROUPS.find((g) => g.city.name === name);
  if (!found) throw new Error(name);
  return found;
}

function placeIn(groupName: string, name: string) {
  const found = group(groupName).places.find((p) => p.name === name);
  if (!found) throw new Error(name);
  return found;
}

describe("foldScript", () => {
  it("folds Cyrillic, Latin with diacritics and typed digraphs alike", () => {
    expect(foldScript("Карпош")).toBe("karpos");
    expect(foldScript("Karpoš")).toBe("karpos");
    expect(foldScript("karposh")).toBe("karpos");
    expect(foldScript("Ѓорче Петров")).toBe(foldScript("Gjorče Petrov"));
    expect(foldScript("Ѓорче Петров")).toBe(foldScript("gjorche petrov"));
    expect(foldScript("Чучер-Сандево")).toBe(foldScript("cucer sandevo"));
    expect(scriptIncludes("Шуто Оризари", "suto")).toBe(true);
    expect(scriptIncludes("Шуто Оризари", "x")).toBe(false);
  });
});

describe("MK_PLACE_GROUPS (territorial organisation)", () => {
  const municipalities = MK_PLACE_GROUPS.flatMap((g) =>
    g.city.name === "Скопје" ? g.places : [g.city, ...g.places],
  );

  it("holds the 80 municipalities in 8 regions, 10 of them the City of Skopje", () => {
    expect(MK_PLACE_GROUPS).toHaveLength(8);
    expect(municipalities).toHaveLength(80);
    expect(new Set(municipalities.map((p) => p.name)).size).toBe(80);
    expect(municipalities.filter((p) => p.cityOfSkopje)).toHaveLength(10);
    expect(group("Скопје").places).toHaveLength(17);
  });

  it("names every place in both scripts with a unique id", () => {
    const ids = MK_PLACE_GROUPS.flatMap((g) => [g.city, ...g.places]).map(
      (p) => p.id,
    );
    expect(new Set(ids).size).toBe(ids.length);
    for (const place of municipalities) {
      expect(place.name).toMatch(/^[Ѐ-ӿ -]+$/);
      expect(place.latin).toMatch(/^[A-Za-zčćšžČŠŽ -]+$/);
      // Macedonian alphabet only.
      expect(place.name).not.toMatch(/[йщъыьэюяё]/i);
    }
  });

  it("matches a place typed in either script", () => {
    const karpos = placeIn("Скопје", "Карпош");
    expect(placeMatches(karpos, "карп")).toBe(true);
    expect(placeMatches(karpos, "Karpos")).toBe(true);
    expect(placeMatches(karpos, "karposh")).toBe(true);
    expect(placeMatches(karpos, "битола")).toBe(false);
  });
});

describe("cityFilterFor", () => {
  const known = ["Скопје", "Битола", "Прилеп"];

  it("sends a City of Skopje municipality to „Скопје“", () => {
    expect(
      cityFilterFor(placeIn("Скопје", "Карпош"), group("Скопје"), known),
    ).toBe("Скопје");
  });

  it("keeps a municipality whose town holds listings", () => {
    expect(
      cityFilterFor(placeIn("Битола", "Прилеп"), group("Битола"), known),
    ).toBe("Прилеп");
    // Script-insensitive: the data's own spelling is what is sent.
    expect(
      cityFilterFor(placeIn("Битола", "Прилеп"), group("Битола"), ["Prilep"]),
    ).toBe("Prilep");
  });

  it("falls back to the region's main town when the place has no listings", () => {
    expect(
      cityFilterFor(placeIn("Битола", "Ресен"), group("Битола"), known),
    ).toBe("Битола");
    expect(
      cityFilterFor(placeIn("Скопје", "Илинден"), group("Скопје"), known),
    ).toBe("Скопје");
  });

  it("uses the seat or own name when the cities are unknown", () => {
    expect(cityFilterFor(placeIn("Охрид", "Дебрца"), group("Охрид"), [])).toBe(
      "Белчишта",
    );
    expect(cityFilterFor(placeIn("Охрид", "Струга"), group("Охрид"))).toBe(
      "Струга",
    );
  });

  it("finds a stored city back by either script", () => {
    expect(findPlaceByName("karpos")?.place.name).toBe("Карпош");
    expect(findPlaceByName("Скопје")?.group.region).toBe("Скопски регион");
    expect(findPlaceByName("Атлантида")).toBeUndefined();
  });
});
