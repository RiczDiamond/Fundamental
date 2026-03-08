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
  'Schaalbare websites met duidelijke contentstructuur en snelle publicatieflows.',
  '[{"type":"hero","data":{"title":"Bouw sneller met Fundamental","subtitle":"Van idee tot live website in korte iteraties.","cta_label":"Plan een intake","cta_url":"/contact"}},{"type":"columns","data":{"columns":[{"heading":"Snel","content":"Van concept naar publicatie met herbruikbare blokken."},{"heading":"Duidelijk","content":"Elke pagina heeft een heldere rol en CTA."},{"heading":"Schaalbaar","content":"Groei door met blog, contenttypes en workflows."}]}},{"type":"cta","data":{"label":"Bekijk onze werkwijze","url":"/werkwijze","style":"primary"}}]',
  'landing',
  'landing_page',
  'Home | Fundamental CMS',
  'Homepagina van Fundamental CMS.',
  'published',
  NOW()
),
(
  'Over ons',
  'over-ons',
  'Wie we zijn en hoe we teams helpen.',
  'Wij zijn een compact team van developers, designers en contentspecialisten.',
  '[{"type":"text","data":{"content":"Wij helpen teams websites bouwen die snel laden en makkelijk te beheren zijn."}},{"type":"quote","data":{"quote":"Goed webwerk is helder, schaalbaar en onderhoudbaar.","author":"Team Fundamental"}},{"type":"cta","data":{"label":"Neem contact op","url":"/contact","style":"secondary"}}]',
  'default',
  'basic_page',
  'Over ons | Fundamental CMS',
  'Meer over het team achter Fundamental CMS.',
  'published',
  NOW()
),
(
  'Contact',
  'contact',
  'Neem contact met ons op.',
  'Stuur ons een bericht. We reageren meestal binnen 1 werkdag.',
  '[{"type":"hero","data":{"title":"Contact opnemen","subtitle":"Vertel kort waar je hulp bij zoekt.","cta_label":"Bel direct","cta_url":"tel:+31201234567"}},{"type":"contact_form","data":{"title":"Stuur ons een bericht","email_to":"info@example.com"}},{"type":"map","data":{"title":"Ons kantoor","embed_url":"https://maps.google.com/maps?q=Amsterdam&t=&z=13&ie=UTF8&iwloc=&output=embed"}}]',
  'contact',
  'contact_page',
  'Contact | Fundamental CMS',
  'Contactpagina van Fundamental CMS.',
  'published',
  NOW()
),
(
  'Werkwijze',
  'werkwijze',
  'Onze aanpak van intake tot oplevering.',
  'In vier fasen werken we van strategie naar livegang met meetbaar resultaat.',
  '[{"type":"text","data":{"content":"Fase 1: intake en doelen. Fase 2: structuur en ontwerp. Fase 3: bouw en content. Fase 4: optimalisatie."}},{"type":"cta","data":{"label":"Bekijk tarieven","url":"/tarieven","style":"primary"}}]',
  'default',
  'basic_page',
  'Werkwijze | Fundamental CMS',
  'Zo werkt Fundamental van intake tot livegang.',
  'published',
  NOW()
),
(
  'Tarieven',
  'tarieven',
  'Transparante prijzen voor websites en doorontwikkeling.',
  'Kies een traject dat past bij je doel: sprint, groei of onderhoud.',
  '[{"type":"columns","data":{"columns":[{"heading":"Sprint","content":"Vanaf EUR 4.500 voor een compacte livegang."},{"heading":"Groei","content":"Vanaf EUR 1.250 per maand voor doorontwikkeling."},{"heading":"Onderhoud","content":"Vanaf EUR 450 per maand voor support en updates."}]}},{"type":"cta","data":{"label":"Plan een intake","url":"/contact","style":"primary"}}]',
  'default',
  'basic_page',
  'Tarieven | Fundamental CMS',
  'Prijsinformatie voor onze diensten.',
  'published',
  NOW()
),
(
  'Support',
  'support',
  'Hulp bij gebruik, content en publicatie.',
  'Vind snelle antwoorden in onze kennisbank of neem contact op met support.',
  '[{"type":"text","data":{"content":"Voor functionele vragen kijk je eerst in de kennisbank. Voor urgente hulp neem je direct contact op."}},{"type":"cta","data":{"label":"Naar kennisbank","url":"/kennisbank","style":"secondary"}}]',
  'default',
  'basic_page',
  'Support | Fundamental CMS',
  'Support en hulp voor editors en beheerders.',
  'published',
  NOW()
),
(
  'Privacyverklaring',
  'privacy',
  'Hoe wij omgaan met persoonsgegevens.',
  'Wij verwerken alleen gegevens die nodig zijn voor dienstverlening en communicatie.',
  '[{"type":"text","data":{"content":"In deze privacyverklaring leggen we uit welke gegevens we bewaren en waarom."}}]',
  'default',
  'basic_page',
  'Privacy | Fundamental CMS',
  'Privacyverklaring van Fundamental CMS.',
  'published',
  NOW()
),
(
  'Algemene voorwaarden',
  'algemene-voorwaarden',
  'Voorwaarden voor samenwerking en levering.',
  'Hier lees je de afspraken over offertes, levering en aansprakelijkheid.',
  '[{"type":"text","data":{"content":"Deze voorwaarden zijn van toepassing op alle offertes, opdrachten en leveringen."}}]',
  'default',
  'basic_page',
  'Algemene voorwaarden | Fundamental CMS',
  'Algemene voorwaarden van Fundamental CMS.',
  'published',
  NOW()
),
(
  'Cookiebeleid',
  'cookiebeleid',
  'Gebruik van cookies en vergelijkbare technieken.',
  'We gebruiken functionele en analytische cookies om de website te verbeteren.',
  '[{"type":"text","data":{"content":"In dit cookiebeleid lees je welke cookies we gebruiken en hoe je voorkeuren kunt aanpassen."}}]',
  'default',
  'basic_page',
  'Cookiebeleid | Fundamental CMS',
  'Cookiebeleid van Fundamental CMS.',
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

INSERT INTO `content_items` (
  `type`,
  `title`,
  `slug`,
  `excerpt`,
  `content`,
  `featured_image`,
  `payload_json`,
  `meta_title`,
  `meta_description`,
  `status`,
  `published_at`,
  `starts_at`,
  `ends_at`,
  `created_by`,
  `updated_by`
) VALUES
(
  'services',
  'Website Sprint',
  'website-sprint',
  'Binnen 2 weken van idee naar live MVP met heldere structuur.',
  'Een kort traject met strategie, design en implementatie in vaste stappen.',
  '/uploads/media/2026/03/image-4-20260306202030-591ac330.png',
  JSON_OBJECT('cta_label', 'Plan intake', 'cta_url', '/contact', 'price_hint', 'Vanaf EUR 4.500'),
  'Website Sprint | Fundamental CMS',
  'Snel live met een compacte websitesprint.',
  'published',
  NOW() - INTERVAL 10 DAY,
  NULL,
  NULL,
  NULL,
  NULL
),
(
  'portfolio',
  'Relaunch voor Bureau Noord',
  'relaunch-bureau-noord',
  'Nieuwe site-architectuur met duidelijke serviceflows en sterkere conversie.',
  'Modulaire website met contentmodel dat redacteuren zelfstandig beheren.',
  '/uploads/media/2026/03/image-4-20260306202030-591ac330.png',
  JSON_OBJECT('client_name', 'Bureau Noord', 'result_metric', '+38% meer offerte-aanvragen', 'quote', 'Het team publiceert nu zelfstandig.'),
  'Case Bureau Noord | Fundamental CMS',
  'Case over relaunch en betere conversie.',
  'published',
  NOW() - INTERVAL 9 DAY,
  NULL,
  NULL,
  NULL,
  NULL
),
(
  'team',
  'Sanne de Vries',
  'sanne-de-vries',
  'Lead UX met focus op duidelijke customer journeys.',
  'Sanne begeleidt discovery workshops en vertaalt doelen naar pagina-opbouw.',
  '/uploads/media/2026/03/image-4-20260306202030-591ac330.png',
  JSON_OBJECT('role', 'Lead UX', 'expertise', 'Information architecture, UX writing, conversion', 'linkedin_url', 'https://www.linkedin.com/'),
  'Sanne de Vries | Team | Fundamental CMS',
  'Maak kennis met Sanne, Lead UX.',
  'published',
  NOW() - INTERVAL 8 DAY,
  NULL,
  NULL,
  NULL,
  NULL
),
(
  'jobs',
  'Frontend Developer',
  'frontend-developer',
  'Bouw toegankelijke interfaces binnen een modulair CMS-team.',
  'Je werkt met PHP, componenten en contentgedreven frontendstructuren.',
  '/uploads/media/2026/03/image-4-20260306202030-591ac330.png',
  JSON_OBJECT('location', 'Amsterdam / hybride', 'employment_type', 'Fulltime', 'apply_url', '/contact'),
  'Vacature Frontend Developer | Fundamental CMS',
  'Solliciteer op de rol Frontend Developer.',
  'published',
  NOW() - INTERVAL 7 DAY,
  NOW() - INTERVAL 7 DAY,
  NOW() + INTERVAL 45 DAY,
  NULL,
  NULL
),
(
  'testimonials',
  'Review van Studio Lumen',
  'review-studio-lumen',
  'Een strak traject met helder projectmanagement en sterke output.',
  'Fundamental hielp ons met structuur, snelheid en betere conversie op kernpagina\'s.',
  '/uploads/media/2026/03/image-4-20260306202030-591ac330.png',
  JSON_OBJECT('client_name', 'Studio Lumen', 'client_role', 'Marketing lead', 'rating', '5'),
  'Review Studio Lumen | Fundamental CMS',
  'Lees de ervaring van Studio Lumen.',
  'published',
  NOW() - INTERVAL 6 DAY,
  NULL,
  NULL,
  NULL,
  NULL
),
(
  'faq',
  'Hoe snel kunnen we live?',
  'hoe-snel-kunnen-we-live',
  'Gemiddeld ben je binnen 2 tot 6 weken live afhankelijk van scope.',
  'Na intake maken we een planning met duidelijke mijlpalen en opleverdata.',
  '/uploads/media/2026/03/image-4-20260306202030-591ac330.png',
  JSON_OBJECT('question', 'Hoe snel kunnen we live?', 'topic', 'Planning', 'answer', 'Meestal binnen 2 tot 6 weken afhankelijk van omvang en feedbackcycli.'),
  'FAQ: Hoe snel live? | Fundamental CMS',
  'Antwoord op veelgestelde vraag over livegang.',
  'published',
  NOW() - INTERVAL 5 DAY,
  NULL,
  NULL,
  NULL,
  NULL
),
(
  'events',
  'Webinar: Content workflows opschalen',
  'webinar-content-workflows-opschalen',
  'Praktische sessie over rollen, review en publiceren zonder bottlenecks.',
  'In 45 minuten laten we zien hoe teams meer output halen uit dezelfde redactiecapaciteit.',
  '/uploads/media/2026/03/image-4-20260306202030-591ac330.png',
  JSON_OBJECT('location', 'Online', 'register_url', '/contact', 'event_kind', 'Webinar'),
  'Webinar content workflows | Fundamental CMS',
  'Schrijf je in voor het webinar over content workflows.',
  'published',
  NOW() - INTERVAL 4 DAY,
  NOW() + INTERVAL 8 DAY,
  NOW() + INTERVAL 8 DAY + INTERVAL 90 MINUTE,
  NULL,
  NULL
),
(
  'downloads',
  'Checklist: Website livegang',
  'checklist-website-livegang',
  'Een korte checklist om content, redirects en SEO te controleren.',
  'Gebruik deze checklist tijdens pre-live en direct na publicatie.',
  '/uploads/media/2026/03/image-4-20260306202030-591ac330.png',
  JSON_OBJECT('file_url', '/uploads/media/2026/03/image-4-20260306202030-591ac330.png', 'file_type', 'PDF', 'file_size', '1.2 MB'),
  'Download checklist livegang | Fundamental CMS',
  'Download de website-livegang checklist.',
  'published',
  NOW() - INTERVAL 3 DAY,
  NULL,
  NULL,
  NULL,
  NULL
),
(
  'knowledge_base',
  'Hoe maak ik een pagina-template?',
  'hoe-maak-ik-een-pagina-template',
  'Stap-voor-stap uitleg voor templates en page presets in het dashboard.',
  'Je leert hoe je een template kiest, blokken opbouwt en metadata correct instelt.',
  '/uploads/media/2026/03/image-4-20260306202030-591ac330.png',
  JSON_OBJECT('topic', 'Pages', 'difficulty', 'Beginner', 'support_url', '/support'),
  'Kennisbank: pagina template | Fundamental CMS',
  'Handleiding voor pagina-templates in Fundamental CMS.',
  'published',
  NOW() - INTERVAL 2 DAY,
  NULL,
  NULL,
  NULL,
  NULL
),
(
  'partners',
  'Partner: CloudDock',
  'partner-clouddock',
  'Hostingpartner voor stabiele deployments en schaalbare omgevingen.',
  'CloudDock ondersteunt onze projecten met snelle servers en monitoring.',
  '/uploads/media/2026/03/image-4-20260306202030-591ac330.png',
  JSON_OBJECT('website_url', 'https://example.com', 'logo_url', '/uploads/media/2026/03/image-4-20260306202030-591ac330.png', 'partnership_type', 'Technologiepartner'),
  'Partner CloudDock | Fundamental CMS',
  'Meer over onze partner CloudDock.',
  'published',
  NOW() - INTERVAL 1 DAY,
  NULL,
  NULL,
  NULL,
  NULL
)
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `excerpt` = VALUES(`excerpt`),
  `content` = VALUES(`content`),
  `featured_image` = VALUES(`featured_image`),
  `payload_json` = VALUES(`payload_json`),
  `meta_title` = VALUES(`meta_title`),
  `meta_description` = VALUES(`meta_description`),
  `status` = VALUES(`status`),
  `published_at` = VALUES(`published_at`),
  `starts_at` = VALUES(`starts_at`),
  `ends_at` = VALUES(`ends_at`),
  `updated_at` = NOW();

INSERT INTO `menu_items` (`parent_id`, `location`, `label`, `url`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT NULL, 'main', 'Werkwijze', '/werkwijze', 25, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `menu_items` WHERE `location` = 'main' AND `url` = '/werkwijze'
);

INSERT INTO `menu_items` (`parent_id`, `location`, `label`, `url`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT NULL, 'main', 'Portfolio', '/portfolio', 35, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `menu_items` WHERE `location` = 'main' AND `url` = '/portfolio'
);

INSERT INTO `menu_items` (`parent_id`, `location`, `label`, `url`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT NULL, 'main', 'Team', '/team', 45, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `menu_items` WHERE `location` = 'main' AND `url` = '/team'
);

INSERT INTO `menu_items` (`parent_id`, `location`, `label`, `url`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT NULL, 'main', 'Kennisbank', '/kennisbank', 50, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `menu_items` WHERE `location` = 'main' AND `url` = '/kennisbank'
);

INSERT INTO `menu_items` (`parent_id`, `location`, `label`, `url`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT NULL, 'main', 'Vacatures', '/vacatures', 55, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `menu_items` WHERE `location` = 'main' AND `url` = '/vacatures'
);

COMMIT;
