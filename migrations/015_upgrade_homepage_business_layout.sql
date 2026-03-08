SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

UPDATE `pages`
SET
  `title` = 'Home',
  `excerpt` = 'Complete bedrijfswebsite met sterke propositie, diensten, cases en conversiegerichte flows.',
  `content` = 'Welkom op onze corporate website. Ontdek diensten, cases en onze werkwijze.',
  `builder_json` = '[{"type":"hero","data":{"title":"De bedrijfswebsite die marketing, sales en recruitment verbindt","subtitle":"Wij bouwen schaalbare websites voor B2B en corporate organisaties, met duidelijke contentstructuur en meetbare groei.","cta_label":"Plan een strategische sessie","cta_url":"/contact"}},{"type":"columns","data":{"columns":[{"heading":"Strategie","content":"Van positionering tot informatiearchitectuur met duidelijke doelen per pagina."},{"heading":"Design & UX","content":"Consistente, toegankelijke interface die vertrouwen opbouwt en converteert."},{"heading":"Doorontwikkeling","content":"Data-gedreven optimalisatie met korte verbetercycli en duidelijke KPI\'s."}]}},{"type":"text","data":{"content":"Waarom bedrijven voor ons kiezen\\n\\n- Heldere governance voor meerdere teams\\n- Snel publiceren zonder technische afhankelijkheid\\n- Meetbare impact op leads, sollicitaties en merkvoorkeur"}},{"type":"cta","data":{"label":"Bekijk onze diensten","url":"/services","style":"primary"}},{"type":"text","data":{"content":"Uitgelichte resultaten\\n\\nOnze trajecten leveren niet alleen een nieuwe website op, maar vooral een betere commerciële en redactionele operatie. Cases tonen verbeteringen in organisch verkeer, demo-aanvragen en sollicitatieconversie."}},{"type":"cta","data":{"label":"Bekijk cases en projecten","url":"/portfolio","style":"secondary"}},{"type":"columns","data":{"columns":[{"heading":"Stap 1: Analyse","content":"Businessdoelen, doelgroepsegmenten en huidige performance in kaart."},{"heading":"Stap 2: Structuur","content":"Paginatypen, templates en contentmodellen bepalen."},{"heading":"Stap 3: Realisatie","content":"Ontwerp, bouw, contentmigratie en training van teams."},{"heading":"Stap 4: Optimalisatie","content":"Doorlopende CRO, SEO en contentverbetering op basis van data."}]}},{"type":"text","data":{"content":"Vertrouwd door teams in industrie, fintech, zorg en zakelijke dienstverlening.\\n\\nWe werken nauw samen met marketing, communicatie, HR en IT om de website als groeiplatform neer te zetten."}},{"type":"cta","data":{"label":"Over ons team","url":"/over-ons","style":"secondary"}},{"type":"text","data":{"content":"Veelgestelde vragen\\n\\nHoe snel kunnen we live? Meestal binnen 2 tot 6 weken afhankelijk van scope.\\nKunnen jullie migreren vanaf WordPress? Ja, inclusief redirects en SEO-validatie.\\nOndersteunen jullie meertalige websites? Ja, met centrale governance en lokale redactierollen."}},{"type":"cta","data":{"label":"Naar alle FAQ","url":"/faq","style":"secondary"}},{"type":"text","data":{"content":"Klaar voor de volgende stap?\\n\\nPlan een vrijblijvende sessie. We laten zien welke website-architectuur en contentflow past bij jullie groeidoelen."}},{"type":"cta","data":{"label":"Neem contact op","url":"/contact","style":"primary"}}]',
  `template` = 'landing',
  `page_type` = 'landing_page',
  `meta_title` = 'Home | Bedrijfswebsite platform | Fundamental CMS',
  `meta_description` = 'Complete bedrijfswebsite met diensten, cases, werkwijze en conversiegerichte contentflows.',
  `status` = 'published',
  `published_at` = COALESCE(`published_at`, NOW()),
  `updated_at` = NOW()
WHERE `slug` = 'home';

COMMIT;
