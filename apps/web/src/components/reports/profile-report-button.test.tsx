import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ProfileReportButton } from "@/components/reports/profile-report-button";
import type { ProfileReportSubject } from "@/lib/api/profile-reports";
import { t } from "@/i18n/t";
import { altchaControl } from "../../../test/altcha";
import { seriousA11yViolations } from "../../../test/axe";

function mockFetch(status: number, body: unknown) {
  const fetchMock = vi.fn().mockResolvedValue(
    new Response(JSON.stringify(body), {
      status,
      headers: { "Content-Type": "application/json" },
    }),
  );
  vi.stubGlobal("fetch", fetchMock);
  return fetchMock;
}

async function openSheet(
  subject: ProfileReportSubject = "doctor",
  compact = false,
) {
  const user = userEvent.setup();
  render(
    <ProfileReportButton
      subject={subject}
      slug="ana-petrovska"
      compact={compact}
    />,
  );
  await user.click(
    screen.getByRole("button", { name: t("profileReports.action") }),
  );
  return { user, dialog: screen.getByRole("dialog") };
}

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("ProfileReportButton", () => {
  it("is a flag button for everyone, signed in or not, that opens a labelled modal", async () => {
    const { dialog } = await openSheet();

    expect(dialog).toHaveAccessibleName(t("profileReports.dialogTitle"));
    expect(dialog).toHaveAttribute("aria-modal", "true");
    expect(within(dialog).getAllByRole("radio")).toHaveLength(5);
    expect(
      within(dialog).getByRole("radio", {
        name: t("profileReports.reasons.no_longer_here"),
      }),
    ).toBeInTheDocument();
    expect(await seriousA11yViolations(document.body)).toEqual([]);
  });

  it("has an icon-only variant for a slot beside the title", async () => {
    render(<ProfileReportButton subject="doctor" slug="ana" compact />);

    const button = screen.getByRole("button", {
      name: t("profileReports.action"),
    });
    expect(button).toHaveAttribute("aria-haspopup", "dialog");
    expect(button).not.toHaveTextContent(t("profileReports.action"));
  });

  it("words „no longer here“ for a place as closed or moved", async () => {
    const { dialog } = await openSheet("pharmacy");

    expect(
      within(dialog).getByRole("radio", {
        name: t("profileReports.reasons.no_longer_here_place"),
      }),
    ).toBeInTheDocument();
  });

  it("asks for a reason before sending anything", async () => {
    const fetchMock = mockFetch(201, {});
    const { user, dialog } = await openSheet();

    await user.click(
      within(dialog).getByRole("button", { name: t("profileReports.submit") }),
    );

    expect(fetchMock).not.toHaveBeenCalled();
    expect(dialog).toHaveTextContent(t("profileReports.reasonRequired"));
    expect(within(dialog).getAllByRole("radio")[0]).toHaveFocus();
  });

  it("posts the profile, reason, note, solved ALTCHA and empty honeypot, then confirms", async () => {
    const fetchMock = mockFetch(201, {
      data: { status: "received", message: "Пријавата е примена." },
    });
    const { user, dialog } = await openSheet("facility");

    await user.click(
      within(dialog).getByRole("radio", {
        name: t("profileReports.reasons.fake_profile"),
      }),
    );
    await user.type(
      within(dialog).getByRole("textbox", {
        name: t("profileReports.noteLabel"),
      }),
      "  Ваква установа не постои.  ",
    );
    await user.click(
      within(dialog).getByRole("button", { name: t("profileReports.submit") }),
    );

    expect(fetchMock).toHaveBeenCalledWith(
      "/api/profile-reports",
      expect.objectContaining({ method: "POST" }),
    );
    expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({
      subject: "facility",
      slug: "ana-petrovska",
      reason: "fake_profile",
      note: "Ваква установа не постои.",
      altcha: "test-altcha-1",
      website: "",
    });
    // The invisible widget is set up without tracking or branding.
    expect(altchaControl.configure).toHaveBeenCalledWith(
      expect.objectContaining({
        challenge: "/api/altcha/challenge",
        display: "invisible",
        hideFooter: true,
        humanInteractionSignature: false,
        language: "mk",
      }),
    );
    const status = await within(dialog).findByRole("status");
    expect(status).toHaveTextContent("Пријавата е примена.");
    await waitFor(() => expect(status).toHaveFocus());
  });

  it("does not send when the proof of work cannot be solved, and says so", async () => {
    altchaControl.fail = true;
    const fetchMock = mockFetch(201, {});
    const { user, dialog } = await openSheet();

    await user.click(within(dialog).getAllByRole("radio")[1]);
    await user.click(
      within(dialog).getByRole("button", { name: t("profileReports.submit") }),
    );

    expect(fetchMock).not.toHaveBeenCalled();
    expect(await within(dialog).findByRole("alert")).toHaveTextContent(
      t("altcha.failed"),
    );
  });

  it("sends a fresh ALTCHA solution on a resubmit after a refusal", async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ message: "Обидете се повторно." }), {
          status: 422,
          headers: { "Content-Type": "application/json" },
        }),
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: { message: "Примено." } }), {
          status: 201,
          headers: { "Content-Type": "application/json" },
        }),
      );
    vi.stubGlobal("fetch", fetchMock);
    const { user, dialog } = await openSheet();

    await user.click(within(dialog).getAllByRole("radio")[0]);
    await user.click(
      within(dialog).getByRole("button", { name: t("profileReports.submit") }),
    );
    expect(await within(dialog).findByRole("alert")).toHaveTextContent(
      "Обидете се повторно.",
    );

    await user.click(
      within(dialog).getByRole("button", { name: t("profileReports.submit") }),
    );
    await within(dialog).findByRole("status");

    const sent = fetchMock.mock.calls.map(
      (call) => JSON.parse(call[1].body).altcha,
    );
    expect(sent).toEqual(["test-altcha-1", "test-altcha-2"]);
  });

  it("explains a rate limit", async () => {
    mockFetch(429, { message: "Too Many Attempts." });
    const { user, dialog } = await openSheet();

    await user.click(within(dialog).getAllByRole("radio")[2]);
    await user.click(
      within(dialog).getByRole("button", { name: t("profileReports.submit") }),
    );

    expect(await within(dialog).findByRole("alert")).toHaveTextContent(
      t("profileReports.throttled"),
    );
  });
});
