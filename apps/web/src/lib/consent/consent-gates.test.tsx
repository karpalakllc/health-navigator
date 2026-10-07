import { act, render } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { StatisticsOnly } from "@/components/layout/statistics-only";
import { CONSENT_KEY, saveConsent } from "@/lib/consent/consent";
import { recordFunnelStep, sendFeedback } from "@/lib/feedback";
import { beaconSender } from "@/lib/ux/tracker";
import { mockFetch } from "../../../test/fetch";

beforeEach(() => {
  window.localStorage.removeItem(CONSENT_KEY);
});

afterEach(() => {
  window.localStorage.removeItem(CONSENT_KEY);
  vi.unstubAllGlobals();
});

describe("statistics gates", () => {
  it("recordFunnelStep sends nothing without an explicit yes, and the header with one", () => {
    const fetch = mockFetch({ status: 204 });

    recordFunnelStep("guidance:headache", "start", 0);
    expect(fetch).not.toHaveBeenCalled();

    saveConsent(false);
    recordFunnelStep("guidance:headache", "start", 0);
    expect(fetch).not.toHaveBeenCalled();

    // Explicit consent wins over Global Privacy Control.
    vi.stubGlobal("navigator", { ...navigator, globalPrivacyControl: true });
    saveConsent(true);
    recordFunnelStep("guidance:headache", "start", 0);
    expect(fetch).toHaveBeenCalledOnce();
    expect(
      (fetch.mock.calls[0][1] as RequestInit).headers as Record<string, string>,
    ).toMatchObject({ "X-Z360-Consent": "statistics" });
  });

  it("a helpful vote is an explicit action and needs no consent header", async () => {
    const fetch = mockFetch({ status: 204 });

    await sendFeedback({ kind: "vote", item: "guide:x", helpful: true });

    expect(fetch).toHaveBeenCalledOnce();
    expect(
      (fetch.mock.calls[0][1] as RequestInit).headers as Record<string, string>,
    ).not.toHaveProperty("X-Z360-Consent");
  });

  it("the UX sender sends only with consent, with the header, and drops a batch after withdrawal", () => {
    const fetch = mockFetch({ status: 204 });
    const send = beaconSender(window);
    const batch = { clicks: [], views: [] };

    send(batch);
    expect(fetch).not.toHaveBeenCalled();

    saveConsent(true);
    send(batch);
    expect(fetch).toHaveBeenCalledOnce();
    expect(
      (fetch.mock.calls[0][1] as RequestInit).headers as Record<string, string>,
    ).toMatchObject({ "X-Z360-Consent": "statistics" });

    saveConsent(false);
    send(batch);
    expect(fetch).toHaveBeenCalledOnce();
  });

  it("StatisticsOnly (Plausible's script and pageviews) renders only while consent stands", () => {
    const { queryByText } = render(
      <StatisticsOnly>
        <span>plausible</span>
      </StatisticsOnly>,
    );
    expect(queryByText("plausible")).toBeNull();

    act(() => saveConsent(true));
    expect(queryByText("plausible")).not.toBeNull();

    act(() => saveConsent(false));
    expect(queryByText("plausible")).toBeNull();
  });
});
