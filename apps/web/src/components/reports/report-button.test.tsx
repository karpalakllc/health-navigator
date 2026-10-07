import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ReportButton } from "@/components/reports/report-button";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { router } from "../../../test/next-navigation";

const LABEL = "Пријави ја рецензијата од Ана П.";

function renderButton(isLoggedIn = true) {
  return render(
    <ReportButton
      target={{ kind: "review", id: 7 }}
      label={LABEL}
      isLoggedIn={isLoggedIn}
      returnTo="/doctors/ana#reviews"
    />,
  );
}

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

async function openDialog() {
  const user = userEvent.setup();
  renderButton();
  await user.click(screen.getByRole("button", { name: LABEL }));
  return { user, dialog: screen.getByRole("dialog") };
}

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("ReportButton", () => {
  it("sends signed-out visitors to sign in and back", () => {
    renderButton(false);

    const link = screen.getByRole("link", { name: LABEL });
    expect(link).toHaveAttribute(
      "href",
      `/login?redirect=${encodeURIComponent("/doctors/ana#reviews")}`,
    );
    expect(link).toHaveTextContent(t("reports.action"));
  });

  it("opens a labelled modal with the five reasons and no axe violations", async () => {
    const { dialog } = await openDialog();

    expect(dialog).toHaveAccessibleName(t("reports.dialogTitle"));
    expect(dialog).toHaveAttribute("aria-modal", "true");
    const group = within(dialog).getByRole("group", {
      name: t("reports.reasonLegend"),
    });
    expect(within(group).getAllByRole("radio")).toHaveLength(5);
    expect(await seriousA11yViolations(dialog)).toEqual([]);
  });

  it("asks for a reason before sending anything", async () => {
    const fetchMock = mockFetch(201, {});
    const { user, dialog } = await openDialog();

    await user.click(
      within(dialog).getByRole("button", { name: t("reports.submit") }),
    );

    expect(fetchMock).not.toHaveBeenCalled();
    expect(dialog).toHaveTextContent(t("reports.reasonRequired"));
    expect(
      within(dialog).getByRole("radio", { name: t("reports.reasonSpam") }),
    ).toHaveFocus();
  });

  it("posts the target, reason and note, then confirms", async () => {
    const fetchMock = mockFetch(201, { data: { status: "received" } });
    const { user, dialog } = await openDialog();

    await user.click(
      within(dialog).getByRole("radio", {
        name: t("reports.reasonPersonalData"),
      }),
    );
    await user.type(
      within(dialog).getByRole("textbox", { name: t("reports.noteLabel") }),
      "  Има телефонски број.  ",
    );
    await user.click(
      within(dialog).getByRole("button", { name: t("reports.submit") }),
    );

    expect(fetchMock).toHaveBeenCalledWith(
      "/api/reports",
      expect.objectContaining({ method: "POST" }),
    );
    expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({
      target: { kind: "review", id: 7 },
      reason: "personal_data",
      note: "Има телефонски број.",
      altcha: "test-altcha-1",
    });
    expect(await within(dialog).findByRole("status")).toHaveTextContent(
      t("reports.successBody"),
    );
  });

  it("shows a calm message when the API throttles", async () => {
    mockFetch(429, { message: "Too many" });
    const { user, dialog } = await openDialog();

    await user.click(
      within(dialog).getByRole("radio", { name: t("reports.reasonOther") }),
    );
    await user.click(
      within(dialog).getByRole("button", { name: t("reports.submit") }),
    );

    expect(await within(dialog).findByRole("alert")).toHaveTextContent(
      t("reports.throttled"),
    );
  });

  it("sends an expired session to sign-in and back", async () => {
    mockFetch(401, { message: "Unauthenticated." });
    const { user, dialog } = await openDialog();

    await user.click(
      within(dialog).getByRole("radio", { name: t("reports.reasonOther") }),
    );
    await user.click(
      within(dialog).getByRole("button", { name: t("reports.submit") }),
    );

    await vi.waitFor(() =>
      expect(router.push).toHaveBeenCalledWith(
        `/login?redirect=${encodeURIComponent("/doctors/ana#reviews")}`,
      ),
    );
  });

  it("closes on Escape and returns focus to the button", async () => {
    const { user } = await openDialog();

    await user.keyboard("{Escape}");

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: LABEL })).toHaveFocus();
  });
});
