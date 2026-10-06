import { fireEvent, render, screen, waitFor } from "@testing-library/react";
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

function consentBox() {
  return screen.getByRole("checkbox", { name: t("forum.consent") });
}

async function consentAll(user: ReturnType<typeof userEvent.setup>) {
  await user.click(consentBox());
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
    expect(screen.getAllByRole("checkbox")).toHaveLength(1);
    expect(consentBox()).toBeRequired();
  });

  it("preselects a known default category and ignores an unknown one", () => {
    const { unmount } = renderComposer("koza");
    expect(screen.getByLabelText(t("forum.categories"))).toHaveValue("koza");
    unmount();

    renderComposer("nepostoi");
    expect(screen.getByLabelText(t("forum.categories"))).toHaveValue("srce");
  });

  it("asks for one consent covering rules, diagnosis and emergencies", () => {
    renderComposer();

    const label = consentBox().closest("label");
    expect(label).toHaveTextContent("правилата на заедницата");
    expect(label).toHaveTextContent("не дава дијагноза или третман");
    expect(label).toHaveTextContent("194 или 112");
  });

  it("keeps submit disabled until the consent is ticked", async () => {
    const user = userEvent.setup();
    renderComposer();

    expect(submitButton()).toBeDisabled();
    await user.click(consentBox());
    expect(submitButton()).toBeEnabled();
    await user.click(consentBox());
    expect(submitButton()).toBeDisabled();
  });

  it("links the missing-consent error to the checkbox", async () => {
    const user = userEvent.setup();
    renderComposer();

    expect(consentBox()).not.toHaveAttribute("aria-describedby");
    // The button is disabled, so submit the form directly (e.g. Enter key
    // in a browser that ignores the disabled default button).
    fireEvent.submit(submitButton().closest("form")!);

    const error = await screen.findByRole("alert");
    expect(error).toHaveTextContent(t("forum.consentRequired"));
    expect(consentBox()).toHaveAttribute("aria-invalid", "true");
    expect(consentBox()).toHaveAccessibleDescription(
      t("forum.consentRequired"),
    );

    await user.click(consentBox());
    expect(consentBox()).not.toHaveAttribute("aria-describedby");
  });

  it("keeps the disabled submit legible instead of fading white on coral", () => {
    renderComposer();

    const classes = submitButton().className.split(/\s+/);
    // opacity-60 left white text on pale coral at about 2:1.
    expect(classes).not.toContain("disabled:opacity-60");
    expect(classes).toEqual(
      expect.arrayContaining([
        "disabled:bg-muted",
        "disabled:text-secondary-foreground",
      ]),
    );
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
