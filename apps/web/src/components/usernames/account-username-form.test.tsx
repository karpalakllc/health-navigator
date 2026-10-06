import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { AccountUsernameForm } from "@/components/usernames/account-username-form";
import { t, tFormat } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { mockFetch, requestBody } from "../../../test/fetch";
import { router } from "../../../test/next-navigation";

describe("AccountUsernameForm", () => {
  it("changes the username and then shows when the next change is possible", async () => {
    const fetch = mockFetch({
      status: 200,
      body: {
        data: {
          user: {
            username: "marija_nova",
            username_change_available_at: "2027-01-12T10:00:00+00:00",
          },
        },
      },
    });
    const user = userEvent.setup();
    render(
      <AccountUsernameForm
        username="bitolchanka"
        mustChoose={false}
        changeAvailableAt={null}
      />,
    );

    const field = screen.getByLabelText(t("usernames.label"));
    expect(field).toHaveValue("bitolchanka");

    await user.clear(field);
    await user.type(field, " marija_nova ");
    await user.click(screen.getByRole("button", { name: t("usernames.save") }));

    expect(fetch.mock.calls[0]?.[0]).toBe("/api/session/profile");
    expect(fetch.mock.calls[0]?.[1]?.method).toBe("PATCH");
    expect(requestBody(fetch)).toEqual({ username: "marija_nova" });
    expect(await screen.findByText(t("usernames.saved"))).toBeInTheDocument();
    expect(router.refresh).toHaveBeenCalled();

    // Read-only until the 90 days are up, and it says until when.
    expect(
      screen.queryByLabelText(t("usernames.label")),
    ).not.toBeInTheDocument();
    expect(screen.getByText("marija_nova")).toBeInTheDocument();
    expect(
      screen.getByText(
        new RegExp(
          tFormat("usernames.nextChange", { date: "12 јануари 2027" }).replace(
            /[.*+?^${}()|[\]\\]/g,
            "\\$&",
          ),
        ),
      ),
    ).toBeInTheDocument();
  });

  it("is read-only during the 90 days", () => {
    render(
      <AccountUsernameForm
        username="bitolchanka"
        mustChoose={false}
        changeAvailableAt="2027-01-12T10:00:00+00:00"
      />,
    );

    expect(screen.queryByRole("textbox")).not.toBeInTheDocument();
    expect(screen.queryByRole("button")).not.toBeInTheDocument();
    expect(screen.getByText(/12 јануари 2027/)).toBeInTheDocument();
  });

  it("checks the format without calling the API", async () => {
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    const user = userEvent.setup();
    render(
      <AccountUsernameForm
        username="bitolchanka"
        mustChoose={false}
        changeAvailableAt={null}
      />,
    );

    const field = screen.getByLabelText(t("usernames.label"));
    await user.clear(field);
    await user.type(field, "ana__m");
    await user.click(screen.getByRole("button", { name: t("usernames.save") }));

    expect(fetch).not.toHaveBeenCalled();
    expect(field).toHaveAttribute("aria-invalid", "true");
    expect(field).toHaveAccessibleDescription(
      new RegExp(t("usernames.errorSeparators")),
    );
  });

  it("shows the API's refusal (cooldown, taken) under the field", async () => {
    mockFetch({
      status: 422,
      body: {
        message: "Ова корисничко име веќе се користи.",
        errors: { username: ["Ова корисничко име веќе се користи."] },
      },
    });
    const user = userEvent.setup();
    render(
      <AccountUsernameForm
        username="bitolchanka"
        mustChoose={false}
        changeAvailableAt={null}
      />,
    );

    const field = screen.getByLabelText(t("usernames.label"));
    await user.clear(field);
    await user.type(field, "marija_bt");
    await user.click(screen.getByRole("button", { name: t("usernames.save") }));

    await waitFor(() => expect(field).toHaveAttribute("aria-invalid", "true"));
    expect(field).toHaveAccessibleDescription(
      /Ова корисничко име веќе се користи\./,
    );
  });

  it("asks calmly for a first username and starts empty instead of the placeholder", async () => {
    const { container } = render(
      <AccountUsernameForm
        username="clen-k3x9p2"
        mustChoose
        changeAvailableAt={null}
      />,
    );

    expect(screen.getByText(t("usernames.accountNotice"))).toBeInTheDocument();
    expect(screen.getByLabelText(t("usernames.label"))).toHaveValue("");
    expect(await seriousA11yViolations(container)).toEqual([]);
  });
});
