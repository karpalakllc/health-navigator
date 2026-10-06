import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { LegalPage } from "@/components/legal/legal-page";
import {
  PrivacyContent,
  privacyLastUpdated,
  privacySections,
} from "@/content/legal/privacy";
import {
  DisclaimerContent,
  disclaimerLastUpdated,
  disclaimerSections,
} from "@/content/legal/disclaimer";
import {
  TermsContent,
  termsLastUpdated,
  termsSections,
} from "@/content/legal/terms";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

const DOCUMENTS = [
  [
    "privacy",
    "legal.privacyTitle",
    PrivacyContent,
    privacySections,
    privacyLastUpdated,
  ],
  ["terms", "legal.termsTitle", TermsContent, termsSections, termsLastUpdated],
  [
    "disclaimer",
    "legal.disclaimerTitle",
    DisclaimerContent,
    disclaimerSections,
    disclaimerLastUpdated,
  ],
] as const;

describe("LegalPage", () => {
  it.each(DOCUMENTS)(
    "the %s table of contents links to every section heading",
    async (_name, titleKey, Content, sections, lastUpdated) => {
      const { container } = render(
        <LegalPage
          titleKey={titleKey}
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
          name: t(titleKey),
        }),
      ).toBeInTheDocument();
      expect(screen.getByText(lastUpdated)).toBeInTheDocument();
      expect(await seriousA11yViolations(container)).toEqual([]);
    },
  );

  // Macedonian Cyrillic has none of these (Russian/Serbian/Bulgarian) letters;
  // one slipping in reads as a typo on a page people are meant to trust.
  it.each(DOCUMENTS)(
    "the %s text uses only Macedonian Cyrillic",
    (_name, _titleKey, Content) => {
      const { container } = render(<Content />);
      expect(container.textContent).not.toMatch(/[йщъыьэюяёЙЩЪЫЬЭЮЯЁ]/);
    },
  );
});
