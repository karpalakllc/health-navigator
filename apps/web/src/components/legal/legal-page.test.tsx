import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { LegalPage } from "@/components/legal/legal-page";
import {
  PrivacyContent,
  privacyLastUpdated,
  privacySections,
} from "@/content/legal/privacy";
import {
  TermsContent,
  termsLastUpdated,
  termsSections,
} from "@/content/legal/terms";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

describe("LegalPage", () => {
  it.each([
    ["privacy", PrivacyContent, privacySections, privacyLastUpdated],
    ["terms", TermsContent, termsSections, termsLastUpdated],
  ] as const)(
    "the %s table of contents links to every section heading",
    async (_name, Content, sections, lastUpdated) => {
      const { container } = render(
        <LegalPage
          titleKey="legal.privacyTitle"
          lastUpdated={lastUpdated}
          sections={sections}
        >
          <Content />
        </LegalPage>,
      );

      const article = screen.getByRole("article");
      const headings = within(article).getAllByRole("heading", { level: 2 });
      // Every h2 of the text is in the list, in order, and nothing else is.
      expect(headings.map((h) => h.textContent)).toEqual(
        sections.map((s) => s.title),
      );

      const [toc] = screen.getAllByRole("navigation", {
        name: t("legal.toc"),
      });
      const links = within(toc).getAllByRole("link");
      expect(links.map((a) => a.getAttribute("href"))).toEqual(
        headings.map((h) => `#${h.id}`),
      );

      expect(
        screen.getByRole("heading", {
          level: 1,
          name: t("legal.privacyTitle"),
        }),
      ).toBeInTheDocument();
      expect(screen.getByText(lastUpdated)).toBeInTheDocument();
      expect(await seriousA11yViolations(container)).toEqual([]);
    },
  );
});
