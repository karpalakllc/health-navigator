import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { ReplyForm } from "@/components/forum/reply-form";
import { t, type MessageKey } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import {
  mockFetch,
  mockFetchNetworkError,
  requestBody,
} from "../../../test/fetch";
import { router } from "../../../test/next-navigation";

/** Required fields carry a visually hidden „(задолжително)“ in their label. */
function requiredLabel(key: MessageKey) {
  return `${t(key)} (${t("ui.required")})`;
}

const BODY = "Благодарам за советот, ќе пробам.";

function renderForm() {
  return render(<ReplyForm categorySlug="srce" topicSlug="pritisok" />);
}

async function reply(body = BODY) {
  const user = userEvent.setup();
  await user.type(screen.getByLabelText(requiredLabel("common.message")), body);
  await user.click(
    screen.getByRole("button", { name: t("forum.replySubmit") }),
  );
}

describe("ReplyForm", () => {
  it("requires a message of at least ten characters", async () => {
    const fetch = mockFetch({ status: 201, body: { data: {} } });
    renderForm();
    const field = screen.getByLabelText(requiredLabel("common.message"));

    expect(field).toBeRequired();
    expect(field).toHaveAttribute("minlength", "10");

    await userEvent
      .setup()
      .click(screen.getByRole("button", { name: t("forum.replySubmit") }));
    expect(fetch).not.toHaveBeenCalled();
  });

  it("says a held reply is waiting for moderation", async () => {
    const fetch = mockFetch({
      status: 201,
      body: { data: { id: 7, status: "pending" } },
    });
    renderForm();
    const region = screen.getByRole("status");

    await reply();

    expect(await screen.findByText(t("forum.replySuccess"))).toBe(region);
    expect(requestBody(fetch)).toEqual({
      categorySlug: "srce",
      topicSlug: "pritisok",
      body: BODY,
    });
    expect(screen.getByLabelText(requiredLabel("common.message"))).toHaveValue(
      "",
    );
    expect(router.refresh).toHaveBeenCalled();
  });

  it("does not claim moderation for a reply the API published at once", async () => {
    // Moderators, and sites with post moderation switched off, get "approved".
    mockFetch({ status: 201, body: { data: { id: 8, status: "approved" } } });
    renderForm();
    const region = screen.getByRole("status");

    await reply();

    expect(await screen.findByText(t("forum.replyPublished"))).toBe(region);
    expect(screen.queryByText(t("forum.replySuccess"))).not.toBeInTheDocument();
  });

  it("shows the locked-topic refusal from the API", async () => {
    mockFetch({
      status: 422,
      body: {
        message: "Темата е заклучена и не прима нови одговори.",
        errors: { topic: ["Темата е заклучена и не прима нови одговори."] },
      },
    });
    renderForm();

    await reply();

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Темата е заклучена и не прима нови одговори.",
    );
    expect(screen.getByRole("status")).toBeEmptyDOMElement();
    // The text is kept so it is not lost.
    expect(screen.getByLabelText(requiredLabel("common.message"))).toHaveValue(
      BODY,
    );
  });

  it.each([
    [401, { message: "Најавете се." }, "Најавете се."],
    [500, {}, t("forum.replyError")],
  ])("shows a %i failure as an alert", async (status, body, text) => {
    mockFetch({ status, body });
    renderForm();

    await reply();

    expect(await screen.findByRole("alert")).toHaveTextContent(text);
  });

  it("reports a network failure", async () => {
    mockFetchNetworkError();
    renderForm();

    await reply();

    expect(await screen.findByRole("alert")).toHaveTextContent(
      t("forum.replyErrorRetry"),
    );
  });

  it("says who is replying, by their public name", () => {
    render(
      <ReplyForm
        categorySlug="srce"
        topicSlug="pritisok"
        viewer={{ name: "Марија К.", initials: "МК" }}
      />,
    );

    expect(
      screen.getByText(t("forum.replyingAs"), { exact: false }),
    ).toHaveTextContent(`${t("forum.replyingAs")} Марија К.`);
  });

  it("counts characters against the API limit and previews the text", async () => {
    const user = userEvent.setup();
    renderForm();
    const field = screen.getByLabelText(requiredLabel("common.message"));

    expect(field).toHaveAttribute("maxlength", "10000");
    expect(field).toHaveAccessibleDescription(
      expect.stringContaining("0 / 10.000"),
    );

    await user.type(field, "Добар совет");
    expect(field).toHaveAccessibleDescription(
      expect.stringContaining("11 / 10.000"),
    );

    const toggle = screen.getByRole("button", { name: t("forum.showPreview") });
    expect(toggle).toHaveAttribute("aria-expanded", "false");
    await user.click(toggle);
    const hide = screen.getByRole("button", { name: t("forum.hidePreview") });
    expect(hide).toHaveAttribute("aria-expanded", "true");
    const preview = document.getElementById(
      hide.getAttribute("aria-controls")!,
    );
    expect(preview).toHaveTextContent("Добар совет");
  });

  it("has no serious accessibility violations", async () => {
    const { container } = renderForm();

    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
