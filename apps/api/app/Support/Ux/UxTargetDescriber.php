<?php

namespace App\Support\Ux;

/**
 * Turns a structural target key (`doctor-card/heading`) into words for the
 * admin “UX analysis” page. Keys never carry page text, so this is the only
 * place that says what they mean. Every context and element in UxSchema has
 * words here (UxTargetDescriberTest).
 */
final class UxTargetDescriber
{
    /** @var array<string, string> */
    private const CONTEXTS = [
        // `data-track` names on components.
        'doctor-card' => 'Doctor card',
        'facility-card' => 'Facility card',
        'pharmacy-card' => 'Pharmacy card',
        'forum-topic' => 'Forum topic in the list',
        'site-nav' => 'Main navigation (header)',
        'tab-bar' => 'Bottom tab bar (mobile)',
        'breadcrumbs' => 'Breadcrumbs',
        'pagination' => 'Pagination',
        'home-how-it-works' => 'Home: “How it works”',
        'home-forum-band' => 'Home: forum and transparency',
        'home-help' => 'Home: “Help someone” (unanswered questions)',
        'review-prompt' => 'Profile: “Have you been to…?” prompt',
        // Landmarks, when no component is named.
        'header' => 'Header',
        'nav' => 'Navigation',
        'main' => 'Main content',
        'footer' => 'Footer',
        'aside' => 'Sidebar',
        'search' => 'Search',
        'dialog' => 'Dialog',
        'form' => 'Form',
        'page' => 'Page (outside any section)',
    ];

    /** @var array<string, string> */
    private const ELEMENTS = [
        'link' => 'link',
        'button' => 'button',
        'select' => 'dropdown',
        'textarea' => 'text area',
        'label' => 'field label',
        'summary' => 'collapsible section title',
        'focusable' => 'focusable element',
        'pointer' => 'element with a pointer cursor',
        'heading' => 'heading',
        'img' => 'image',
        'icon' => 'icon',
        'text' => 'text',
        'table' => 'table',
        'media' => 'media',
        'area' => 'empty space / block',
        'disabled' => 'disabled control',
    ];

    /** Elements that do nothing when clicked, as the tracker classifies them. */
    private const NON_INTERACTIVE = ['heading', 'img', 'icon', 'text', 'table', 'media', 'area', 'disabled'];

    public static function knows(string $context): bool
    {
        return isset(self::CONTEXTS[$context]);
    }

    public static function knowsElement(string $element): bool
    {
        return isset(self::ELEMENTS[$element])
            || str_starts_with($element, 'input-')
            || str_starts_with($element, 'role-');
    }

    public static function describe(string $key): string
    {
        [$context, $element] = array_pad(explode('/', $key, 2), 2, '');

        $where = self::CONTEXTS[$context] ?? "„{$context}“";
        $what = self::element($element);

        return in_array($element, self::NON_INTERACTIVE, true)
            ? "{$where} → {$what} (not interactive)"
            : "{$where} → {$what}";
    }

    private static function element(string $element): string
    {
        if (isset(self::ELEMENTS[$element])) {
            return self::ELEMENTS[$element];
        }

        if (str_starts_with($element, 'input-')) {
            return 'input ('.substr($element, 6).')';
        }

        if (str_starts_with($element, 'role-')) {
            return 'control ('.substr($element, 5).')';
        }

        return $element;
    }
}
