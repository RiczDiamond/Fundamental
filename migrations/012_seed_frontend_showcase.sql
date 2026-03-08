SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

INSERT INTO `blogs` (
  `title`,
  `slug`,
  `permalink`,
  `featured_image`,
  `intro`,
  `category`,
  `tags`,
  `meta_title`,
  `meta_description`,
  `excerpt`,
  `content`,
  `status`,
  `published_at`,
  `scheduled_at`,
  `author_id`
)
SELECT
  'Zo bouw je een snellere marketingwebsite',
  'snellere-marketingwebsite-bouwen',
  '/blog/snellere-marketingwebsite-bouwen',
  '/uploads/media/2026/03/image-4-20260306202030-591ac330.png',
  'In dit artikel delen we de 5 keuzes die direct impact hebben op snelheid en beheer.',
  'Strategie',
  'webdesign,performance,cms',
  'Snellere marketingwebsite bouwen | Fundamental CMS',
  'Praktische tips om je website sneller, duidelijker en beter onderhoudbaar te maken.',
  '5 concrete verbeteringen voor een snellere website met betere contentflow.',
  '<h2>Start met heldere pagina-rollen</h2><p>Geef elke pagina een duidelijke functie: informeren, overtuigen of converteren. Dit maakt keuzes in layout en content veel eenvoudiger.</p><h2>Beperk visuele ruis</h2><p>Werk met consistente componenten en een beperkt kleurenpalet. Minder variatie betekent vaak meer rust en betere leesbaarheid.</p><h2>Meet elke release</h2><p>Check snelheid en interactie na iedere content-update. Zo blijft de voorkant snel terwijl het team doorbouwt.</p>',
  'published',
  NOW() - INTERVAL 10 DAY,
  NULL,
  NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `blogs` WHERE `slug` = 'snellere-marketingwebsite-bouwen'
);

INSERT INTO `blogs` (
  `title`,
  `slug`,
  `permalink`,
  `featured_image`,
  `intro`,
  `category`,
  `tags`,
  `meta_title`,
  `meta_description`,
  `excerpt`,
  `content`,
  `status`,
  `published_at`,
  `scheduled_at`,
  `author_id`
)
SELECT
  'Content workflows die echt werken',
  'content-workflows-die-echt-werken',
  '/blog/content-workflows-die-echt-werken',
  '/uploads/media/2026/03/image-4-20260306202030-591ac330.png',
  'Van intake tot publicatie: zo maak je contentbeheer voorspelbaar voor redacteuren.',
  'Content',
  'workflow,redactie,team',
  'Content workflows die echt werken | Fundamental CMS',
  'Richt je contentproces zo in dat teamleden sneller publiceren met minder fouten.',
  'Praktisch model voor redactiewerk met rollen, review en publicatie-afspraken.',
  '<h2>Werk met templates per paginatype</h2><p>Een contactpagina heeft andere velden nodig dan een landingspagina. Templates verkorten de invultijd en verbeteren kwaliteit.</p><h2>Review op vaste momenten</h2><p>Plan korte reviewrondes met duidelijke checklists. Hierdoor voorkom je veel losse correcties na livegang.</p><h2>Publiceer met confidence</h2><p>Wanneer status, metadata en routes kloppen, wordt publiceren een routine in plaats van een risico.</p>',
  'published',
  NOW() - INTERVAL 7 DAY,
  NULL,
  NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `blogs` WHERE `slug` = 'content-workflows-die-echt-werken'
);

