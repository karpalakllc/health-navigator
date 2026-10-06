import { cn } from "@/lib/cn";

/*
 * Original artwork drawn for Здравје360 (no third-party source): a doctor
 * beside a profile card with a rating, an „accepting patients“ check, a map
 * pin, a forum bubble and the coral heart. Ink line + flat D2a fills (cream,
 * apricot, chip tint, white), coral only on the heart and the stethoscope,
 * care green on the check and the plant. Decorative: aria-hidden, no text.
 * The viewBox fixes the aspect (4:3), so the box is reserved before paint.
 */
const INK = "#2a2220";
const CREAM = "#fbf6f1";
const APRICOT = "#fcdfcc";
const CHIP = "#fde9dd";
const SAND = "#f6ebe2";
const LINE = "#eaded5";
const CORAL = "#ff5757";
const CARE = "#2f6b4f";
const CARE_TINT = "#e4f1ea";
const STAR = "#a14806";
const SKIN = "#e0a27f";

const STAR_PATH =
  "m106 200 3.5 7.2 7.9 1.1-5.7 5.6 1.3 7.8-7-3.7-7 3.7 1.3-7.8-5.7-5.6 7.9-1.1z";

export function HomeHeroIllustration({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 480 360"
      aria-hidden="true"
      focusable="false"
      data-hero-illustration=""
      className={cn("block h-auto w-full", className)}
      fill="none"
      stroke={INK}
      strokeWidth={3}
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      {/* backdrop */}
      <path
        d="M86 300C40 262 38 178 86 120 136 60 226 36 306 52c80 16 140 74 146 150 6 70-34 110-94 118H120c-14 0-24-8-34-20Z"
        fill={CREAM}
        stroke="none"
      />
      <circle cx="404" cy="70" r="40" fill={CHIP} stroke="none" />
      <path d="M28 334h424" />

      {/* profile card */}
      <g transform="rotate(-4 180 200)">
        <rect x="72" y="104" width="216" height="196" rx="22" fill="#fff" />
        <circle cx="122" cy="152" r="26" fill={APRICOT} />
        <path d="M104 171c4-10 11-15 18-15s14 5 18 15" fill="#fff" />
        <circle cx="122" cy="142" r="9" fill="#fff" />
        <rect
          x="160"
          y="138"
          width="98"
          height="11"
          rx="5.5"
          fill={INK}
          stroke="none"
        />
        <rect
          x="160"
          y="158"
          width="66"
          height="9"
          rx="4.5"
          fill={LINE}
          stroke="none"
        />
        <g fill={STAR} stroke="none">
          {[0, 24, 48, 72].map((dx) => (
            <path key={dx} d={STAR_PATH} transform={`translate(${dx} 0)`} />
          ))}
        </g>
        <path
          d={STAR_PATH}
          transform="translate(96 0)"
          fill={APRICOT}
          stroke={STAR}
          strokeWidth={2}
        />
        <rect
          x="96"
          y="236"
          width="150"
          height="9"
          rx="4.5"
          fill={SAND}
          stroke="none"
        />
        <rect
          x="96"
          y="254"
          width="116"
          height="9"
          rx="4.5"
          fill={SAND}
          stroke="none"
        />
        {/* „accepting patients“ check pill */}
        <rect
          x="196"
          y="276"
          width="112"
          height="34"
          rx="17"
          fill={CARE_TINT}
          stroke={CARE}
          strokeWidth={2.5}
        />
        <path d="m212 293 6 6 11-12" stroke={CARE} />
        <rect
          x="238"
          y="289"
          width="54"
          height="8"
          rx="4"
          fill={CARE}
          stroke="none"
        />
      </g>

      {/* heart with a plus */}
      <g transform="rotate(-8 92 82)">
        <path
          d="M92 112c-22-14-34-26-34-41 0-10 8-18 17-18 7 0 13 4 17 10 4-6 10-10 17-10 9 0 17 8 17 18 0 15-12 27-34 41Z"
          fill={CORAL}
        />
        <path d="M92 70v20M82 80h20" stroke="#fff" strokeWidth={4} />
      </g>

      {/* map pin */}
      <path
        d="M300 96c0-14 10-24 22-24s22 10 22 24c0 17-22 38-22 38s-22-21-22-38Z"
        fill="#fff"
      />
      <circle cx="322" cy="96" r="7" fill={APRICOT} />

      {/* sparkles */}
      <path d="M232 52v14M225 59h14M446 196v12M440 202h12" strokeWidth={2.5} />
      <circle cx="54" cy="176" r="4" fill={INK} stroke="none" />

      {/* plant */}
      <path d="M46 290c-14-8-20-24-16-40 14 6 22 20 16 40Z" fill="#7fb595" />
      <path d="M58 288c4-22 16-34 32-36 0 18-12 32-32 36Z" fill={CARE} />
      <path d="M52 292c-6-20-2-40 10-52 8 16 6 36-10 52Z" fill="#b9d8c4" />
      <path d="M34 292h40l-5 42H39z" fill={APRICOT} />

      {/* doctor */}
      <path d="M362 262v64M390 262v64" strokeWidth={18} />
      <path d="M350 330h20M384 330h22" strokeWidth={8} />
      <path d="M318 246c2-34 8-66 18-86l14 10c-6 22-10 50-12 78z" fill="#fff" />
      <path
        d="M434 246c-2-34-8-66-18-86l-14 10c6 22 10 50 12 78z"
        fill="#fff"
      />
      <circle cx="328" cy="256" r="9" fill={SKIN} />
      <circle cx="424" cy="256" r="9" fill={SKIN} />
      <path d="M342 156c4-14 16-22 34-22s30 8 34 22l6 116h-80z" fill="#fff" />
      <path d="M362 136l14 32 14-32" fill={APRICOT} />
      <path
        d="M362 136l-6 10 12 18M390 136l6 10-12 18M376 168v104"
        strokeWidth={2.5}
      />
      <rect
        x="390"
        y="204"
        width="16"
        height="12"
        rx="3"
        fill={APRICOT}
        strokeWidth={2.5}
      />
      <path
        d="M366 138c-4 16-2 30 6 38M388 138c4 16 2 30-6 38"
        strokeWidth={2.5}
      />
      <circle cx="377" cy="182" r="6" fill={CORAL} strokeWidth={2.5} />
      <path d="M370 118v18h14v-18" fill={SKIN} />
      <circle cx="377" cy="102" r="22" fill={SKIN} />
      <path
        d="M355 102c0-14 10-24 22-24 14 0 23 10 22 24-8-2-16-8-20-14-4 8-14 14-24 14Z"
        fill={INK}
      />
      <circle cx="400" cy="82" r="9" fill={INK} />
      <circle cx="370" cy="106" r="1.8" fill={INK} stroke="none" />
      <circle cx="385" cy="106" r="1.8" fill={INK} stroke="none" />
      <path d="M372 114c3 3 7 3 10 0" strokeWidth={2.5} />

      {/* forum bubble */}
      <path
        d="M414 118h40a12 12 0 0 1 12 12v18a12 12 0 0 1-12 12h-26l-12 10v-10h-2a12 12 0 0 1-12-12v-18a12 12 0 0 1 12-12Z"
        fill="#fff"
      />
      <g fill={INK} stroke="none">
        <circle cx="422" cy="139" r="3" />
        <circle cx="434" cy="139" r="3" />
        <circle cx="446" cy="139" r="3" />
      </g>
    </svg>
  );
}
