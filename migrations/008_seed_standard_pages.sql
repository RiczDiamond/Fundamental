SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

INSERT INTO `pages` (
  `title`,
  `slug`,
  `excerpt`,
  `content`,
  `builder_json`,
  `template`,
  `page_type`,
  `meta_title`,
  `meta_description`,
  `status`,
  `published_at`
) VALUES
(
  'Home',
  'home',
  'Welkom bij Fundamental CMS.',
  'Welkom op onze website. Hieronder vind je de belangrijkste informatie.',
  '[{"type":"hero","data":{"title":"Bouw sneller met Fundamental","subtitle":"Een flexibele basis voor content, pages en navigatie.","cta_label":"Neem contact op","cta_url":"/contact"}},{"type":"text","data":{"content":"Wij helpen teams met snelle websites die makkelijk te beheren zijn."}},{"type":"columns","data":{"columns":[{"heading":"Snel","content":"Binnen enkele dagen live met een duidelijke structuur."},{"heading":"Beheerbaar","content":"Redacteuren kunnen paginas en blokken zelfstandig beheren."},{"heading":"Schaalbaar","content":"Uit te breiden met blog, media en workflows."}]}},{"type":"cta","data":{"label":"Lees meer over ons","url":"/over-ons","style":"primary"}}]',
  'landing',
  'landing_page',
  'Home | Fundamental CMS',
  'Welkom op de homepagina van Fundamental CMS.',
  'published',
  NOW()
),
(
  'Over ons',
  'over-ons',
  'Wie we zijn en hoe we werken.',
  'Wij zijn een compact team dat focust op duidelijke websites met sterke content-structuur.',
  '[{"type":"text","data":{"content":"Ons team bestaat uit developers, designers en contentspecialisten."}},{"type":"image","data":{"src":"/uploads/media/2026/03/image-4-20260306202030-591ac330.png","alt":"Ons team","caption":"Samen bouwen we aan duurzame websites."}},{"type":"quote","data":{"quote":"Goede websites zijn niet alleen mooi, maar vooral duidelijk en onderhoudbaar.","author":"Team Fundamental"}},{"type":"cta","data":{"label":"Plan een kennismaking","url":"/contact","style":"secondary"}}]',
  'default',
  'basic_page',
  'Over ons | Fundamental CMS',
  'Lees meer over het team achter Fundamental CMS.',
  'published',
  NOW()
),
(
  'Contact',
  'contact',
  'Neem contact met ons op.',
  'Heb je vragen of wil je samenwerken? Laat een bericht achter.',
  '[{"type":"hero","data":{"title":"Contact opnemen","subtitle":"We reageren meestal binnen 1 werkdag.","cta_label":"Bel direct","cta_url":"tel:+31201234567"}},{"type":"text","data":{"content":"Gebruik het formulier of stuur een e-mail naar info@example.com."}},{"type":"contact_form","data":{"title":"Stuur ons een bericht","email_to":"info@example.com"}},{"type":"map","data":{"title":"Ons kantoor","embed_url":"https://maps.google.com/maps?q=Amsterdam&t=&z=13&ie=UTF8&iwloc=&output=embed"}}]',
  'contact',
  'contact_page',
  'Contact | Fundamental CMS',
  'Contactpagina van Fundamental CMS.',
  'published',
  NOW()
)
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `excerpt` = VALUES(`excerpt`),
  `content` = VALUES(`content`),
  `builder_json` = VALUES(`builder_json`),
  `template` = VALUES(`template`),
  `page_type` = VALUES(`page_type`),
  `meta_title` = VALUES(`meta_title`),
  `meta_description` = VALUES(`meta_description`),
  `status` = VALUES(`status`),
  `published_at` = VALUES(`published_at`),
  `updated_at` = NOW();

INSERT INTO `menu_items` (`parent_id`, `location`, `label`, `url`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT NULL, 'main', 'Home', '/', 10, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `menu_items` WHERE `location` = 'main' AND `url` = '/'
);

INSERT INTO `menu_items` (`parent_id`, `location`, `label`, `url`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT NULL, 'main', 'Over ons', '/over-ons', 20, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `menu_items` WHERE `location` = 'main' AND `url` = '/over-ons'
);

INSERT INTO `menu_items` (`parent_id`, `location`, `label`, `url`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT NULL, 'main', 'Contact', '/contact', 30, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `menu_items` WHERE `location` = 'main' AND `url` = '/contact'
);

COMMIT;
