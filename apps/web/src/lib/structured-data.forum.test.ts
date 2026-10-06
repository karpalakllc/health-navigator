import { describe, expect, it } from "vitest";
import { schemaProblems } from "../../test/schema-sanity";
import {
  breadcrumbJsonLd,
  forumTopicJsonLd,
  physicianJsonLd,
  websiteJsonLd,
} from "@/lib/structured-data";

const topic = {
  title: "Операција за проширени вени",
  body: "Кој има искуство со операција на вени во Скопје?",
  author_name: "ana_m",
  published_at: "2026-10-01T10:00:00+02:00",
  last_post_at: "2026-10-05T09:00:00+02:00",
  replies_count: 2,
  tags: [
    { name: "проширени вени", slug: "prosireni-veni", latin: "prosireni veni" },
  ],
};

const posts = [
  {
    id: 11,
    body: "Јас оперирав пред две години, добро искуство.",
    author_name: "marko",
    published_at: "2026-10-02T10:00:00+02:00",
  },
  {
    id: 12,
    body: "Колку чинеше?",
    author_name: "ana_m",
    published_at: "2026-10-05T09:00:00+02:00",
  },
];

const url = "https://zdravje.test/forum/hirurgija/operacija";
const category = {
  name: "Хирургија",
  url: "https://zdravje.test/forum/hirurgija",
};

describe("forumTopicJsonLd", () => {
  it("is a DiscussionForumPosting with the replies as Comments", () => {
    const data = forumTopicJsonLd({ topic, posts, url, category });

    expect(data).toMatchObject({
      "@context": "https://schema.org",
      "@type": "DiscussionForumPosting",
      url,
      mainEntityOfPage: url,
      headline: topic.title,
      text: topic.body,
      author: { "@type": "Person", name: "ana_m" },
      datePublished: topic.published_at,
      dateModified: topic.last_post_at,
      keywords: "проширени вени",
      isPartOf: { "@type": "WebPage", name: "Хирургија", url: category.url },
      interactionStatistic: {
        "@type": "InteractionCounter",
        interactionType: "https://schema.org/CommentAction",
        userInteractionCount: 2,
      },
    });
    expect(data?.comment).toEqual([
      {
        "@type": "Comment",
        url: `${url}#post-11`,
        author: { "@type": "Person", name: "marko" },
        datePublished: posts[0].published_at,
        text: posts[0].body,
      },
      expect.objectContaining({ url: `${url}#post-12` }),
    ]);
    expect(schemaProblems(data!)).toEqual([]);
  });

  it("does not invent a profile URL for authors (members have none)", () => {
    const data = forumTopicJsonLd({ topic, posts, url, category });

    expect(JSON.stringify(data)).not.toContain(
      '"author":{"@type":"Person","name":"ana_m","url"',
    );
  });

  it("leaves out comment, keywords and dateModified when there is nothing to say", () => {
    const data = forumTopicJsonLd({
      topic: {
        ...topic,
        tags: [],
        replies_count: 0,
        last_post_at: topic.published_at,
      },
      posts: [],
      url,
      category,
    });

    expect(data).not.toHaveProperty("comment");
    expect(data).not.toHaveProperty("keywords");
    expect(data).not.toHaveProperty("dateModified");
    expect(schemaProblems(data!)).toEqual([]);
  });

  it("skips removed-reply placeholders (no author, no text)", () => {
    const data = forumTopicJsonLd({
      topic,
      posts: [{ id: 99, removed: true }, posts[1]],
      url,
      category,
    });

    expect(data?.comment).toEqual([
      expect.objectContaining({ url: `${url}#post-12` }),
    ]);
    expect(schemaProblems(data!)).toEqual([]);
  });

  it("is not emitted without a publication date, and skips undated replies", () => {
    expect(
      forumTopicJsonLd({
        topic: { ...topic, published_at: null },
        posts,
        url,
        category,
      }),
    ).toBeNull();

    const data = forumTopicJsonLd({
      topic,
      posts: [{ ...posts[0], published_at: null }, posts[1]],
      url,
      category,
    });
    expect(data?.comment).toHaveLength(1);
  });
});

