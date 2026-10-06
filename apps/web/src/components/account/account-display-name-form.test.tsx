import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { AccountDisplayNameForm } from "@/components/account/account-display-name-form";
import { t } from "@/i18n/t";
import { mockFetch, requestBody } from "../../../test/fetch";
import { router } from "../../../test/next-navigation";

describe("AccountDisplayNameForm", () => {
  it("saves the trimmed display name and refreshes the page", async () => {
    const fetch = mockFetch({
      status: 200,
      body: { data: { user: { display_name: "Мара К." } } },
    });
    const user = userEvent.setup();
    render(<AccountDisplayNameForm displayName="Марија К." />);

    const field = screen.getByLabelText(t("account.displayName"));
    expect(field).toHaveAccessibleDescription(t("account.displayNameHelp"));

    await user.clear(field);
    await user.type(field, "  Мара   К. ");
    await user.click(
      screen.getByRole("button", { name: t("account.displayNameSave") }),
    );

    expect(fetch.mock.calls[0]?.[0]).toBe("/api/session/profile");
    expect(fetch.mock.calls[0]?.[1]?.method).toBe("PATCH");
    expect(requestBody(fetch)).toEqual({ display_name: "Мара К." });
    expect(
      await screen.findByText(t("account.displayNameSaved")),
    ).toBeInTheDocument();
    expect(router.refresh).toHaveBeenCalled();
  });

  it("refuses characters the API would reject without calling it", async () => {
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    const user = userEvent.setup();
    render(<AccountDisplayNameForm displayName="Марија К." />);

    const field = screen.getByLabelText(t("account.displayName"));
    await user.clear(field);
    await user.type(field, "Мара 123");
    await user.click(
      screen.getByRole("button", { name: t("account.displayNameSave") }),
    );

    expect(screen.getByRole("alert")).toHaveTextContent(
      t("account.displayNameInvalid"),
    );
    expect(fetch).not.toHaveBeenCalled();
  });

  it("shows the API's validation message", async () => {
    mockFetch({
      status: 422,
      body: {
        message: "Јавното име е невалидно.",
        errors: { display_name: ["Јавното име е невалидно."] },
      },
    });
    const user = userEvent.setup();
    render(<AccountDisplayNameForm displayName="Марија К." />);

    await user.click(
      screen.getByRole("button", { name: t("account.displayNameSave") }),
    );

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Јавното име е невалидно.",
    );
  });
});
