import { HeroMeshCard } from "@/components/design/hero-mesh-card";
import { t } from "@/i18n/t";

export function AuthTrustAside() {
  const items = [
    { text: t("home.trustModerated"), tone: "teal" as const },
    { text: t("home.trustInformational"), tone: "red" as const },
    { text: t("home.trustEmergency"), tone: "red" as const },
  ];

  return (
    <HeroMeshCard variant="profile" align="start" className="h-auto">
      <p className="text-sm font-extrabold text-[#43515d]">
        {t("auth.loginAsideTitle")}
      </p>
      <h2 className="mt-3 text-2xl font-black tracking-tight text-foreground">
        {t("auth.asideHeadline")}
      </h2>
      <p className="mt-3 text-sm leading-relaxed text-muted-foreground">
        {t("auth.asideBody")}
      </p>
      <ul className="mt-6 space-y-3">
        {items.map((item) => (
          <li
            key={item.text}
            className="trust-badge-compact flex items-center gap-3 px-3.5 py-2.5"
          >
            <span
              className={`inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${
                item.tone === "teal" ? "icon-soft-teal" : "icon-soft-red"
              }`}
            >
              {item.tone === "teal" ? <ShieldIcon /> : <InfoIcon />}
            </span>
            <span className="text-sm font-semibold leading-snug text-[#586570]">
              {item.text}
            </span>
          </li>
        ))}
      </ul>
    </HeroMeshCard>
  );
}

function ShieldIcon() {
  return (
    <svg
      className="h-5 w-5"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <path
        d="M12 3l8 4v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function InfoIcon() {
  return (
    <svg
      className="h-5 w-5"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <circle cx="12" cy="12" r="9" />
      <path d="M12 10v6M12 7h.01" strokeLinecap="round" />
    </svg>
  );
}
