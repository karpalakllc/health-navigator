/**
 * Browser privacy signals that switch the UX tracker off entirely: Global
 * Privacy Control and Do Not Track. With either one on, nothing is sent —
 * not even a page view.
 */
type SignalSource = {
  navigator?: {
    globalPrivacyControl?: boolean;
    doNotTrack?: string | null;
    msDoNotTrack?: string | null;
  };
  doNotTrack?: string | null;
};

const ON = new Set(["1", "yes"]);

export function privacySignalOn(source: SignalSource): boolean {
  const nav = source.navigator;

  if (nav?.globalPrivacyControl === true) return true;

  return [nav?.doNotTrack, nav?.msDoNotTrack, source.doNotTrack].some(
    (value) => typeof value === "string" && ON.has(value.toLowerCase()),
  );
}

/** The same signals as request headers (Sec-GPC, DNT), for the route handler. */
export function privacySignalHeader(headers: Headers): boolean {
  return headers.get("sec-gpc") === "1" || headers.get("dnt") === "1";
}
