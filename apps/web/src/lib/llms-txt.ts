import { mk } from "@/i18n/mk";
import { t } from "@/i18n/t";

/**
 * /llms.txt in the llmstxt.org format: an H1, a blockquote summary, H2
 * sections of `[name](url): note` links, and an "Optional" section an agent
 * may skip. No AI vendor has said it reads the file (docs/seo.md); it is
 * cheap, so it is offered, but robots.txt, the sitemap and server-rendered
 * pages are what actually get the site found.
 */
export type LlmsTxtInput = {
  siteUrl: string;
  modules: { forum: boolean; guidance: boolean };
  categories: { slug: string; name: string; description: string | null }[];
  topics: { slug: string; title: string; category: { slug: string } }[];
  tags: { slug: string; name: string; latin: string }[];
};

/** Keeps a link line on one line and the markdown link intact. */
function clean(text: string): string {
  return text.replace(/\s+/g, " ").replace(/[[\]]/g, "").trim();
}

function link(siteUrl: string, name: string, path: string, note?: string) {
  const line = `- [${clean(name)}](${siteUrl}${path})`;

  return note ? `${line}: ${clean(note)}` : line;
}

export function buildLlmsTxt(input: LlmsTxtInput): string {
  const { siteUrl, modules } = input;
  const out: string[] = [
    `# ${mk.meta.title} (Здравје360)`,
    "",
    `> ${t("seo.llmsSummary")}`,
    "",
    mk.meta.description,
    "",
    `## ${t("seo.llmsSections")}`,
    "",
    link(siteUrl, t("doctors.title"), "/doctors", t("seo.llmsDoctors")),
    link(
      siteUrl,
      t("facilities.title"),
      "/facilities",
      t("seo.llmsFacilities"),
    ),
  ];

  if (modules.forum) {
    out.push(link(siteUrl, t("forum.title"), "/forum", t("seo.llmsForum")));
  }

  if (modules.guidance) {
    out.push(
      link(siteUrl, t("nav.guidance"), "/guidance", t("seo.llmsGuidance")),
    );
  }

  if (modules.forum && input.categories.length > 0) {
    out.push("", `## ${t("seo.llmsCategories")}`, "");
    for (const category of input.categories) {
      out.push(
        link(
          siteUrl,
          category.name,
          `/forum/${category.slug}`,
          category.description ?? undefined,
        ),
      );
    }
  }

  if (modules.forum && input.topics.length > 0) {
    out.push("", `## ${t("seo.llmsTopics")}`, "");
    for (const topic of input.topics) {
      out.push(
        link(
          siteUrl,
          topic.title,
          `/forum/${topic.category.slug}/${topic.slug}`,
        ),
      );
    }
  }

  if (modules.forum && input.tags.length > 0) {
    out.push("", `## ${t("seo.llmsTags")}`, "");
    for (const tag of input.tags) {
      out.push(link(siteUrl, tag.name, `/forum/tags/${tag.slug}`, tag.latin));
    }
  }

  out.push(
    "",
    "## Optional",
    "",
    link(siteUrl, t("seo.llmsAbout"), "/about"),
    link(siteUrl, t("seo.llmsDisclaimer"), "/disclaimer"),
    link(siteUrl, t("seo.llmsTerms"), "/terms"),
    link(siteUrl, t("seo.llmsPrivacy"), "/privacy"),
    link(siteUrl, "Sitemap", "/sitemap.xml"),
    "",
  );

  return out.join("\n");
}
