/**
 * DRAFT — requires review by a lawyer admitted in North Macedonia before
 * production. Background: docs/legal/research-memo.md. The page's single
 * 194/112 line lives here; the owner wants few emergency mentions elsewhere.
 */

import Link from "next/link";
import { LegalHeading, type LegalSection } from "@/components/legal/legal-page";
import { Notice, NoticeTelLink } from "@/components/ui/notice";

export const disclaimerLastUpdated = "2026-10-06";

/** The h2s, in order (also the page's table of contents). */
export const disclaimerSections = [
  { id: "ne-e-sovet", title: "Не е медицински совет" },
  { id: "nasoki", title: "Насоки за симптоми" },
  { id: "imenik", title: "Именикот" },
  { id: "istaknato", title: "„Истакнат“ и „Спонзорирано“" },
  { id: "recenzii", title: "Рецензии и форум" },
  { id: "proizvodi", title: "Аптеки, производи и цени" },
] as const satisfies readonly LegalSection[];

const [notAdvice, guidance, directory, featured, reviews, products] =
  disclaimerSections;

export function DisclaimerContent() {
  return (
    <>
      <Notice tone="info">
        Нацрт. Текстот чека правен преглед пред јавно објавување.
      </Notice>
      <p>
        Zdravje360 е информативен именик. Помага да најдете лекар, установа или
        аптека и да прочитате туѓи искуства. Не лекува и не советува.
      </p>

      <LegalHeading section={notAdvice} />
      <p>
        Ништо на платформата не е медицински совет, дијагноза или препорака за
        лекување. За вашето здравје разговарајте со лекар или фармацевт. Не
        одложувајте и не прекинувајте лекување поради нешто што сте го прочитале
        тука.
      </p>
      <p>
        Платформата не е служба за итна помош. При итност повикајте{" "}
        <NoticeTelLink number="194" /> или <NoticeTelLink number="112" />.
      </p>

      <LegalHeading section={guidance} />
      <p>
        Насоките за симптоми се општи информации засновани на однапред поставени
        правила и на вашите одговори. Не поставуваат дијагноза, не ја
        проценуваат вашата состојба како што би направил лекар и не
        препорачуваат лекови. Можат само да помогнат да одлучите кој би бил
        следниот чекор.
      </p>

      <LegalHeading section={directory} />
      <p>
        Податоците за лекарите, установите и аптеките ги собираме од јавни
        извори и од самите установи. Може да се променат без наше знаење, затоа
        работното време, достапноста и условите проверете ги директно. Тоа што
        некој е во именикот не значи дека го препорачуваме.
      </p>

      <LegalHeading section={featured} />
      <p>
        „Истакнат“ е избор на нашиот тим и не се плаќа. „Спонзорирано“ означува
        платен профил. Ниту едното ниту другото не е оцена на квалитетот на
        лекувањето. Повеќе во{" "}
        <Link href="/terms#istaknato">условите за користење</Link>.
      </p>

      <LegalHeading section={reviews} />
      <p>
        Рецензиите и објавите се лични мислења на корисниците. Ги проверуваме
        според правилата, но не можеме да ја потврдиме вистинитоста на секое
        искуство и не стоиме зад нив. Едно лошо или добро искуство не мора да
        важи и за вас.
      </p>

      <LegalHeading section={products} />
      <p>
        Податоците за производите и цените се референтни и може да се
        разликуваат од цената во аптеката. Не се понуда за купување и на
        платформата не може ништо да се купи. За лековите прочитајте го
        упатството и прашајте лекар или фармацевт.
      </p>
    </>
  );
}
