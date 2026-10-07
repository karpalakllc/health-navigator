import type { ReactNode } from "react";
import type { FirstAidIllustrationId } from "@/content/first-aid/types";
import { cn } from "@/lib/cn";

/*
 * Simple line illustrations for the first-aid guides, drawn for Здравје360 in
 * the D2a spot-illustration style (home-spot-illustrations.tsx): 3px ink
 * line, round caps, flat cream/apricot/care-tint fills, coral only for a
 * heart or a pressing hand. Decorative: aria-hidden — every step says in
 * words what the picture shows, so nothing depends on seeing it (or on
 * colour). Inline SVG: no extra request, prints, works offline once loaded.
 */
const INK = "#2a2220";
const CREAM = "#fbf6f1";
const APRICOT = "#fcdfcc";
const CHIP = "#fde9dd";
const CORAL = "#ff5757";
const CARE = "#2f6b4f";
const CARE_TINT = "#e4f1ea";
const WATER = "#dbeaf3";

function Svg({
  id,
  className,
  children,
}: {
  id: FirstAidIllustrationId;
  className?: string;
  children: ReactNode;
}) {
  return (
    <svg
      viewBox="0 0 160 120"
      aria-hidden="true"
      focusable="false"
      data-first-aid-illustration={id}
      fill="none"
      stroke={INK}
      strokeWidth={3}
      strokeLinecap="round"
      strokeLinejoin="round"
      className={cn("block h-auto", className ?? "w-full")}
    >
      {children}
    </svg>
  );
}

/** Pressing-down marks above a point. */
function PushMarks({ x, y }: { x: number; y: number }) {
  return (
    <path
      d={`M${x - 10} ${y}v8M${x} ${y - 4}v10M${x + 10} ${y}v8`}
      strokeWidth={2.5}
    />
  );
}

/** A person lying on their back, head at the left. */
function LyingBody({ scale = 1 }: { scale?: number }) {
  const w = 96 * scale;
  return (
    <>
      <circle cx={34} cy={84} r={11 * scale} fill={CREAM} />
      <rect
        x={46}
        y={78}
        width={w}
        height={18 * scale}
        rx={9 * scale}
        fill={APRICOT}
      />
    </>
  );
}