INSERT INTO `blogs` (
  `title`,
  `slug`,
  `permalink`,
  `featured_image`,
  `intro`,
  `category`,
  `tags`,
  `meta_title`,
  `meta_description`,
  `excerpt`,
  `content`,
  `status`,
  `published_at`,
  `scheduled_at`,
  `author_id`
)
SELECT
  'Designsystem in 30 dagen opzetten',
  'designsystem-in-30-dagen-opzetten',
  '/blog/designsystem-in-30-dagen-opzetten',
  '/uploads/media/2026/03/image-4-20260306202030-591ac330.png',
  'Een compact, haalbaar plan om van losse stijlen naar een schaalbaar UI-systeem te gaan.',
  'Design',
  'designsystem,frontend,ux',
  'Designsystem in 30 dagen | Fundamental CMS',
  'Roadmap voor teams die een consistente en schaalbare frontend willen neerzetten.',
  'Een 4-weken aanpak met focus op componenten, tokens en documentatie.',
  '<h2>Week 1: inventariseren</h2><p>Breng patronen in kaart en bepaal de kerncomponenten van je platform.</p><h2>Week 2: tokens en basiscomponenten</h2><p>Leg kleuren, spacing en typografie vast in herbruikbare variabelen.</p><h2>Week 3 en 4: implementeren en finetunen</h2><p>Migreer pagina''s stap voor stap en verbeter op basis van gebruikersfeedback.</p>',
  'published',
  NOW() - INTERVAL 3 DAY,
  NULL,
  NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `blogs` WHERE `slug` = 'designsystem-in-30-dagen-opzetten'
);

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
  `created_by`,
  `updated_by`
)
SELECT
  'services',
  'Website Sprint',
  'website-sprint',
  'Binnen 2 weken van idee naar live MVP met heldere structuur.',
  'Onze Website Sprint combineert strategie, design en implementatie in een kort traject met duidelijke milestones.',
  '/uploads/media/2026/03/image-4-20260306202030-591ac330.png',
  JSON_OBJECT(
    'cta_label', 'Plan intake',
    'cta_url', '/contact',
    'price_hint', 'Vanaf EUR 4.500'
  ),
  'Website Sprint | Fundamental CMS',
  'Snel live met een compacte sprint voor websites en landingspaginas.',
  'published',
  NOW() - INTERVAL 6 DAY,
  NULL,
  NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `content_items` WHERE `type` = 'services' AND `slug` = 'website-sprint'
);

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
  `created_by`,
  `updated_by`
)
SELECT
  'portfolio',
  'Relaunch voor Bureau Noord',
  'relaunch-bureau-noord',
  'Nieuwe site-architectuur met duidelijke serviceflows en sterkere conversie.',
  'Voor Bureau Noord ontwikkelden we een modulaire website met een contentmodel dat redacteuren zelfstandig kunnen beheren.',
  '/uploads/media/2026/03/image-4-20260306202030-591ac330.png',
  JSON_OBJECT(
    'client_name', 'Bureau Noord',
    'result_metric', '+38% meer offerte-aanvragen',
    'quote', 'Het team kan nu zonder ontwikkelaars paginas publiceren.'
  ),
  'Case: Bureau Noord | Fundamental CMS',
  'Lees hoe een nieuwe informatiearchitectuur leidde tot meer aanvragen.',
  'published',
  NOW() - INTERVAL 4 DAY,
  NULL,
  NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `content_items` WHERE `type` = 'portfolio' AND `slug` = 'relaunch-bureau-noord'
);

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
  `created_by`,
  `updated_by`
)
SELECT
  'team',
  'Sanne de Vries',
  'sanne-de-vries',
  'Lead UX met focus op duidelijke customer journeys.',
  'Sanne begeleidt discovery workshops en vertaalt klantdoelen naar concrete pagina-opbouw.',
  '/uploads/media/2026/03/image-4-20260306202030-591ac330.png',
  JSON_OBJECT(
    'role', 'Lead UX',
    'expertise', 'Information architecture, UX writing, conversion',
    'linkedin_url', 'https://www.linkedin.com/'
  ),
  'Sanne de Vries | Team | Fundamental CMS',
  'Maak kennis met Sanne, Lead UX binnen het Fundamental team.',
  'published',
  NOW() - INTERVAL 2 DAY,
  NULL,
  NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `content_items` WHERE `type` = 'team' AND `slug` = 'sanne-de-vries'
);

INSERT INTO `menu_items` (`parent_id`, `location`, `label`, `url`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT NULL, 'main', 'Diensten', '/services', 40, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `menu_items` WHERE `location` = 'main' AND `url` = '/services'
);

COMMIT;
