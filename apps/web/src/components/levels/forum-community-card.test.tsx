import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { ForumCommunityCard } from "@/components/levels/forum-community-card";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

describe("ForumCommunityCard (forum hub → „Заедница“)", () => {
  it("names the page and links to /community", async () => {
    const { container } = render(<ForumCommunityCard />);

    expect(
      screen.getByRole("heading", { level: 2, name: t("levels.pageTitle") }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: t("levels.forumHubLink") }),
    ).toHaveAttribute("href", "/community");
    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
