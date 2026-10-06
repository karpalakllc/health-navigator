import Image from "next/image";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

function isSvgAsset(url: string): boolean {
  return /\.svg($|\?)/i.test(url);
}

/** The coral disc with a white „+“ (decorative; the wordmark is the text). */
export function BrandMarkIcon({ size = 32 }: { size?: number }) {
  const r = size / 2;
  const barW = size * 0.16;
  const barL = size * 0.5;

  return (
    <svg
      width={size}
      height={size}
      viewBox={`0 0 ${size} ${size}`}
      aria-hidden="true"
      focusable="false"
      className="shrink-0"
    >
      <circle cx={r} cy={r} r={r} fill="var(--color-coral)" />
      <rect
        x={r - barW / 2}
        y={r - barL / 2}
        width={barW}
        height={barL}
        rx={barW / 2}
        fill="#ffffff"
      />
      <rect
        x={r - barL / 2}
        y={r - barW / 2}
        width={barL}
        height={barW}
        rx={barW / 2}
        fill="#ffffff"
      />
    </svg>
  );
}

/**
 * Logo: an admin-uploaded logo when there is one, else the coral mark and
 * the „Здравје360“ wordmark (Geologica 700, 20px mobile / 24px desktop).
 */
export function SiteBrandMark({
  logoUrl,
  className,
  size = "responsive",
}: {
  logoUrl?: string | null;
  className?: string;
  /** "responsive": 32/20 on mobile, 36/24 from lg. */
  size?: "responsive" | "sm";
}) {
  if (logoUrl) {
    const imageClass = cn(
      "h-9 w-auto max-w-[min(100%,14rem)] object-contain object-left sm:h-10 sm:max-w-[16rem]",
      className,
    );

    if (isSvgAsset(logoUrl)) {
      return (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={logoUrl} alt={t("nav.wordmark")} className={imageClass} />
      );
    }

    return (
      <Image
        src={logoUrl}
        alt={t("nav.wordmark")}
        width={220}
        height={40}
        className={imageClass}
        unoptimized
        priority
      />
    );
  }

  return (
    <span
      className={cn(
        "inline-flex items-center gap-2.5",
        size === "responsive" && "lg:gap-3",
        className,
      )}
    >
      {size === "responsive" ? (
        <>
          <span className="lg:hidden">
            <BrandMarkIcon size={32} />
          </span>
          <span className="hidden lg:inline">
            <BrandMarkIcon size={36} />
          </span>
        </>
      ) : (
        <BrandMarkIcon size={32} />
      )}
      <span
        className={cn(
          "text-xl font-bold leading-none tracking-[-0.01em] text-ink",
          size === "responsive" && "lg:text-2xl",
        )}
      >
        {t("nav.wordmark")}
      </span>
    </span>
  );
}
