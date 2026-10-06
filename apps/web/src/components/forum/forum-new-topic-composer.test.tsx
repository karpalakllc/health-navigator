import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { ForumNewTopicComposer } from "@/components/forum/forum-new-topic-composer";
import type { ForumCategory } from "@/lib/api/forum";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import {
  mockFetch,
  mockFetchNetworkError,
  requestBody,
} from "../../../test/fetch";
import { router } from "../../../test/next-navigation";

const categories: ForumCategory[] = [
  { slug: "srce", name: "Срце и крвни садови", description: null },
  { slug: "koza", name: "Кожа", description: null },
];

const settings = {
  forum_rules_enabled: false,
  forum_rules_title: null,
  forum_rules_body: null,
};

function renderComposer(defaultCategorySlug?: string) {
  return render(
    <ForumNewTopicComposer
      categories={categories}
      defaultCategorySlug={defaultCategorySlug}
      settings={settings}
    />,
  );
}

function submitButton() {
  return screen.getByRole("button", { name: t("forum.topicSubmitModeration") });
}

async function fill(user: ReturnType<typeof userEvent.setup>) {
  await user.type(
    screen.getByLabelText(t("common.title")),
    "Висок притисок наутро",
  );
  await user.type(
    screen.getByLabelText(t("common.message")),
    "Дали некој има искуство со мерење на притисок наутро?",
  );
}

async function consentAll(user: ReturnType<typeof userEvent.setup>) {
  for (const label of [
    t("forum.consentRules"),
    t("forum.consentNoDiagnosis"),
    t("forum.consentEmergency"),
  ]) {
    await user.click(screen.getByRole("checkbox", { name: label }));
  }
}

describe("ForumNewTopicComposer", () => {
  it("labels the fields and enforces their minimum lengths", () => {
    renderComposer();

    const title = screen.getByLabelText(t("common.title"));
    const body = screen.getByLabelText(t("common.message"));
    expect(title).toBeRequired();
    expect(title).toHaveAttribute("minlength", "5");
    expect(body).toBeRequired();
    expect(body).toHaveAttribute("minlength", "20");
    expect(
      screen.getByRole("group", { name: t("forum.consentLegend") }),
    ).toBeInTheDocument();
  });

  it("preselects a known default category and ignores an unknown one", () => {
    const { unmount } = renderComposer("koza");
    expect(screen.getByLabelText(t("forum.categories"))).toHaveValue("koza");
    unmount();

    renderComposer("nepostoi");
    expect(screen.getByLabelText(t("forum.categories"))).toHaveValue("srce");
  });

  it("keeps submit disabled until all three consents are given", async () => {
    const user = userEvent.setup();
    renderComposer();

    expect(submitButton()).toBeDisabled();
    await user.click(
      screen.getByRole("checkbox", { name: t("forum.consentRules") }),
    );
    await user.click(
      screen.getByRole("checkbox", { name: t("forum.consentNoDiagnosis") }),
    );
    expect(submitButton()).toBeDisabled();
    await user.click(
      screen.getByRole("checkbox", { name: t("forum.consentEmergency") }),
    );
    expect(submitButton()).toBeEnabled();
  });

  it("submits for moderation and sends the author to their forum page", async () => {
    const fetch = mockFetch({
      status: 201,
      body: { data: { status: "pending" } },
    });
    const user = userEvent.setup();
    renderComposer("koza");

    await fill(user);
    await consentAll(user);
    await user.click(submitButton());

    await waitFor(() =>
      expect(router.push).toHaveBeenCalledWith("/account/forum"),
    );
    expect(requestBody(fetch)).toEqual({
      categorySlug: "koza",
      title: "Висок притисок наутро",
      body: "Дали некој има искуство со мерење на притисок наутро?",
      accepted_community_rules: true,
    });
  });

  it("shows an API refusal as an alert and stays on the page", async () => {
    mockFetch({ status: 429, body: { message: "Премногу нови теми." } });
    const user = userEvent.setup();
    renderComposer();

    await fill(user);
    await consentAll(user);
    await user.click(submitButton());

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Премногу нови теми.",
    );
    expect(router.push).not.toHaveBeenCalled();
  });

  it("reports a network failure", async () => {
    mockFetchNetworkError();
    const user = userEvent.setup();
    renderComposer();

    await fill(user);
    await consentAll(user);
    await user.click(submitButton());

    expect(await screen.findByRole("alert")).toHaveTextContent(
      t("forum.topicErrorRetry"),
    );
  });

  it("previews the draft on request", async () => {
    const user = userEvent.setup();
    renderComposer();

    await user.type(screen.getByLabelText(t("common.title")), "Наслов на тема");
    await user.click(
      screen.getByRole("button", { name: t("forum.showPreview") }),
    );

    expect(
      screen.getByRole("heading", { name: "Наслов на тема" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: t("forum.hidePreview") }),
    ).toBeInTheDocument();
  });

  it("has no serious accessibility violations", async () => {
    const { container } = renderComposer();

    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
