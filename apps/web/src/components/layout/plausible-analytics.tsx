import Script from "next/script";

export function PlausibleAnalytics() {
  const domain = process.env.NEXT_PUBLIC_PLAUSIBLE_DOMAIN;

  if (!domain) {
    return null;
  }

  const scriptUrl =
    process.env.NEXT_PUBLIC_PLAUSIBLE_SCRIPT_URL ?? "https://plausible.io/js/script.js";

  return (
    <Script
      defer
      data-domain={domain}
      src={scriptUrl}
      strategy="afterInteractive"
    />
  );
}
