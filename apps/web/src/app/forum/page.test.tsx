import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

const empty = {
  data: [],
  meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
};

vi.mock("@/lib/api/settings", () => ({
  fetchPublicSettings: async () => ({
    public_forum: true,
    forum_rules_enabled: true,
    forum_rules_title: "",
    forum_rules_body: "",
  }),
}));
vi.mock("@/lib/auth/session", () => ({
  getSessionToken: async () => null,
}));
vi.mock("@/lib/api/forum", () => ({
  fetchForumCategories: async () => [],
  fetchForumRecentTopics: async () => empty,
  fetchForumTopicSearch: async () => empty,
  fetchForumUnansweredTopics: async () => empty,
}));

import ForumPage from "@/app/forum/page";
import { t } from "@/i18n/t";

describe("/forum", () => {
  it("renders without the 194/112 safety box or the „не заменува“ subtitle sentence", async () => {
    render(await ForumPage({ searchParams: Promise.resolve({}) }));

    expect(
      screen.getByRole("heading", { level: 1, name: t("forum.title") }),
    ).toBeInTheDocument();
    expect(
      screen.getByText("Модерирани дискусии за општи здравствени теми."),
    ).toBeInTheDocument();

    expect(screen.queryByText(/Не заменува професионална нега/)).toBeNull();
    expect(screen.queryByText(/не за медицински совет/)).toBeNull();
    expect(screen.queryByText(/Итно\?/)).toBeNull();
    expect(screen.queryByText(/194|112/)).toBeNull();
    expect(screen.queryByRole("link", { name: "194" })).toBeNull();
  });
});
