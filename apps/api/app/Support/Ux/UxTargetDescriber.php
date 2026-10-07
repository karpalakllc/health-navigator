<?php

namespace App\Support\Ux;

/**
 * Turns a structural target key (`doctor-card/heading`) into words for the
 * admin „UX анализа“ page. Keys never carry page text, so this is the only
 * place that says what they mean. Keep in step with apps/web/src/lib/ux/target.ts.
 */
final class UxTargetDescriber
{
    /** @var array<string, string> */
    private const CONTEXTS = [
        // `data-track` names on components.
        'doctor-card' => 'Картичка на лекар',
        'facility-card' => 'Картичка на установа',
        'pharmacy-card' => 'Картичка на аптека',
        'product-card' => 'Картичка на производ',
        'forum-topic' => 'Тема во листа на форумот',
        'site-nav' => 'Главна навигација (заглавје)',
        'tab-bar' => 'Долна лента со јазичиња (мобилен)',
        'breadcrumbs' => 'Патека до страницата',
        'pagination' => 'Страничење',
        'filters' => 'Филтри',
        'home-how-it-works' => 'Почетна: „Како функционира“',
        'home-forum-band' => 'Почетна: форум и транспарентност',
        // Landmarks, when no component is named.
        'header' => 'Заглавје',
        'nav' => 'Навигација',
        'main' => 'Главна содржина',
        'footer' => 'Подножје',
        'aside' => 'Странична колона',
        'search' => 'Пребарување',
        'dialog' => 'Дијалог',
        'form' => 'Формулар',
        'page' => 'Страница (надвор од делови)',
    ];

    /** @var array<string, string> */
    private const ELEMENTS = [
        'link' => 'линк',
        'button' => 'копче',
        'select' => 'паѓачко мени',
        'textarea' => 'поле за текст',
        'label' => 'ознака на поле',
        'summary' => 'наслов на расклопен дел',
        'focusable' => 'елемент што прима фокус',
        'pointer' => 'елемент со курсор „рака“',
        'heading' => 'наслов',
        'img' => 'слика',
        'icon' => 'икона',
        'text' => 'текст',
        'table' => 'табела',
        'media' => 'медиум',
        'area' => 'празен простор / блок',
        'disabled' => 'исклучена контрола',
    ];

    /** Elements that do nothing when clicked, as the tracker classifies them. */
    private const NON_INTERACTIVE = ['heading', 'img', 'icon', 'text', 'table', 'media', 'area', 'disabled'];

    public static function describe(string $key): string
    {
        [$context, $element] = array_pad(explode('/', $key, 2), 2, '');

        $where = self::CONTEXTS[$context] ?? "„{$context}“";
        $what = self::element($element);

        return in_array($element, self::NON_INTERACTIVE, true)
            ? "{$where} → {$what} (не е интерактивно)"
            : "{$where} → {$what}";
    }

    private static function element(string $element): string
    {
        if (isset(self::ELEMENTS[$element])) {
            return self::ELEMENTS[$element];
        }

        if (str_starts_with($element, 'input-')) {
            return 'поле ('.substr($element, 6).')';
        }

        if (str_starts_with($element, 'role-')) {
            return 'контрола ('.substr($element, 5).')';
        }

        return $element;
    }
}