const DRAWINGS: Record<FirstAidIllustrationId, () => ReactNode> = {
  "cpr-adult": () => (
    <>
      <circle cx={138} cy={22} r={14} fill={CHIP} stroke="none" />
      <path d="M8 104h144" />
      <LyingBody />
      <circle cx={82} cy={24} r={10} fill={CREAM} />
      <path d="M76 36l-2 34M88 36l2 34" />
      <rect x={70} y={66} width={24} height={10} rx={5} fill={CORAL} />
      <PushMarks x={112} y={52} />
    </>
  ),
  "cpr-child": () => (
    <>
      <circle cx={138} cy={22} r={14} fill={CHIP} stroke="none" />
      <path d="M8 104h144" />
      <circle cx={40} cy={88} r={9} fill={CREAM} />
      <rect x={50} y={82} width={66} height={15} rx={7.5} fill={APRICOT} />
      <circle cx={80} cy={28} r={10} fill={CREAM} />
      <path d="M80 40v34" />
      <rect x={72} y={72} width={16} height={9} rx={4.5} fill={CORAL} />
      <PushMarks x={104} y={56} />
    </>
  ),
  "cpr-infant": () => (
    <>
      <circle cx={136} cy={24} r={14} fill={CHIP} stroke="none" />
      <path d="M8 104h144" />
      <circle cx={52} cy={92} r={8} fill={CREAM} />
      <rect x={60} y={86} width={44} height={13} rx={6.5} fill={APRICOT} />
      <path d="M84 40v40M90 40v40" />
      <path d="M78 34h18a6 6 0 0 1 6 6v0H72v0a6 6 0 0 1 6-6Z" fill={CREAM} />
      <circle cx={84} cy={81} r={2.5} fill={CORAL} stroke="none" />
      <circle cx={90} cy={81} r={2.5} fill={CORAL} stroke="none" />
      <PushMarks x={112} y={60} />
    </>
  ),
  "recovery-position": () => (
    <>
      <path d="M8 104h144" />
      <circle cx={30} cy={82} r={11} fill={CREAM} />
      <path
        d="M42 80c14-6 34-6 52-2l20 4c6 2 8 8 4 12l-6 8H44c-6 0-8-6-4-10Z"
        fill={APRICOT}
      />
      <path d="M96 82l18-18 20 14" />
      <path d="M24 92h18" />
      <circle cx={130} cy={26} r={14} fill={CARE_TINT} stroke="none" />
      <path d="m124 26 4.5 4.5 8-8.5" stroke={CARE} strokeWidth={2.5} />
    </>
  ),
  "choking-back-blows": () => (
    <>
      <path d="M8 108h144" />
      <circle cx={52} cy={46} r={11} fill={CREAM} />
      <path d="M60 54l30-6 16 24-8 36" fill="none" />
      <path d="M62 56c6 10 18 16 30 14" />
      <circle cx={112} cy={22} r={10} fill={CREAM} />
      <path d="M112 34v40M112 46l-14 6" />
      <rect x={90} y={48} width={12} height={10} rx={4} fill={CORAL} />
      <path d="M84 36l-6-6M92 32v-8M100 36l6-6" strokeWidth={2.5} />
    </>
  ),
  "choking-abdominal": () => (
    <>
      <path d="M8 108h144" />
      <circle cx={70} cy={24} r={11} fill={CREAM} />
      <rect x={58} y={36} width={24} height={44} rx={12} fill={APRICOT} />
      <path d="M64 80v28M76 80v28" />
      <circle cx={98} cy={22} r={10} fill={CHIP} />
      <path d="M98 34v74M98 50c-10 4-16 8-22 12" />
      <circle cx={74} cy={62} r={6} fill={CORAL} />
      <path d="M58 70l-8 6M52 60h-10" strokeWidth={2.5} />
      <path d="M46 48l4-8 4 8" strokeWidth={2.5} />
    </>
  ),
  "choking-infant": () => (
    <>
      <path d="M20 54l110 30" strokeWidth={10} stroke={CHIP} />
      <path d="M20 54l110 30" />
      <circle cx={42} cy={72} r={8} fill={CREAM} />
      <rect
        x={50}
        y={60}
        width={44}
        height={14}
        rx={7}
        fill={APRICOT}
        transform="rotate(16 72 67)"
      />
      <rect x={70} y={36} width={14} height={10} rx={4} fill={CORAL} />
      <path d="M66 26l-4-6M77 22v-8M88 26l4-6" strokeWidth={2.5} />
    </>
  ),
  stroke: () => (
    <>
      <circle cx={60} cy={60} r={36} fill={CREAM} />
      <circle cx={46} cy={50} r={3.5} fill={INK} stroke="none" />
      <circle cx={74} cy={54} r={3.5} fill={INK} stroke="none" />
      <path d="M44 74c8 2 16 4 30 12" />
      <circle cx={124} cy={46} r={22} fill={CHIP} />
      <path d="M124 32v14l10 6" />
      <path d="M118 88h20M128 82v12" strokeWidth={2.5} />
    </>
  ),
  heart: () => (
    <>
      <path d="M8 108h144M28 108V40" />
      <circle cx={46} cy={36} r={11} fill={CREAM} />
      <path
        d="M36 50c8-2 18-2 22 4l6 26H40Z"
        fill={APRICOT}
        strokeLinejoin="round"
      />
      <path d="M64 80l26-4 6 32M42 80l-2 28" />
      <path
        d="M118 58c-11-7-17-13-17-21 0-5 4-9 9-9 3.5 0 6.5 2 8 5 1.5-3 4.5-5 8-5 5 0 9 4 9 9 0 8-6 14-17 21Z"
        fill={CORAL}
      />
    </>
  ),
  seizure: () => (
    <>
      <path d="M8 104h144" />
      <rect x={16} y={84} width={46} height={16} rx={8} fill={CARE_TINT} />
      <circle cx={40} cy={76} r={11} fill={CREAM} />
      <rect x={52} y={72} width={84} height={18} rx={9} fill={APRICOT} />
      <circle cx={118} cy={34} r={18} fill={CHIP} />
      <path d="M118 22v12l8 4M114 12h8" />
    </>
  ),
  anaphylaxis: () => (
    <>
      <path d="M20 30c30-8 70-8 100 0v40c-30 8-70 8-100 0Z" fill={APRICOT} />
      <rect
        x={66}
        y={8}
        width={18}
        height={56}
        rx={6}
        fill={CREAM}
        transform="rotate(8 75 36)"
      />
      <rect
        x={68}
        y={6}
        width={14}
        height={12}
        rx={4}
        fill={CORAL}
        transform="rotate(8 75 12)"
      />
      <path d="M126 90l8 8M140 84l-8 8" strokeWidth={2.5} />
      <path d="M30 94h70" />
    </>
  ),
  bleeding: () => (
    <>
      <rect x={14} y={60} width={132} height={26} rx={13} fill={CREAM} />
      <rect x={58} y={48} width={44} height={22} rx={6} fill={CARE_TINT} />
      <rect x={60} y={30} width={40} height={14} rx={7} fill={CORAL} />
      <PushMarks x={80} y={10} />
      <path d="M36 98l-4 8M48 98v8" stroke={CORAL} strokeWidth={2.5} />
    </>
  ),
  burn: () => (
    <>
      <path d="M40 14h46v14H70v8H56v-8H40Z" fill={CREAM} />
      <path d="M58 46v8M64 52v10M70 46v8" stroke={CARE} strokeWidth={2.5} />
      <path d="M50 74h70a10 10 0 0 1 0 20H50Z" fill={APRICOT} />
      <path d="M50 70v28" />
      <path d="M58 64v4M70 64v4" stroke={CARE} strokeWidth={2.5} />
      <circle cx={130} cy={30} r={14} fill={WATER} stroke="none" />
      <path d="M130 20c6 8 8 12 8 15a8 8 0 0 1-16 0c0-3 2-7 8-15Z" />
    </>
  ),
  head: () => (
    <>
      <path
        d="M52 100V82c-12-6-18-18-18-32 0-20 16-34 36-34s34 14 34 32c0 6-2 10-4 14l8 12-8 4v8c0 6-6 10-12 10H80v4"
        fill={CREAM}
      />
      <circle cx={76} cy={20} r={8} fill={APRICOT} />
      <rect x={100} y={10} width={40} height={22} rx={8} fill={WATER} />
      <path d="M108 21h24" strokeWidth={2.5} />
    </>
  ),
  poison: () => (
    <>
      <path
        d="M36 40h28v-14h-6V14h16v12h-6v14h0c8 4 12 10 12 18v42H36V58c0-8 4-14 0-18Z"
        fill={CREAM}
      />
      <path d="M48 68h32M48 80h20" />
      <rect x={100} y={30} width={40} height={70} rx={8} fill={APRICOT} />
      <path d="M112 44h16M110 64h4M118 64h4M126 64h4M110 74h4M118 74h4M126 74h4" />
      <path d="M116 90h8" strokeWidth={2.5} />
    </>
  ),
  faint: () => (
    <>
      <path d="M8 104h144" />
      <circle cx={24} cy={88} r={10} fill={CREAM} />
      <rect x={34} y={82} width={64} height={16} rx={8} fill={APRICOT} />
      <path d="M96 88l30-22 20 0" />
      <rect x={110} y={70} width={30} height={34} rx={4} fill={CHIP} />
      <path d="M118 30l10-10 10 10M128 20v26" strokeWidth={2.5} />
    </>
  ),
  hypo: () => (
    <>
      <path d="M40 22h50l-8 84H48Z" fill={CREAM} />
      <path d="M44 50h42l-5 52H49Z" fill={APRICOT} stroke="none" />
      <path d="M40 22h50l-8 84H48Z" />
      <path d="M74 10l-6 30" />
      <rect x={104} y={70} width={22} height={22} rx={4} fill={CREAM} />
      <rect x={120} y={84} width={22} height={22} rx={4} fill={CREAM} />
      <circle cx={126} cy={34} r={14} fill={CHIP} stroke="none" />
    </>
  ),
  heat: () => (
    <>
      <circle cx={40} cy={36} r={16} fill={APRICOT} />
      <path d="M40 8v6M40 58v6M12 36h6M62 36h6M20 16l4 4M56 52l4 4M20 56l4-4M56 20l4-4" />
      <path d="M96 30h24l6 10-6 10H96Z" fill={CREAM} />
      <path
        d="M128 38l12-6M128 42l14 0M128 46l12 6"
        stroke={CARE}
        strokeWidth={2.5}
      />
      <path d="M36 104c0-18 16-30 44-30s44 12 44 30" fill={CREAM} />
      <path d="M64 92v-4M80 90v-6M96 92v-4" stroke={CARE} strokeWidth={2.5} />
    </>
  ),
  cold: () => (
    <>
      <circle cx={80} cy={32} r={13} fill={CREAM} />
      <path d="M52 56c0-8 12-12 28-12s28 4 28 12v44H52Z" fill={APRICOT} />
      <path d="M58 64l44 20M58 84l44-14" strokeWidth={2.5} />
      <path
        d="M128 20v24M116 32h24M120 24l16 16M136 24l-16 16"
        strokeWidth={2.5}
      />
      <path d="M8 104h144" />
    </>
  ),
  nose: () => (
    <>
      <path
        d="M40 104V84c-10-8-14-18-14-30 0-22 18-38 40-38 20 0 34 14 34 30 0 6-2 12-4 16l14 14-12 4v6c0 6-4 10-10 10H76v18"
        fill={CREAM}
      />
      <rect x={98} y={56} width={30} height={12} rx={6} fill={CORAL} />
      <path d="M128 62h14" />
      <path d="M100 96l18 6M44 104h56" strokeWidth={2.5} />
    </>
  ),
  sprain: () => (
    <>
      <rect x={14} y={78} width={132} height={22} rx={11} fill={CHIP} />
      <path d="M30 76V40h22v30c0 6 4 8 10 8h40c8 0 10 6 6 10" fill={CREAM} />
      <rect x={26} y={48} width={30} height={16} rx={6} fill={WATER} />
      <path d="M110 30l10-10 10 10M120 20v26" strokeWidth={2.5} />
    </>
  ),
};

export function FirstAidIllustration({
  id,
  className,
}: {
  id: FirstAidIllustrationId;
  className?: string;
}) {
  const Drawing = DRAWINGS[id];

  return (
    <Svg id={id} className={className}>
      <Drawing />
    </Svg>
  );
}

export const FIRST_AID_ILLUSTRATION_IDS = Object.keys(
  DRAWINGS,
) as FirstAidIllustrationId[];
