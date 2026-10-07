import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const state = vi.hoisted(() => ({ anyPublished: false }));

vi.mock("@/content/first-aid", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/content/first-aid")>()),
  hasPublishedFirstAidGuides: () => state.anyPublished,
}));

import { SiteFooterContent } from "@/components/layout/site-footer";
import { publicSettingsDefaults } from "@/lib/api/public-settings";

beforeEach(() => {
  state.anyPublished = false;
});

describe("SiteFooter „Прва помош“ link", () => {
  it("is absent while no guide is published (it would lead to a holding note)", () => {
    render(<SiteFooterContent settings={publicSettingsDefaults} />);

    expect(screen.queryByRole("link", { name: "Прва помош" })).toBeNull();
  });

  it("links the index once a guide is published", () => {
    state.anyPublished = true;
    render(<SiteFooterContent settings={publicSettingsDefaults} />);

    expect(screen.getByRole("link", { name: "Прва помош" })).toHaveAttribute(
      "href",
      "/prva-pomos",
    );
  });
});
