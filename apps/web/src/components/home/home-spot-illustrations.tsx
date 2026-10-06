import { cn } from "@/lib/cn";

/*
 * Two small spot illustrations in the hero artwork's style (original, drawn
 * for Здравје360): 3px ink line, round caps, flat D2a fills, coral only on
 * the heart. Decorative: aria-hidden, no text, fixed viewBox (no shift).
 */
const INK = "#2a2220";
const CREAM = "#fbf6f1";
const APRICOT = "#fcdfcc";
const CHIP = "#fde9dd";
const CORAL = "#ff5757";
const CARE = "#2f6b4f";
const CARE_TINT = "#e4f1ea";

const common = {
  "aria-hidden": true,
  focusable: "false",
  fill: "none",
  stroke: INK,
  strokeWidth: 3,
  strokeLinecap: "round",
  strokeLinejoin: "round",
} as const;

/** „Од заедницата“: a question, an answer with a check, a small heart. */
export function CommunityIllustration({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 240 160"
      data-spot-illustration="community"
      className={cn("block h-auto", className ?? "w-full")}
      {...common}
    >
      <circle cx="186" cy="40" r="30" fill={CREAM} stroke="none" />
      <circle cx="44" cy="128" r="22" fill={CHIP} stroke="none" />

      {/* question bubble */}
      <path
        d="M30 26h104a14 14 0 0 1 14 14v34a14 14 0 0 1-14 14H66l-18 16V88H30a14 14 0 0 1-14-14V40a14 14 0 0 1 14-14Z"
        fill="#fff"
      />
      <rect
        x="34"
        y="44"
        width="80"
        height="9"
        rx="4.5"
        fill={INK}
        stroke="none"
      />
      <rect
        x="34"
        y="62"
        width="56"
        height="8"
        rx="4"
        fill={APRICOT}
        stroke="none"
      />

      {/* answer bubble */}
      <path
        d="M110 82h100a14 14 0 0 1 14 14v30a14 14 0 0 1-14 14h-14v14l-18-14h-68a14 14 0 0 1-14-14V96a14 14 0 0 1 14-14Z"
        fill={APRICOT}
      />
      <circle
        cx="122"
        cy="111"
        r="12"
        fill={CARE_TINT}
        stroke={CARE}
        strokeWidth={2.5}
      />
      <path d="m116 111 4.5 4.5 8-8.5" stroke={CARE} strokeWidth={2.5} />
      <rect
        x="142"
        y="100"
        width="64"
        height="8"
        rx="4"
        fill="#fff"
        stroke="none"
      />
      <rect
        x="142"
        y="116"
        width="44"
        height="8"
        rx="4"
        fill="#fff"
        stroke="none"
      />

      {/* heart */}
      <path
        d="M196 60c-11-7-17-13-17-21 0-5 4-9 9-9 3.5 0 6.5 2 8 5 1.5-3 4.5-5 8-5 5 0 9 4 9 9 0 8-6 14-17 21Z"
        fill={CORAL}
      />
      <path d="M226 18v10M221 23h10M162 14v8M158 18h8" strokeWidth={2.5} />
    </svg>
  );
}

/** „Пребарај по град“: hills with three pins, the middle one apricot. */
export function CitiesIllustration({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 240 120"
      data-spot-illustration="cities"
      className={cn("block h-auto", className ?? "w-full")}
      {...common}
    >
      <path
        d="M8 98c30-26 60-30 92-14s62 16 88-4 40-14 44-10v40H8z"
        fill={CARE_TINT}
        stroke="none"
      />
      <path d="M8 98c30-26 60-30 92-14s62 16 88-4 40-14 44-10" />
      <path d="M8 112h224" />
      <circle cx="200" cy="26" r="14" fill={CHIP} stroke="none" />

      <path
        d="M58 70c0-9 6-15 13-15s13 6 13 15c0 10-13 22-13 22S58 80 58 70Z"
        fill="#fff"
      />
      <circle cx="71" cy="70" r="4" fill={INK} stroke="none" />

      <path
        d="M104 46c0-13 9-22 19-22s19 9 19 22c0 15-19 32-19 32s-19-17-19-32Z"
        fill={APRICOT}
      />
      <circle cx="123" cy="46" r="6" fill="#fff" />

      <path
        d="M164 62c0-8 5-13 11-13s11 5 11 13c0 9-11 19-11 19s-11-10-11-19Z"
        fill="#fff"
      />
      <circle cx="175" cy="62" r="3.5" fill={INK} stroke="none" />

      <path d="M84 30v8M80 34h8M222 52v8M218 56h8" strokeWidth={2.5} />
    </svg>
  );
}
