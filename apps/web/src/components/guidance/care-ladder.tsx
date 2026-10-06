import { Button } from "@/components/ui/button";
import { Icon, type IconName } from "@/components/ui/icons";
import { NoticeTelLink } from "@/components/ui/notice";
import { Tag } from "@/components/ui/tag";
import { cn } from "@/lib/cn";
import type { MessageKey } from "@/i18n/t";
import { t } from "@/i18n/t";

/*
 * The care ladder (NHS 111's care settings, simplified as Ada does): every
 * outcome is placed on one of four rungs, from looking after yourself at home
 * to calling emergency services. Showing all four, with the result marked,
 * tells the visitor where to go next and where to go if things get worse.
 */
export const CARE_LEVELS = ["home", "pharmacy", "gp", "emergency"] as const;

export type CareLevel = (typeof CARE_LEVELS)[number];

/*
 * Outcome codes are authored by staff in Filament. The seeded ones, plus the
 * obvious names a new outcome would get, map to a rung; anything else marks
 * no rung rather than guessing one.
 */
const OUTCOME_LEVELS: Record<string, CareLevel> = {
  general_information: "home",
  self_care: "home",
  home_care: "home",
  pharmacy: "pharmacy",
  see_pharmacist: "pharmacy",
  seek_care_soon: "gp",
  see_gp: "gp",
  gp: "gp",
  emergency: "emergency",
};

export function careLevelForOutcome(code: string): CareLevel | null {
  return Object.hasOwn(OUTCOME_LEVELS, code) ? OUTCOME_LEVELS[code] : null;
}

type Rung = {
  level: CareLevel;
  icon: IconName;
  title: MessageKey;
  body: MessageKey;
  link?: { href: string; label: MessageKey };
};

/** Directory pages the ladder itself links to (handoffs repeating them are dropped). */
export const LADDER_HREFS = ["/pharmacies", "/doctors"] as const;

function rungs(pharmaciesOn: boolean): Rung[] {
  return [
    {
      level: "home",
      icon: "home",
      title: "guidance.ladderHomeTitle",
      body: "guidance.ladderHomeBody",
    },
    {
      level: "pharmacy",
      icon: "pill",
      title: "guidance.ladderPharmacyTitle",
      body: "guidance.ladderPharmacyBody",
      link: pharmaciesOn
        ? { href: "/pharmacies", label: "guidance.ladderPharmacyLink" }
        : undefined,
    },
    {
      level: "gp",
      icon: "stethoscope",
      title: "guidance.ladderGpTitle",
      body: "guidance.ladderGpBody",
      link: { href: "/doctors", label: "guidance.ladderGpLink" },
    },
    {
      level: "emergency",
      icon: "phone",
      title: "guidance.ladderEmergencyTitle",
      body: "guidance.ladderEmergencyBody",
    },
  ];
}

export function CareLadder({
  current,
  pharmaciesOn,
}: {
  current: CareLevel | null;
  pharmaciesOn: boolean;
}) {
  return (
    <section
      aria-labelledby="care-ladder-title"
      className="flex flex-col gap-4"
    >
      <div className="flex flex-col gap-2">
        <h2 id="care-ladder-title" className="type-h2 text-ink">
          {t("guidance.ladderTitle")}
        </h2>
        <p className="type-reading measure text-ink-2">
          {t("guidance.ladderIntro")}
        </p>
      </div>
      <ol aria-labelledby="care-ladder-title" className="flex flex-col gap-3">
        {rungs(pharmaciesOn).map((rung, index) => {
          const isCurrent = rung.level === current;

          return (
            <li
              key={rung.level}
              data-level={rung.level}
              aria-current={isCurrent ? "step" : undefined}
              className={cn(
                "card grid grid-cols-[3rem_minmax(0,1fr)] gap-x-4 gap-y-3 p-5 lg:grid-cols-[3rem_minmax(0,1fr)_auto] lg:items-center lg:gap-x-5",
                isCurrent &&
                  "bg-apricot shadow-none ring-2 ring-inset ring-ink",
              )}
            >
              <span
                aria-hidden="true"
                className={cn(
                  "inline-flex size-12 items-center justify-center rounded-full text-ink",
                  isCurrent ? "bg-white" : "bg-sand",
                )}
              >
                <Icon name={rung.icon} size={24} />
              </span>
              <div className="flex min-w-0 flex-col gap-1 self-center">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                  <h3 className="type-h3 text-ink">
                    <span className="sr-only">{index + 1}. </span>
                    {t(rung.title)}
                  </h3>
                  {isCurrent ? (
                    <Tag tone="ink" icon="check">
                      {t("guidance.ladderYourResult")}
                    </Tag>
                  ) : null}
                </div>
                <p className="type-reading text-ink">
                  {t(rung.body)}
                  {rung.level === "emergency" ? (
                    <>
                      {" "}
                      <NoticeTelLink number="194" /> /{" "}
                      <NoticeTelLink number="112" />
                    </>
                  ) : null}
                </p>
              </div>
              {rung.link ? (
                <Button
                  href={rung.link.href}
                  variant={isCurrent ? "primary" : "soft"}
                  trailingIcon="arrow-right"
                  className="col-start-2 self-start lg:col-start-3 lg:self-center"
                >
                  {t(rung.link.label)}
                </Button>
              ) : null}
            </li>
          );
        })}
      </ol>
    </section>
  );
}
