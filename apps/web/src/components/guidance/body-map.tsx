"use client";

import type { BodyArea } from "@/lib/api/guidance-v2";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

/*
 * Front and back outline with tappable regions. A pointer convenience only:
 * the SVG is hidden from assistive technology and skipped by the keyboard,
 * because the same areas are the labelled chips next to it (the accessible
 * list alternative). Regions without a flow are muted but still clickable:
 * the visitor gets the „no guidance yet“ note instead of silence.
 */

type Region = {
  area: BodyArea;
  shape:
    | { kind: "rect"; x: number; y: number; w: number; h: number; r: number }
    | { kind: "ellipse"; cx: number; cy: number; rx: number; ry: number };
};

const FRONT: Region[] = [
  { area: "head", shape: { kind: "ellipse", cx: 60, cy: 28, rx: 19, ry: 22 } },
  { area: "ears", shape: { kind: "ellipse", cx: 39, cy: 29, rx: 4, ry: 7 } },
  { area: "ears", shape: { kind: "ellipse", cx: 81, cy: 29, rx: 4, ry: 7 } },
  { area: "eyes", shape: { kind: "rect", x: 47, y: 20, w: 26, h: 8, r: 4 } },
  { area: "mouth", shape: { kind: "rect", x: 51, y: 35, w: 18, h: 7, r: 3.5 } },
  { area: "throat", shape: { kind: "rect", x: 51, y: 50, w: 18, h: 12, r: 4 } },
  { area: "chest", shape: { kind: "rect", x: 34, y: 63, w: 52, h: 42, r: 12 } },
  {
    area: "abdomen",
    shape: { kind: "rect", x: 36, y: 106, w: 48, h: 34, r: 8 },
  },
  {
    area: "pelvis",
    shape: { kind: "rect", x: 38, y: 141, w: 44, h: 22, r: 8 },
  },
  { area: "arms", shape: { kind: "rect", x: 16, y: 66, w: 15, h: 98, r: 7.5 } },
  { area: "arms", shape: { kind: "rect", x: 89, y: 66, w: 15, h: 98, r: 7.5 } },
  { area: "legs", shape: { kind: "rect", x: 39, y: 165, w: 19, h: 88, r: 8 } },
  { area: "legs", shape: { kind: "rect", x: 62, y: 165, w: 19, h: 88, r: 8 } },
];

const BACK: Region[] = [
  { area: "head", shape: { kind: "ellipse", cx: 60, cy: 28, rx: 19, ry: 22 } },
  { area: "throat", shape: { kind: "rect", x: 51, y: 50, w: 18, h: 12, r: 4 } },
  { area: "back", shape: { kind: "rect", x: 34, y: 63, w: 52, h: 78, r: 12 } },
  {
    area: "pelvis",
    shape: { kind: "rect", x: 38, y: 142, w: 44, h: 21, r: 8 },
  },
  { area: "arms", shape: { kind: "rect", x: 16, y: 66, w: 15, h: 98, r: 7.5 } },
  { area: "arms", shape: { kind: "rect", x: 89, y: 66, w: 15, h: 98, r: 7.5 } },
  { area: "legs", shape: { kind: "rect", x: 39, y: 165, w: 19, h: 88, r: 8 } },
  { area: "legs", shape: { kind: "rect", x: 62, y: 165, w: 19, h: 88, r: 8 } },
];

function Figure({
  regions,
  label,
  selected,
  available,
  onSelect,
}: {
  regions: Region[];
  label: string;
  selected: BodyArea | null;
  available: Set<BodyArea>;
  onSelect: (area: BodyArea) => void;
}) {
  return (
    <figure className="flex flex-col items-center gap-2">
      <svg
        viewBox="0 0 120 260"
        className="h-64 w-auto lg:h-72"
        aria-hidden="true"
        focusable="false"
      >
        {regions.map((region, index) => {
          const active = available.has(region.area);
          const isSelected = selected === region.area;
          const className = cn(
            "stroke-line-strong stroke-[1.25] transition-colors",
            isSelected
              ? "fill-ink"
              : active
                ? "cursor-pointer fill-sand hover:fill-apricot"
                : "cursor-pointer fill-white opacity-60 hover:opacity-100",
          );
          const onClick = () => onSelect(region.area);

          return region.shape.kind === "rect" ? (
            <rect
              key={`${region.area}-${index}`}
              data-area={region.area}
              x={region.shape.x}
              y={region.shape.y}
              width={region.shape.w}
              height={region.shape.h}
              rx={region.shape.r}
              className={className}
              onClick={onClick}
            />
          ) : (
            <ellipse
              key={`${region.area}-${index}`}
              data-area={region.area}
              cx={region.shape.cx}
              cy={region.shape.cy}
              rx={region.shape.rx}
              ry={region.shape.ry}
              className={className}
              onClick={onClick}
            />
          );
        })}
      </svg>
      <figcaption className="type-meta text-ink-2">{label}</figcaption>
    </figure>
  );
}

export function BodyMap({
  selected,
  available,
  onSelect,
}: {
  selected: BodyArea | null;
  available: BodyArea[];
  onSelect: (area: BodyArea) => void;
}) {
  const set = new Set(available);

  return (
    <div className="flex justify-center gap-6 rounded-card bg-white p-4 shadow-card">
      <Figure
        regions={FRONT}
        label={t("guidance.bodyFront")}
        selected={selected}
        available={set}
        onSelect={onSelect}
      />
      <Figure
        regions={BACK}
        label={t("guidance.bodyBack")}
        selected={selected}
        available={set}
        onSelect={onSelect}
      />
    </div>
  );
}
