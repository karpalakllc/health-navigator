import { describe, expect, it } from "vitest";
import { ICON_NAMES } from "@/components/ui/icons";
import { specialtyIcon } from "@/lib/specialty-icons";

describe("specialtyIcon", () => {
  it.each([
    [{ slug: "kardiologija", name: "Кардиологија" }, "heart"],
    [{ slug: "pedijatrija", name: "Педијатрија" }, "baby"],
    [{ slug: "ortopedija", name: "Ортопедија" }, "bone"],
    [{ slug: "dermatologija", name: "Дерматологија" }, "droplet"],
    [{ slug: "ginekologija", name: "Гинекологија" }, "venus"],
    [{ slug: "nevrologija" }, "brain"],
    [{ slug: "stomatologija" }, "tooth"],
    [{ slug: "oftalmologija" }, "eye"],
    [{ slug: "x-1", name: "ОРЛ" }, "ear"],
    [{ slug: "interna-medicina" }, "activity"],
    // Matched on the name when the slug says nothing.
    [{ slug: "s-12", name: "Детска кардиологија" }, "heart"],
    [{ slug: "nepoznata", name: "Непозната" }, "stethoscope"],
  ])("%o → %s", (specialty, icon) => {
    expect(specialtyIcon(specialty)).toBe(icon);
    expect(ICON_NAMES).toContain(icon);
  });

  it("does not read „орл“ inside another word", () => {
    expect(specialtyIcon({ slug: "s", name: "Корлеоне" })).toBe("stethoscope");
  });
});
