import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { AccountDevices } from "@/components/account/account-devices";
import type { AccountDevice } from "@/lib/api/account";
import { t, tFormat } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";
import { mockFetch } from "../../../test/fetch";
import { router } from "../../../test/next-navigation";

const devices: AccountDevice[] = [
  {
    id: 3,
    name: "Chrome · macOS",
    created_at: "2026-10-01T09:00:00+02:00",
    last_used_at: "2026-10-06T10:00:00+02:00",
    expires_at: "2026-10-31T09:00:00+02:00",
    is_current: true,
  },
  {
    id: 2,
    name: "Safari · iOS",
    created_at: "2026-09-20T09:00:00+02:00",
    last_used_at: "2026-10-02T18:00:00+02:00",
    expires_at: "2026-10-20T09:00:00+02:00",
    is_current: false,
  },
  {
    id: 1,
    name: "web",
    created_at: "2026-09-10T09:00:00+02:00",
    last_used_at: null,
    expires_at: "2026-10-10T09:00:00+02:00",
    is_current: false,
  },
];

function list() {
  return screen.getByRole("list", { name: t("account.devices.heading") });
}

describe("AccountDevices", () => {
  it("lists devices with dates, marks this one and offers no sign-out for it", async () => {
    const { container } = render(<AccountDevices devices={devices} />);

    const items = within(list()).getAllByRole("listitem");
    expect(items).toHaveLength(3);

    const current = items[0];
    expect(within(current).getByText("Chrome · macOS")).toBeInTheDocument();
    expect(
      within(current).getByText(t("account.devices.current")),
    ).toBeInTheDocument();
    expect(within(current).queryByRole("button")).toBeNull();
    expect(
      within(current).getByText(
        tFormat("account.devices.signedIn", { date: "1 октомври 2026" }),
      ),
    ).toBeInTheDocument();

    // Unnamed legacy tokens get a readable label.
    expect(
      within(items[2]).getByText(t("account.devices.unknownDevice")),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", {
        name: tFormat("account.devices.revokeAria", { name: "Safari · iOS" }),
      }),
    ).toBeInTheDocument();

    expect(await seriousA11yViolations(container)).toEqual([]);
  });

  it("signs one device out, announces it and moves focus to the heading", async () => {
    const fetch = mockFetch({ status: 200, body: { data: {} } });
    const user = userEvent.setup();
    render(<AccountDevices devices={devices} />);

    await user.click(
      screen.getByRole("button", {
        name: tFormat("account.devices.revokeAria", { name: "Safari · iOS" }),
      }),
    );

    expect(fetch.mock.calls[0]?.[0]).toBe("/api/account/devices/2");
    expect(fetch.mock.calls[0]?.[1]?.method).toBe("DELETE");
    expect(
      await screen.findByText(
        tFormat("account.devices.revoked", { name: "Safari · iOS" }),
      ),
    ).toBeInTheDocument();
    expect(screen.queryByText("Safari · iOS")).toBeNull();
    expect(
      screen.getByRole("heading", { name: t("account.devices.heading") }),
    ).toHaveFocus();
    expect(router.refresh).toHaveBeenCalled();
  });

  it("signs out every other device and keeps this one", async () => {
    const fetch = mockFetch({ status: 200, body: { data: { revoked: 2 } } });
    const user = userEvent.setup();
    render(<AccountDevices devices={devices} />);

    await user.click(
      screen.getByRole("button", { name: t("account.devices.revokeOthers") }),
    );

    expect(fetch.mock.calls[0]?.[0]).toBe("/api/account/devices");
    expect(
      await screen.findByText(t("account.devices.revokedOthers")),
    ).toBeInTheDocument();
    expect(within(list()).getAllByRole("listitem")).toHaveLength(1);
    expect(screen.getByText(t("account.devices.onlyThis"))).toBeInTheDocument();
    expect(
      screen.queryByRole("button", { name: t("account.devices.revokeOthers") }),
    ).toBeNull();
  });

  it("keeps the device and shows an error when the sign-out fails", async () => {
    mockFetch({ status: 502, body: { message: "Услугата не е достапна." } });
    const user = userEvent.setup();
    render(<AccountDevices devices={devices} />);

    await user.click(
      screen.getByRole("button", {
        name: tFormat("account.devices.revokeAria", { name: "Safari · iOS" }),
      }),
    );

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Услугата не е достапна.",
    );
    expect(screen.getByText("Safari · iOS")).toBeInTheDocument();
  });

  it("sends an expired session to sign-in", async () => {
    mockFetch({ status: 401, body: { message: "Не сте најавени." } });
    const user = userEvent.setup();
    render(<AccountDevices devices={devices} />);

    await user.click(
      screen.getByRole("button", { name: t("account.devices.revokeOthers") }),
    );

    expect(router.push).toHaveBeenCalledWith(
      "/login?redirect=/account/devices",
    );
  });
});