describe("breadcrumbJsonLd", () => {
  it("numbers the trail and lets the last item stand for the page", () => {
    const data = breadcrumbJsonLd([
      { name: "Почетна", url: "https://zdravje.test/" },
      { name: "Форум", url: "https://zdravje.test/forum" },
      { name: "Тема" },
    ]);

    expect(data).toEqual({
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      itemListElement: [
        {
          "@type": "ListItem",
          position: 1,
          name: "Почетна",
          item: "https://zdravje.test/",
        },
        {
          "@type": "ListItem",
          position: 2,
          name: "Форум",
          item: "https://zdravje.test/forum",
        },
        { "@type": "ListItem", position: 3, name: "Тема" },
      ],
    });
    expect(schemaProblems(data!)).toEqual([]);
  });

  it("is not emitted for fewer than two items", () => {
    expect(breadcrumbJsonLd([{ name: "Почетна" }])).toBeNull();
  });
});

const doctor = {
  full_name: "Елена Димитрова",
  avatar_url: null,
  city: "Скопје",
  phone: "02 123 456",
  specialties: [
    { slug: "interna", name: "Интерна медицина", is_primary: false },
    { slug: "kardio", name: "Кардиологија", is_primary: true },
  ],
  procedures: ["Ехокардиографија"],
  languages: ["Македонски", "Англиски"],
  facilities: [
    {
      slug: "kb",
      name: "Клиничка болница",
      type: "hospital" as const,
      city: "Скопје",
      is_primary: true,
    },
    {
      slug: "ks",
      name: "Клиника Софија",
      type: "clinic" as const,
      city: "Скопје",
      is_primary: false,
    },
  ],
  accepts_new_patients: true,
  review_summary: { count: 0, average_rating: null },
};

describe("physicianJsonLd", () => {
  const facilityUrl = (f: { slug: string }) =>
    `https://zdravje.test/facilities/${f.slug}`;

  it("states only what the profile shows, specialties as text", () => {
    const data = physicianJsonLd(
      doctor,
      "https://zdravje.test/doctors/elena",
      facilityUrl,
    );

    expect(data).toEqual({
      "@context": "https://schema.org",
      "@type": "Physician",
      name: "Елена Димитрова",
      url: "https://zdravje.test/doctors/elena",
      knowsAbout: ["Кардиологија", "Интерна медицина"],
      address: {
        "@type": "PostalAddress",
        addressLocality: "Скопје",
        addressCountry: "MK",
      },
      telephone: "02 123 456",
      knowsLanguage: ["Македонски", "Англиски"],
      availableService: [
        { "@type": "MedicalProcedure", name: "Ехокардиографија" },
      ],
      hospitalAffiliation: [
        {
          "@type": "Hospital",
          name: "Клиничка болница",
          url: "https://zdravje.test/facilities/kb",
        },
      ],
      isAcceptingNewPatients: true,
    });
    // Free text is not a value of schema.org's MedicalSpecialty enumeration.
    expect(data).not.toHaveProperty("medicalSpecialty");
    expect(schemaProblems(data)).toEqual([]);
  });

  it("adds an aggregateRating only with published reviews behind it", () => {
    const rated = physicianJsonLd(
      { ...doctor, review_summary: { count: 7, average_rating: 4.6 } },
      "u",
      facilityUrl,
    );

    expect(rated.aggregateRating).toEqual({
      "@type": "AggregateRating",
      ratingValue: 4.6,
      reviewCount: 7,
      bestRating: 5,
      worstRating: 1,
    });
    expect(schemaProblems({ ...rated, url: "https://zdravje.test/u" })).toEqual(
      [],
    );
    expect(physicianJsonLd(doctor, "u", facilityUrl)).not.toHaveProperty(
      "aggregateRating",
    );
  });
});

describe("websiteJsonLd", () => {
  it("names the site", () => {
    expect(
      websiteJsonLd("Zdravje360", "https://zdravje.test/", "Опис"),
    ).toMatchObject({
      "@type": "WebSite",
      name: "Zdravje360",
      url: "https://zdravje.test/",
      inLanguage: "mk",
    });
  });
});

describe("schemaProblems (the sanity check itself)", () => {
  it("catches a posting without an author name or text, and null values", () => {
    expect(
      schemaProblems({
        "@context": "https://schema.org",
        "@type": "DiscussionForumPosting",
        author: { "@type": "Person" },
        datePublished: "yesterday",
        headline: null,
      }),
    ).toEqual([
      "$<DiscussionForumPosting>: author.name is required",
      "$<DiscussionForumPosting>: datePublished must be an ISO 8601 date",
      "$<DiscussionForumPosting>: one of text, image or video is required",
      "$.headline: null/undefined value",
    ]);
  });
});
