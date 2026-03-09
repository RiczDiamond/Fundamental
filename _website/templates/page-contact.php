<?php

require __DIR__ . '/../partials/header.php';

$contactNotice = '';
$contactError = '';

$form = [
	'name' => '',
	'email' => '',
	'subject' => '',
	'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_form']) && $_POST['contact_form'] === '1') {
	if (!mol_require_valid_nonce('public_contact_form')) {
		$contactError = 'Sessie verlopen. Vernieuw de pagina en probeer opnieuw.';
	}

	$form['name'] = sanitize_text_field($_POST['name'] ?? '');
	$form['email'] = sanitize_email($_POST['email'] ?? '');
	$form['subject'] = sanitize_text_field($_POST['subject'] ?? '');
	$form['message'] = sanitize_textarea_field($_POST['message'] ?? '');
	$honeypot = sanitize_text_field($_POST['website'] ?? '');

	if ($contactError !== '') {
		// Nonce invalid; keep form values for user feedback.
	} elseif ($honeypot !== '') {
		$contactError = 'Verzenden mislukt. Probeer opnieuw.';
	} elseif ($form['name'] === '' || $form['email'] === '' || $form['message'] === '') {
		$contactError = 'Naam, e-mail en bericht zijn verplicht.';
	} elseif (!is_email($form['email'])) {
		$contactError = 'Vul een geldig e-mailadres in.';
	} else {
		$pageUrl = (string) ($_SERVER['REQUEST_URI'] ?? '/contact');
		$ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
		$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

		$submissionId = create_contact_submission($link, [
			'name' => $form['name'],
			'email' => $form['email'],
			'subject' => $form['subject'],
			'message' => $form['message'],
			'page_url' => $pageUrl,
			'ip_address' => $ipAddress,
			'user_agent' => $userAgent,
			'mail_sent' => false,
		]);

		if ($submissionId > 0) {
			$contactNotice = 'Bedankt, je bericht is ontvangen. We reageren zo snel mogelijk.';
			$form = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
		} else {
			$contactError = 'Bericht kon niet worden opgeslagen. Probeer later opnieuw.';
		}
	}
}

if (!isset($page) || empty($page)) {
	http_response_code(404);
	echo '<h1>Page not found</h1>';
	require __DIR__ . '/../partials/footer.php';
	return;
}

$pageTitle = esc_html((string) ($page['post_title'] ?? 'Contact'));
// page_content is ignored; layout is driven by flexible sections
$sections = get_page_sections($link, (int) $page['ID']);
?>

<article class="contact-page">

		<?php render_flexible_sections($sections); ?>

	<section class="contact-form-section">
		<?php if ($contactNotice !== ''): ?>
			<div class="contact-notice"><?php echo esc_html($contactNotice); ?></div>
		<?php endif; ?>

		<?php if ($contactError !== ''): ?>
			<div class="contact-error"><?php echo esc_html($contactError); ?></div>
		<?php endif; ?>

		<form method="post" action="" class="contact-form" novalidate>
			<input type="hidden" name="contact_form" value="1">
			<?php mol_nonce_field('public_contact_form'); ?>

			<label for="contact-name">Naam</label>
			<input id="contact-name" type="text" name="name" value="<?php echo esc_attr($form['name']); ?>" required>

			<label for="contact-email">E-mail</label>
			<input id="contact-email" type="email" name="email" value="<?php echo esc_attr($form['email']); ?>" required>

			<label for="contact-subject">Onderwerp (optioneel)</label>
			<input id="contact-subject" type="text" name="subject" value="<?php echo esc_attr($form['subject']); ?>">

			<label for="contact-message">Bericht</label>
			<textarea id="contact-message" name="message" rows="7" required><?php echo esc_textarea($form['message']); ?></textarea>

			<input class="contact-honeypot" type="text" name="website" tabindex="-1" autocomplete="off">

			<button type="submit">Verstuur bericht</button>
		</form>
	</section>
</article>

<?php require __DIR__ . '/../partials/footer.php'; ?>
