import { describe, expect, it } from "vitest";
import { buildLlmsTxt, type LlmsTxtInput } from "@/lib/llms-txt";

const input: LlmsTxtInput = {
  siteUrl: "https://zdravje.test",
  modules: { forum: true, guidance: true },
  categories: [
    {
      slug: "hirurgija",
      name: "Хирургија",
      description: "Искуства со\nоперации",
    },
  ],
  topics: [
    {
      slug: "operacija-za-prosireni-veni",
      title: "Операција [за] проширени вени",
      category: { slug: "hirurgija" },
    },
  ],
  tags: [
    { slug: "prosireni-veni", name: "проширени вени", latin: "prosireni veni" },
  ],
};

describe("llms.txt", () => {
  it("follows the llmstxt.org shape: H1, summary, H2 link lists, Optional", () => {
    const text = buildLlmsTxt(input);
    const lines = text.split("\n");

    expect(lines[0]).toBe("# Zdravje360 (Здравје360)");
    expect(lines[2].startsWith("> ")).toBe(true);
    expect(text).toContain("## Optional");
    expect(text.indexOf("## Optional")).toBeGreaterThan(text.indexOf("## "));
  });

  it("links the directory, forum categories, recent topics and tag pages", () => {
    const text = buildLlmsTxt(input);

    expect(text).toContain("- [Лекари](https://zdravje.test/doctors): ");
    expect(text).toContain(
      "- [Хирургија](https://zdravje.test/forum/hirurgija): Искуства со операции",
    );
    // Brackets in a title would break the markdown link.
    expect(text).toContain(
      "- [Операција за проширени вени](https://zdravje.test/forum/hirurgija/operacija-za-prosireni-veni)",
    );
    expect(text).toContain(
      "- [проширени вени](https://zdravje.test/forum/tags/prosireni-veni): prosireni veni",
    );
    expect(text).toContain("(https://zdravje.test/sitemap.xml)");
  });

  it("leaves the forum and guidance out while their modules are off", () => {
    const text = buildLlmsTxt({
      ...input,
      modules: { forum: false, guidance: false },
    });

    expect(text).not.toContain("/forum");
    expect(text).not.toContain("/guidance");
    expect(text).toContain("/doctors");
  });
});
