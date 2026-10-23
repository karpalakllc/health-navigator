import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { FirstAidGuideArticle } from "@/components/first-aid/guide-article";
import { FirstAidIndex } from "@/components/first-aid/guide-index";
import {
  FIRST_AID_ILLUSTRATION_IDS,
  FirstAidIllustration,
} from "@/components/first-aid/illustrations";
import { FIRST_AID_COPY } from "@/content/first-aid/copy";
import {
  FIRST_AID_ANCHORS,
  FIRST_AID_GROUPS,
  FIRST_AID_GUIDES,
  getFirstAidGuide,
} from "@/content/first-aid";
import type { FirstAidGuide } from "@/content/first-aid/types";
import { seriousA11yViolations } from "../../../test/axe";

function escapeRegExp(text: string): string {
  return text.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

const SLUGS = FIRST_AID_GUIDES.map((guide) => [guide.slug] as const);

function guide(slug: string): FirstAidGuide {
  return getFirstAidGuide(slug)!;
}

function published(slug: string): FirstAidGuide {
  return {
    ...guide(slug),
    published: true,
    review: { status: "reviewed", reviewedOn: "2026-11-01" },
  };
}

describe("FirstAidGuideArticle", () => {
  it.each(SLUGS)(
    "%s: one h1, every contract anchor, numbered steps, a 194 call and no serious axe issue",
    async (slug) => {
      const { container } = render(
        <FirstAidGuideArticle guide={guide(slug)} preview />,
      );

      expect(screen.getAllByRole("heading", { level: 1 })).toHaveLength(1);
      expect(
        screen.getByRole("heading", { level: 1, name: guide(slug).title }),
      ).toBeInTheDocument();

      for (const anchor of Object.values(FIRST_AID_ANCHORS)) {
        expect(
          container.querySelectorAll(`[id="${anchor}"]`),
          `#${anchor}`,
        ).toHaveLength(1);
      }
      for (const section of guide(slug).sections) {
        expect(container.querySelector(`[id="${section.id}"]`)).not.toBeNull();
      }

      // Steps are real ordered lists, one per step section.
      const lists = container.querySelectorAll(
        `#${FIRST_AID_ANCHORS.steps} ol`,
      );
      expect(lists).toHaveLength(guide(slug).sections.length);
      expect(lists[0].querySelectorAll(":scope > li")).toHaveLength(
        guide(slug).sections[0].steps.length,
      );

      const call = screen.getAllByRole("link", { name: /194/ });
      expect(call[0]).toHaveAttribute("href", "tel:194");
      expect(screen.getByRole("link", { name: /112/ })).toHaveAttribute(
        "href",
        "tel:112",
      );

      expect(await seriousA11yViolations(container)).toEqual([]);
    },
  );

  it("leads a life-threatening guide with „Прво повикајте 194“", () => {
    render(<FirstAidGuideArticle guide={guide("kpr-vozrasni")} preview />);

    const box = screen
      .getByRole("heading", { level: 2, name: FIRST_AID_COPY.callFirstTitle })
      .closest("section")!;
    expect(box).toHaveAttribute("id", FIRST_AID_ANCHORS.call);
    expect(within(box).getByRole("link", { name: /194/ })).toHaveAttribute(
      "href",
      "tel:194",
    );
  });

  it("lists when to call 194 at the top of a guide that does not always need it", () => {
    render(<FirstAidGuideArticle guide={guide("krvarenje-od-nos")} preview />);

    const box = screen
      .getByRole("heading", { level: 2, name: FIRST_AID_COPY.callIfTitle })
      .closest("section")!;
    for (const line of guide("krvarenje-od-nos").escalate) {
      expect(within(box).getByText(line)).toBeInTheDocument();
    }
  });

  it("offers the toxicology clinic as a tap-to-call link in the poisoning call box", () => {
    render(<FirstAidGuideArticle guide={guide("truenje")} preview />);

    const box = screen
      .getByRole("heading", { level: 2, name: FIRST_AID_COPY.callFirstTitle })
      .closest("section")!;
    expect(
      within(box).getByRole("link", { name: /02 3147 168/ }),
    ).toHaveAttribute("href", "tel:+38923147168");
    // 194 stays first.
    expect(within(box).getAllByRole("link")[0]).toHaveAttribute(
      "href",
      "tel:194",
    );
  });

  it.each(["zadavuvanje", "zadavuvanje-bebe"])(
    "%s: the call box says to call after the first unsuccessful round, not after three",
    (slug) => {
      render(<FirstAidGuideArticle guide={guide(slug)} preview />);

      const box = screen
        .getByRole("heading", { level: 2, name: FIRST_AID_COPY.callIfTitle })
        .closest("section")!;
      expect(within(box).getByText(/првиот круг/)).toBeInTheDocument();
      expect(within(box).queryByText(/три круга/)).toBeNull();
    },
  );

  it("shows a draft in staff preview with no draft label and cites its sources with the access date", () => {
    render(<FirstAidGuideArticle guide={guide("truenje")} preview />);

    expect(screen.queryByRole("status")).toBeNull();
    expect(
      screen.queryByText(/Нацрт|Преглед за тимот|Преглед на нацрти/),
    ).toBeNull();
    expect(
      screen.queryByText(new RegExp(FIRST_AID_COPY.reviewDone)),
    ).toBeNull();
    const sources = screen
      .getByRole("heading", { name: FIRST_AID_COPY.sources })
      .closest("section")!;
    expect(
      within(sources).getByRole("link", { name: /NHS: Poisoning/ }),
    ).toHaveAttribute("href", "https://www.nhs.uk/conditions/poisoning/");
    expect(sources).toHaveTextContent("пристапено 7 октомври 2026");
  });

  it("ends a guide with no generic disclaimer block", () => {
    render(<FirstAidGuideArticle guide={published("truenje")} />);

    expect(screen.queryByText(/не замена за обука/)).toBeNull();
    // The guide's own call content stays.
    expect(screen.getAllByRole("link", { name: /194/ }).length).toBeGreaterThan(
      0,
    );
  });

  it("shows the clinician sign-off, not the draft label, once published", () => {
    render(<FirstAidGuideArticle guide={published("truenje")} />);

    expect(screen.queryByRole("status")).toBeNull();
    expect(
      screen.queryByText(/Нацрт|Преглед за тимот|Преглед на нацрти/),
    ).toBeNull();
    expect(
      screen.getByText(new RegExp(FIRST_AID_COPY.reviewDone)),
    ).toBeInTheDocument();
  });

  it("links related guides only when they are visible to the viewer", () => {
    render(<FirstAidGuideArticle guide={published("truenje")} />);

    // Every related guide is still a draft: no link a visitor would 404 on.
    expect(
      screen.queryByRole("heading", { name: FIRST_AID_COPY.related }),
    ).toBeNull();
  });
});

describe("FirstAidIndex", () => {
  it("groups every guide by situation for staff, without draft labels", async () => {
    const { container } = render(<FirstAidIndex guides={FIRST_AID_GUIDES} />);

    expect(
      screen
        .getAllByRole("heading", { level: 2 })
        .map((heading) => heading.textContent),
    ).toEqual(FIRST_AID_GROUPS.map((group) => group.title));
    for (const item of FIRST_AID_GUIDES) {
      expect(
        screen.getByRole("link", {
          name: new RegExp(escapeRegExp(item.title)),
        }),
      ).toHaveAttribute("href", `/prva-pomos/${item.slug}`);
    }
    expect(
      screen.queryByText(/Нацрт|Преглед за тимот|Преглед на нацрти/),
    ).toBeNull();
    expect(screen.queryByRole("status")).toBeNull();
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("shows the public a calm holding note, no 194/112 banner or disclaimer, while nothing is published", async () => {
    const { container } = render(<FirstAidIndex guides={[]} />);

    expect(screen.getByText(FIRST_AID_COPY.emptyIndex)).toBeInTheDocument();
    expect(screen.queryByRole("heading", { level: 2 })).toBeNull();
    expect(screen.queryByRole("link", { name: /194/ })).toBeNull();
    expect(screen.queryByText(/не замена за обука/)).toBeNull();
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("lists only the guides it is given, without a draft label when published", () => {
    render(<FirstAidIndex guides={[published("izgorenici")]} />);

    expect(screen.getAllByRole("heading", { level: 2 })).toHaveLength(1);
    expect(
      screen.getByRole("link", { name: /Изгореници/ }),
    ).toBeInTheDocument();
    expect(
      screen.queryByText(/Нацрт|Преглед за тимот|Преглед на нацрти/),
    ).toBeNull();
  });
});

describe("FirstAidIllustration", () => {
  it.each(FIRST_AID_ILLUSTRATION_IDS.map((id) => [id] as const))(
    "%s is decorative inline SVG",
    (id) => {
      const { container } = render(<FirstAidIllustration id={id} />);
      const svg = container.querySelector("svg")!;

      expect(svg).toHaveAttribute("aria-hidden", "true");
      expect(svg).toHaveAttribute("data-first-aid-illustration", id);
      expect(svg.querySelector("image, use, foreignObject")).toBeNull();
    },
  );
});
