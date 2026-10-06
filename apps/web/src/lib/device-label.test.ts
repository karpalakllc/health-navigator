import { describe, expect, it } from "vitest";
import { deviceLabel, UNKNOWN_DEVICE_LABEL } from "@/lib/device-label";

describe("deviceLabel", () => {
  it.each([
    [
      "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36",
      "Chrome · macOS",
    ],
    [
      "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 Edg/129.0.0.0",
      "Edge · Windows",
    ],
    [
      "Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1",
      "Safari · iOS",
    ],
    [
      "Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36",
      "Chrome · Android",
    ],
    [
      "Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0",
      "Firefox · Linux",
    ],
    [
      "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 OPR/114.0.0.0",
      "Opera · Windows",
    ],
    [
      "Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0.0.0 Mobile Safari/537.36",
      "Samsung Internet · Android",
    ],
  ])("labels %s", (ua, expected) => {
    expect(deviceLabel(ua)).toBe(expected);
  });

  it("falls back when nothing is recognised", () => {
    expect(deviceLabel(undefined)).toBe(UNKNOWN_DEVICE_LABEL);
    expect(deviceLabel("")).toBe(UNKNOWN_DEVICE_LABEL);
    expect(deviceLabel("curl/8.7.1")).toBe(UNKNOWN_DEVICE_LABEL);
  });

  it("never passes the raw user agent through", () => {
    const ua = "Mozilla/5.0 (X11; Linux x86_64) SomeBot/1.0 secret-build-1234";
    expect(deviceLabel(ua)).toBe("Linux");
  });
});
