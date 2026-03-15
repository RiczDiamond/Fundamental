# Fundamental (PHP CMS)

## Overzicht

Fundamental is een PHP-based CMS / dashboard systeem met:

- **REST API** onder `_api/` (public API endpoints)
- **Dashboard** onder `_dashboard/` voor beheer (account, media, pagina's, etc.)
- **AJAX backend** onder `resources/ajax/` voor frontend acties (login, account, forgot-password)
- **Template-based frontend** onder `_website/`

### Kernprincipes

- **Eenvoudig PHP zonder framework**
- **jQuery** voor frontend AJAX (in `resources/js/admin.js`)
- **CSRF bescherming** via één globale nonce (`resources/php/functions.php`, `resources/ajax/get-nonce.php`)
- **Rate-limiting** voor gevoelige endpoints (login / wachtwoord reset)

---

## Belangrijke locaties

| Pad | Beschrijving |
| --- | --- |
| `_api/` | REST API endpoints (posts, auth, account) |
| `_dashboard/` | Admin UI (login, account, media, pagina's) |
| `resources/ajax/` | AJAX endpoints gebruikt door dashboard JS |
| `resources/js/` | Frontend scripts (admin behavior, jQuery) |
| `resources/php/` | Core PHP logica (init, config, auth, account, functions) |
| `resources/includes/phpmailer/` | PHPMailer library voor SMTP mail |
| `.env` | Configuratie variabelen (DB, mail, auth, etc.) |

---

## Belangrijke bestanden

- `index.php` - Router / entrypoint, routeert naar `_api`, `_dashboard`, `_website`
- `_dashboard/_setup.php` - Laadt de juiste dashboard pagina (login/forgot/dashboard)
- `resources/php/init.php` - Core bootstrap (DB, config, functies, classes)
- `resources/php/functions.php` - Helpers (CSRF, rate limiting, mail)
- `resources/js/admin.js` - Alle dashboard frontend logica/ AJAX calls (CSRF token handling + AJAX actions)
- `resources/ajax/get-nonce.php` - Endpoint om een globale CSRF nonce op te halen voor AJAX
- `resources/ajax/forgot-password.php` - Forgot password endpoint (mail + reset)

---

## Mail / SMTP

- Mail wordt gestuurd via `resources/php/functions.php::email()`.
- Configuratie gebeurt via `.env`:
  - `MAIL_HOST`, `MAIL_USER`, `MAIL_PASS`, etc.
- De code ondersteunt **PHPMailer** (SMTP) en kan debuglog schrijven.

---

## Debuggen

- CSRF / AJAX: `resources/ajax/csrf-debug.log`
- Forgot-password flow: `resources/ajax/forgot-debug.log`
- Mail debug (SMTP): `resources/mail.log` (bij `MAIL_DEBUG=2`)

---

## Aanpassingen en extensies

### Nieuwe API endpoint toevoegen
1. Maak nieuw bestand in `_api/` (bijv. `my-resource.php`)
2. Voeg route toe in `_api/_setup.php`
3. Volg patroon van bestaande endpoints (json output, headers)

### Nieuw dashboard overzicht
1. Voeg view toe in `_dashboard/views/`
2. Registreer route in `_dashboard/_setup.php` of `dashboard.php`
3. Voeg benodigde AJAX endpoint toe onder `resources/ajax/`

---

## Stijl en conventies

- Gebruik `mol_` helpers voor legacy dashboard functies (CSRF, login)
- Houd output clean: API endpoints moeten JSON retourneren
- Vermijd direct `$_POST` gebruik; parse JSON body via `file_get_contents('php://input')`

---

## Bootstrapping

1. Kopieer `.env.example` naar `.env`
2. Vul DB credentials in (`DB_HOST/DB_NAME/DB_USER/DB_PASS`)
3. Vul mail/smtp credentials in voor `MAIL_*`
4. Start lokaal (bijv. Laragon) en open `http://fundamental.test`

---

## Contact

- `resources/php/class_auth.php` voor login/remember-me
- `resources/php/class_account.php` voor gebruikersbeheer
- `resources/php/functions.php` voor shared helpers (nonce, rate limiting, mail)
