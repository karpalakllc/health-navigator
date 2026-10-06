import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { CommunityTopicCard } from "@/components/home/community-topic-card";
import { HomeDirectoryTiles } from "@/components/home/home-directory-tiles";
import { HomeGuidanceCard } from "@/components/home/home-guidance-card";
import { HomeHero } from "@/components/home/home-hero";
import { HomeForumTransparency } from "@/components/layout/home-forum-transparency";
import { HomeHowItWorksSection } from "@/components/layout/home-how-it-works-section";
import { HEADER_SEARCH_INPUT_ID } from "@/components/layout/header-search";
import type { ForumTopicSearchItem } from "@/lib/api/forum";
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
    expect(await seriousA11yViolations(container)).toEqual([]);
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
});

describe("HomeGuidanceCard", () => {
  it("starts the guidance and offers 194 as a tel: link", () => {
    render(<HomeGuidanceCard />);

    expect(
      screen.getByRole("heading", { level: 2, name: t("home.guideTitle") }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: t("home.guideCta") }),
    ).toHaveAttribute("href", "/guidance");
    expect(screen.getByRole("link", { name: "194" })).toHaveAttribute(
      "href",
      "tel:194",
    );
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
