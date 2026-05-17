import Image from "next/image";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

function isSvgAsset(url: string): boolean {
  return /\.svg($|\?)/i.test(url);
}

export function SiteBrandMark({
  logoUrl,
  className,
}: {
  logoUrl?: string | null;
  className?: string;
}) {
  if (logoUrl) {
    const imageClass = cn(
      "h-9 w-auto max-w-[min(100%,14rem)] object-contain object-left sm:h-10 sm:max-w-[16rem]",
      className,
    );

    if (isSvgAsset(logoUrl)) {
      return (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={logoUrl} alt={t("meta.title")} className={imageClass} />
      );
    }

    return (
      <Image
        src={logoUrl}
        alt={t("meta.title")}
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
        "flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-accent text-sm font-bold text-primary-foreground shadow-sm sm:h-10 sm:w-10",
        className,
      )}
    >
      Z
    </span>
  );
}
