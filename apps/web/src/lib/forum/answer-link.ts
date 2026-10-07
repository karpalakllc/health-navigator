/**
 * The id of a topic page's reply box — the reply form for members, the
 * „Најави се за одговор“ card for guests (whose sign-in returns here). Same
 * value as REPLY_FORM_ID in components/forum/reply-form.tsx, kept in a plain
 * module so server components can build links with it.
 */
export const FORUM_REPLY_ANCHOR = "forum-reply";

/**
 * Where „Одговори“ on an unanswered question leads: straight to the topic's
 * reply box. Guests land on the question with the sign-in card in view rather
 * than on the login page, so they can read what they would be answering first.
 */
export function forumAnswerHref(categorySlug: string, topicSlug: string) {
  return `/forum/${encodeURIComponent(categorySlug)}/${encodeURIComponent(topicSlug)}#${FORUM_REPLY_ANCHOR}`;
}
