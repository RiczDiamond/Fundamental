<?php

	function email(string $recipient, string $subject, string $message, string $from = MAIL['FROM'], string $from_name = MAIL['NAME'], string $reply_to = '', string $reply_to_name = '', $cc = '', $bcc = '', $attachments = '' ) {

		// Load PHPMailer classes
		require_once DIR . 'resources/includes/phpmailer/src/PHPMailer.php';
		require_once DIR . 'resources/includes/phpmailer/src/SMTP.php';
		require_once DIR . 'resources/includes/phpmailer/src/Exception.php';

		try {

			$mail = new \PHPMailer\PHPMailer\PHPMailer(true);	

			// SMTP configuration
			$mail->isSMTP();
			$mail->Host       = MAIL['HOST'];
			$mail->SMTPSecure = MAIL['SECURE'];
			$mail->SMTPAuth   = true;
			$mail->Username   = MAIL['USER'];
			$mail->Password   = MAIL['PASS'];

			// Set email headers
			$mail->setFrom(

				$from ?: (MAIL['FROM'] ?? ''),
				$from_name ?: (MAIL['NAME'] ?? '')

			);

			$mail->addAddress($recipient);

			if ($reply_to) {

				$mail->addReplyTo($reply_to, $reply_to_name);

			}

			// Add CC recipients
			foreach ((array)$cc as $c) {

				if ($c) {

					$mail->addCC($c);

				}

			}

			// Add BCC recipients
			foreach ((array)$bcc as $b) {

				if ($b) {

					$mail->addBCC($b);

				}

			}

			// Add attachments
			foreach ((array)$attachments as $file) {

				if ($file) {

					$mail->addAttachment($file);

				}

			}

			// Email content
			$mail->isHTML(true);
			$mail->Subject = $subject;
			$mail->Body    = $message;

			return $mail->send() ? true : 'Mailer Error: ' . $mail->ErrorInfo;

		} catch (PHPMailer\PHPMailer\Exception $e) {

			return 'PHPMailer Exception: ' . $e->getMessage();

		} catch (\Exception $e) {

			return 'General Exception: ' . $e->getMessage();

		}
		
	}