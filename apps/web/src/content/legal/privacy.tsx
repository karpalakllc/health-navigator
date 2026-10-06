/** DRAFT — requires legal review before production promotion. */

import { LegalHeading, type LegalSection } from "@/components/legal/legal-page";
import { Notice } from "@/components/ui/notice";

export const privacyLastUpdated = "2026-05-15";

/** The h2s, in order (also the page's table of contents). */
export const privacySections = [
  { id: "podatoci", title: "Податоци што ги обработуваме" },
  { id: "kolacinja", title: "Колачиња (cookies)" },
  { id: "spodeluvanje", title: "Споделување" },
  { id: "prava", title: "Вашите права" },
  { id: "kontakt", title: "Контакт" },
] as const satisfies readonly LegalSection[];

const [data, cookies, sharing, rights, contact] = privacySections;

export function PrivacyContent() {
  return (
    <>
      <Notice tone="info">{/* legal draft */}Нацрт за правен преглед.</Notice>
      <p>
        Zdravje360 („ние“, „платформата“) ја почитува вашата приватност. Оваа
        политика опишува како собираме и користиме податоци во затворената бета
        верзија на веб-страницата.
      </p>
      <LegalHeading section={data} />
      <ul>
        <li>
          Податоци за сметка (име, е-пошта) за членови со сметка доделена од
          тимот.
        </li>
        <li>
          Содржина што ја испраќате (рецензии, објави на форум) по најава.
        </li>
        <li>
          Технички податоци (на пр. IP адреса во серверски дневници) —
          минимизирани каде е можно; не ги користиме за симптомски насоки.
        </li>
        <li>
          Сесии за насоки за симптоми: структурирани одговори и исход, без
          слободен текст за симптоми; задржување според внатрешна политика (на
          пр. 90 дена).
        </li>
      </ul>
      <LegalHeading section={cookies} />
      <p>
        Користиме неопходни колачиња за најава на членови (httpOnly сесија за
        API токен). Не користиме колачиња за рекламирање во оваа бета фаза.
      </p>
      <LegalHeading section={sharing} />
      <p>
        Не продаваме лични податоци. Може да користиме обработувачи (на пр.
        хостинг, Sentry за грешки) со договори за обработка каде што е потребно.
      </p>
      <LegalHeading section={rights} />
      <p>
        Можете да побарате пристап или бришење на податоци со контакт на
        платформата. За затворена бета, контактирајте го администраторот што ви
        ја доделил сметката.
      </p>
      <LegalHeading section={contact} />
      <p>
        За прашања за приватност: [контакт е-пошта — дополнете пред продукција].
      </p>
    </>
  );
}
