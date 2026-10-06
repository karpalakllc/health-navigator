/** DRAFT — requires legal review before production promotion. */

import { LegalHeading, type LegalSection } from "@/components/legal/legal-page";
import { Notice } from "@/components/ui/notice";

export const termsLastUpdated = "2026-05-15";

/** The h2s, in order (also the page's table of contents). */
export const termsSections = [
  { id: "beta", title: "Затворена бета" },
  { id: "namena", title: "Информативна намена" },
  { id: "sodrzina", title: "Корисничка содржина" },
  { id: "odgovornost", title: "Ограничување на одговорност" },
] as const satisfies readonly LegalSection[];

const [beta, purpose, content, liability] = termsSections;

export function TermsContent() {
  return (
    <>
      <Notice tone="info">Нацрт за правен преглед.</Notice>
      <p>
        Со користење на Zdravje360 во затворената бета, се согласувате со овие
        услови. Платформата е во развој; функциите може да се менуваат.
      </p>
      <LegalHeading section={beta} />
      <p>
        Пристапот е само со покана. Сметките се креирани од тимот на
        платформата; нема јавна регистрација. Не споделувајте пристап со
        неовластени лица.
      </p>
      <LegalHeading section={purpose} />
      <p>
        Содржината (вклучително директориуми, рецензии, форум и насоки за
        симптоми) е информативна и не претставува медицински совет, дијагноза
        или итна нега.
      </p>
      <LegalHeading section={content} />
      <p>
        Вие сте одговорни за содржината што ја објавувате. Рецензиите и објавите
        се модерираат. Забрането е објавување незаконска, навредлива или
        заблудувачка медицинска содржина.
      </p>
      <LegalHeading section={liability} />
      <p>
        Платформата се обезбедува „како што е“. Не гарантираме точност на
        податоци од трети страни (на пр. цени во аптеки). Погледнете ја и
        страницата за ограничување на одговорност.
      </p>
    </>
  );
}
