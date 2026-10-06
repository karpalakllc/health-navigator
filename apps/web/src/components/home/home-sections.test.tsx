import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { CommunityTopicCard } from "@/components/home/community-topic-card";
import { HomeDirectoryTiles } from "@/components/home/home-directory-tiles";
import { HomeGuidanceCard } from "@/components/home/home-guidance-card";
import { HomeFeaturedDoctorsRail } from "@/components/home/home-featured-doctors";
import { HomeHero } from "@/components/home/home-hero";
import { buildHeroStats, buildHomeTiles } from "@/components/home/home-tiles";
import { HomeForumTransparency } from "@/components/layout/home-forum-transparency";
import { HomeHowItWorksSection } from "@/components/layout/home-how-it-works-section";
import { HEADER_SEARCH_INPUT_ID } from "@/components/layout/header-search";
import type { ForumTopicSearchItem } from "@/lib/api/forum";
import type { DoctorListItem } from "@/lib/api/types";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

describe("HomeHowItWorksSection (owner-kept „Како функционира“)", () => {
  it("is a labelled section with the intro and four ordered steps", async () => {
    const { container } = render(<HomeHowItWorksSection />);

    const section = screen.getByRole("region", {
      name: t("home.howItWorksTitle"),
    });
    expect(
      within(section).getByRole("heading", {
        level: 2,
        name: t("home.howItWorksTitle"),
      }),
    ).toBeInTheDocument();
    expect(
      within(section).getByText(t("home.howItWorksDescription")),
    ).toBeInTheDocument();

    const steps = within(section).getAllByRole("listitem");
    expect(steps).toHaveLength(4);
    expect(steps[0].closest("ol")).not.toBeNull();
    expect(
      steps.map(
        (step) => within(step).getByRole("heading", { level: 3 }).textContent,
      ),
    ).toEqual([
      t("home.howStep1Title"),
      t("home.howStep2Title"),
      t("home.howStep3Title"),
      t("home.howStep4Title"),
    ]);
    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});

describe("HomeForumTransparency (owner-kept forum band + „Зошто ова е важно“)", () => {
  it("shows the forum band with its three points and the „Отвори форум“ link", async () => {
    const { container } = render(<HomeForumTransparency />);

    const forum = screen.getByRole("region", {
      name: t("home.forumBandTitle"),
    });
    const points = within(forum).getAllByRole("listitem");
    expect(points.map((p) => p.textContent)).toEqual([
      t("home.forumPointModerated"),
      t("home.forumPointWarnings"),
      t("home.forumPointClarity"),
    ]);
    expect(
      within(forum).getByRole("link", { name: t("home.communityForumCta") }),
    ).toHaveAttribute("href", "/forum");

    const why = screen.getByRole("region", {
      name: t("home.transparencyTitle"),
    });
    expect(
      within(why)
        .getAllByRole("heading", { level: 3 })
        .map((h) => h.textContent),
    ).toEqual([
      t("home.transparencyItem1Title"),
      t("home.transparencyItem2Title"),
      t("home.transparencyItem3Title"),
    ]);
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("drops the forum band (and its link) when the forum module is off", () => {
    render(<HomeForumTransparency showForum={false} />);

    expect(
      screen.queryByRole("region", { name: t("home.forumBandTitle") }),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole("link", { name: t("home.communityForumCta") }),
    ).not.toBeInTheDocument();
    expect(
      screen.getByRole("region", { name: t("home.transparencyTitle") }),
    ).toBeInTheDocument();
  });
});

describe("HomeHero", () => {
  it("has the h1, a GET search form to /search and the quick links", async () => {
    const { container } = render(
      <HomeHero
        quickLinks={[
          { href: "/doctors?specialty=kardiologija", label: "Кардиологија" },
        ]}
      />,
    );

    expect(
      screen.getByRole("heading", { level: 1, name: t("home.heroHeading") }),
    ).toBeInTheDocument();
    const form = screen.getByRole("search");
    expect(form).toHaveAttribute("action", "/search");
    expect(form).toHaveAttribute("method", "get");
    expect(
      within(form).getByRole("searchbox", { name: t("nav.searchWhat") }),
    ).toHaveAttribute("name", "q");
    expect(
      within(form).getByRole("textbox", { name: t("nav.searchWhere") }),
    ).toHaveAttribute("name", "city");

    const quick = screen.getByRole("list", { name: t("home.quickLinksAria") });
    expect(
      within(quick).getByRole("link", { name: "Кардиологија" }),
    ).toHaveAttribute("href", "/doctors?specialty=kardiologija");
    // The phone row scrolls and so clips: padding keeps the focus ring whole.
    expect(quick.className.split(/\s+/)).toEqual(
      expect.arrayContaining(["py-2.5", "-mb-2.5"]),
    );
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("opens with the value line and a decorative illustration, not the wordmark", () => {
    const { container } = render(
      <HomeHero
        quickLinks={[]}
        stats={[
          { icon: "stethoscope", label: "14 лекари" },
          { icon: "building", label: "6 установи" },
        ]}
      />,
    );

    const stats = screen.getByRole("list", { name: t("home.heroStatsAria") });
    expect(
      within(stats)
        .getAllByRole("listitem")
        .map((item) => item.textContent),
    ).toEqual(["14 лекари", "6 установи"]);
    // The old „Здравје360“ eyebrow repeated the logo right above it.
    expect(screen.queryByText(t("nav.wordmark"))).toBeNull();

    const art = container.querySelector("svg[data-hero-illustration]");
    expect(art).toHaveAttribute("aria-hidden", "true");
    expect(art).toHaveAttribute("focusable", "false");
    // A fixed viewBox reserves the box before paint (no layout shift).
    expect(art).toHaveAttribute("viewBox", "0 0 480 360");
  });

  it("leaves the value line out when no totals are known", () => {
    render(<HomeHero quickLinks={[]} />);
    expect(
      screen.queryByRole("list", { name: t("home.heroStatsAria") }),
    ).toBeNull();
  });

  it("the desktop prompt moves focus into the header search", async () => {
    render(
      <>
        <input id={HEADER_SEARCH_INPUT_ID} aria-label="header q" />
        <HomeHero quickLinks={[]} />
      </>,
    );
    // jsdom has no layout: make the header input count as rendered.
    const header = screen.getByLabelText("header q");
    Object.defineProperty(header, "offsetParent", { value: document.body });

    const prompt = screen.getByRole("link", {
      name: t("home.heroSearchPrompt"),
    });
    expect(prompt).toHaveAttribute("href", "/search");
    await userEvent.click(prompt);
    expect(header).toHaveFocus();
  });
});

describe("HomeDirectoryTiles", () => {
  it("links each tile, named by its label and count", () => {
    render(
      <HomeDirectoryTiles
        tiles={[
          {
            href: "/doctors",
            label: t("nav.doctors"),
            icon: "stethoscope",
            sub: "10 профили",
            feature: true,
          },
          { href: "/facilities", label: t("nav.facilities"), icon: "building" },
        ]}
      />,
    );

    expect(
      screen.getByRole("heading", { level: 2, name: t("home.tilesTitle") }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: `${t("nav.doctors")} 10 профили` }),
    ).toHaveAttribute("href", "/doctors");
    expect(
      screen.getByRole("link", { name: t("nav.facilities") }),
    ).toHaveAttribute("href", "/facilities");
  });

  it("paints exactly the tiles flagged `feature` apricot", () => {
    render(<HomeDirectoryTiles tiles={buildHomeTiles(allModules, {})} />);

    const featured = screen
      .getAllByRole("link")
      .filter((link) => link.className.split(/\s+/).includes("bg-apricot"))
      .map((link) => link.getAttribute("href"));
    expect(featured).toEqual(["/doctors", "/forum"]);
  });
});

const allModules = {
  pharmacies: true,
  products: true,
  guidance: true,
  forum: true,
};

describe("buildHomeTiles (owner: highlight Лекари and Форум)", () => {
  const featuredHrefs = (tiles: ReturnType<typeof buildHomeTiles>) =>
    tiles.filter((tile) => tile.feature).map((tile) => tile.href);

  it("features Лекари and Форум, not Насоки за симптоми", () => {
    const tiles = buildHomeTiles(allModules, { doctors: 14, forumTopics: 2 });

    expect(tiles.map((tile) => tile.href)).toEqual([
      "/doctors",
      "/facilities",
      "/pharmacies",
      "/products",
      "/guidance",
      "/forum",
    ]);
    expect(featuredHrefs(tiles)).toEqual(["/doctors", "/forum"]);
    expect(tiles.find((tile) => tile.href === "/guidance")?.feature).toBe(
      false,
    );
    expect(tiles[0].sub).toBe("14 профили");
  });

  it("keeps the highlight on the destination when modules shift the grid", () => {
    // Pharmacies, products and guidance off: Форум moves to the third slot
    // but stays highlighted; nothing else picks the highlight up.
    const tiles = buildHomeTiles(
      { pharmacies: false, products: false, guidance: false, forum: true },
      {},
    );
    expect(tiles.map((tile) => tile.href)).toEqual([
      "/doctors",
      "/facilities",
      "/forum",
    ]);
    expect(featuredHrefs(tiles)).toEqual(["/doctors", "/forum"]);

    expect(
      featuredHrefs(buildHomeTiles({ ...allModules, forum: false }, {})),
    ).toEqual(["/doctors"]);
  });
});

describe("buildHeroStats", () => {
  it("lists only the totals that are known, in Macedonian plural forms", () => {
    expect(
      buildHeroStats({ doctors: 14, facilities: 1, pharmacies: undefined }).map(
        (stat) => stat.label,
      ),
    ).toEqual(["14 лекари", "1 установа"]);
    expect(buildHeroStats({})).toEqual([]);
  });
});

const doctor: DoctorListItem = {
  slug: "elena-dimitrova",
  full_name: "д-р Елена Димитрова",
  title: null,
  subspecialty: null,
  city: "Битола",
  avatar_url: null,
  years_experience: null,
  accepts_new_patients: true,
  is_featured: true,
  is_sponsored: false,
  primary_specialty: { slug: "ortopedija", name: "Ортопедија" },
  primary_facility: null,
  review_summary: { count: 2, average_rating: 4.5 },
} as DoctorListItem;

describe("HomeFeaturedDoctorsRail", () => {
  const plain: DoctorListItem = {
    ...doctor,
    slug: "ana-petrovska",
    full_name: "д-р Ана Петровска",
    is_featured: false,
  };

  it("gives each doctor a card: band, identity, rating, own-line tag, CTA", async () => {
    const { container } = render(
      <HomeFeaturedDoctorsRail doctors={[doctor, plain]} />,
    );

    const section = screen.getByRole("region", {
      name: t("home.featuredDoctors"),
    });
    const cards = within(section).getAllByRole("article");
    expect(cards).toHaveLength(2);

    const [featured, other] = cards;
    // „Истакнат“ sits in the apricot band, as on /doctors — only for a
    // featured doctor.
    const band = featured.querySelector("[data-featured-band]");
    expect(band).not.toBeNull();
    expect(
      within(band as HTMLElement).getByText(t("ui.featured")),
    ).toBeVisible();
    expect(other.querySelector("[data-featured-band]")).toBeNull();
    expect(within(other).queryByText(t("ui.featured"))).toBeNull();

    for (const card of cards) {
      // The accepting tag is on its own row, never in the photo row
      // (where it overflowed the card at 390px).
      const identity = card.querySelector("[data-doctor-identity]")!;
      const tag = within(card).getByText(t("doctors.acceptingPatients"));
      expect(identity).not.toContainElement(tag);
      expect(card.querySelector("[data-doctor-tags]")).toContainElement(tag);
      expect(
        within(identity as HTMLElement).getByRole("heading", { level: 3 }),
      ).toBeInTheDocument();
    }

    // A clear but secondary CTA: the outlined pill, not the beige blob.
    const cta = within(featured).getByRole("link", {
      name: `${t("doctors.viewProfile")}: д-р Елена Димитрова`,
    });
    expect(cta).toHaveAttribute("href", "/doctors/elena-dimitrova");
    expect(cta.className.split(/\s+/)).toContain("btn-secondary");
    expect(cta.className.split(/\s+/)).not.toContain("btn-soft");

    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("lays the rail out as a snapping row with room for shadows", () => {
    render(<HomeFeaturedDoctorsRail doctors={[doctor, plain]} />);

    const list = screen.getAllByRole("list")[0];
    const classes = list.className.split(/\s+/);
    expect(classes).toEqual(
      expect.arrayContaining(["snap-x", "snap-mandatory", "pb-6", "pt-2"]),
    );
    for (const item of within(list).getAllByRole("listitem")) {
      expect(item.className.split(/\s+/)).toContain("snap-start");
    }
  });

  it("renders nothing without doctors", () => {
    const { container } = render(<HomeFeaturedDoctorsRail doctors={[]} />);
    expect(container).toBeEmptyDOMElement();
  });
});

describe("HomeGuidanceCard", () => {
  it("starts the guidance without repeating the emergency numbers", () => {
    render(<HomeGuidanceCard />);

    expect(
      screen.getByRole("heading", { level: 2, name: t("home.guideTitle") }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: t("home.guideCta") }),
    ).toHaveAttribute("href", "/guidance");
    // 194/112 belong to the guidance flow and the footer, not this teaser.
    expect(document.querySelector('a[href^="tel:"]')).toBeNull();
  });
});

describe("CommunityTopicCard", () => {
  const topic: ForumTopicSearchItem = {
    slug: "pritisok",
    title: "Колку често да мерам притисок дома?",
    author_name: "Ана М.",
    replies_count: 4,
    last_post_at: null,
    published_at: null,
    category: { slug: "srce", name: "Срце и крвен притисок" },
  };

  it("links the title to the topic and reads the reply count", () => {
    render(<CommunityTopicCard topic={topic} />);

    expect(screen.getByRole("link", { name: topic.title })).toHaveAttribute(
      "href",
      "/forum/srce/pritisok",
    );
    expect(screen.getByText("4 одговори")).toBeInTheDocument();
    expect(
      screen.queryByText(t("home.communityUnanswered")),
    ).not.toBeInTheDocument();
  });

  it("marks a topic nobody answered as „Без одговор“", () => {
    render(<CommunityTopicCard topic={{ ...topic, replies_count: 0 }} />);

    expect(screen.getByText(t("home.communityUnanswered"))).toBeInTheDocument();
  });
});
