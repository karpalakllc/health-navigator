import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { AccountDelete } from "@/components/account/account-delete";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { mockFetch, requestBody } from "../../../test/fetch";
import { router } from "../../../test/next-navigation";

async function openConfirmation() {
  const user = userEvent.setup();
  render(<AccountDelete />);
  await user.click(
    screen.getByRole("button", { name: t("account.data.deleteStart") }),
  );

  return user;
}

function passwordField() {
  return screen.getByLabelText(t("account.data.deletePassword"));
}

function confirmButton() {
  return screen.getByRole("button", { name: t("account.data.deleteConfirm") });
}

describe("AccountDelete", () => {
  it("sends nothing until the explicit confirmation, and focuses its heading", async () => {
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    const user = userEvent.setup();
    const { container } = render(<AccountDelete />);

    expect(passwordField).toThrow();

    await user.click(
      screen.getByRole("button", { name: t("account.data.deleteStart") }),
    );

    expect(
      screen.getByRole("heading", {
        name: t("account.data.deleteConfirmHeading"),
      }),
    ).toHaveFocus();
    expect(
      screen.getByRole("form", {
        name: t("account.data.deleteConfirmHeading"),
      }),
    ).toHaveAccessibleDescription(t("account.data.deleteConfirmBody"));
    expect(passwordField()).toHaveAttribute("type", "password");
    expect(passwordField()).toHaveAttribute("autocomplete", "current-password");
    expect(fetch).not.toHaveBeenCalled();
    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("asks for the password before calling the API", async () => {
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    const user = await openConfirmation();

    await user.click(confirmButton());

    expect(passwordField()).toHaveFocus();
    expect(passwordField()).toHaveAttribute("aria-invalid", "true");
    expect(passwordField()).toHaveAccessibleDescription(
      new RegExp(t("account.data.deletePasswordRequired")),
    );
    expect(fetch).not.toHaveBeenCalled();
  });

  it("deletes with the password and leaves for the confirmation page", async () => {
    const fetch = mockFetch({
      status: 200,
      body: { data: { message: "Сметката е избришана." } },
    });
    const user = await openConfirmation();

    await user.type(passwordField(), "sufficiently1long");
    await user.click(confirmButton());

    expect(fetch.mock.calls[0]?.[0]).toBe("/api/account/delete");
    expect(fetch.mock.calls[0]?.[1]?.method).toBe("POST");
    expect(requestBody(fetch)).toEqual({ password: "sufficiently1long" });
    expect(router.replace).toHaveBeenCalledWith("/account/deleted");
  });

  it("puts a wrong password back on the field, cleared and focused", async () => {
    mockFetch({
      status: 422,
      body: {
        message: "Лозинката не е точна.",
        errors: { password: ["Лозинката не е точна."] },
      },
    });
    const user = await openConfirmation();

    await user.type(passwordField(), "wrong-password");
    await user.click(confirmButton());

    await screen.findByText(/Лозинката не е точна\./);
    expect(passwordField()).toHaveAccessibleDescription(
      /Лозинката не е точна\./,
    );
    expect(passwordField()).toHaveValue("");
    expect(passwordField()).toHaveFocus();
    expect(passwordField()).toHaveAttribute("aria-invalid", "true");
    expect(router.replace).not.toHaveBeenCalled();
  });

  it("shows the API's message when deletion is refused", async () => {
    mockFetch({
      status: 403,
      body: {
        code: "account.staff_cannot_delete",
        message: "Сметките на тимот не можат да се избришат од тука.",
      },
    });
    const user = await openConfirmation();

    await user.type(passwordField(), "sufficiently1long");
    await user.click(confirmButton());

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Сметките на тимот не можат да се избришат од тука.",
    );
  });

  it("cancelling closes the confirmation and returns focus to the opener", async () => {
    const user = await openConfirmation();

    await user.type(passwordField(), "sufficiently1long");
    await user.click(
      screen.getByRole("button", { name: t("account.data.deleteCancel") }),
    );

    expect(
      screen.queryByLabelText(t("account.data.deletePassword")),
    ).toBeNull();
    expect(
      screen.getByRole("button", { name: t("account.data.deleteStart") }),
    ).toHaveFocus();

    // Reopening starts empty: the password is not kept around.
    await user.click(
      screen.getByRole("button", { name: t("account.data.deleteStart") }),
    );
    expect(passwordField()).toHaveValue("");
  });
});
