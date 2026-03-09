<?php

class Auth {

    private Session $session;
    private Cookie $cookies;
    private Account $account;

    public function __construct(Session $session, Cookie $cookies, Account $account) {
        $this->session   = $session;
        $this->cookies   = $cookies;
        $this->account = $account;
        $this->session->start();
    }

    public function bootstrap_auth(): void {
        if ($this->is_user_logged_in()) return;

        $auth_cookie = $this->cookies->get('logged_in');
        if (!$auth_cookie) return;

        $cookie_data = $this->parse_auth_cookie($auth_cookie);
        if (!$cookie_data) {
            $this->cookies->delete('logged_in');
            return;
        }

        $token_record = $this->account->get_remember_token($cookie_data['selector']);
        if (!$token_record || (int)$token_record['expires_at'] < time()) {
            $this->account->delete_remember_token($cookie_data['selector']);
            $this->cookies->delete('logged_in');
            return;
        }

        $validator_hash = hash('sha256', $cookie_data['validator']);
        if (!hash_equals($token_record['token_hash'], $validator_hash)) {
            $this->account->delete_remember_token($cookie_data['selector']);
            $this->cookies->delete('logged_in');
            return;
        }

        $user_data = $this->account->get_user_by_id((int)$token_record['user_id']);
        if (!$user_data) {
            $this->account->delete_remember_token($cookie_data['selector']);
            $this->cookies->delete('logged_in');
            return;
        }

        $this->set_auth_cookie($user_data, true);
        $this->account->delete_remember_token($cookie_data['selector']);
    }

    private function parse_auth_cookie(string $raw_cookie): ?array {
        $parts = explode(':', $raw_cookie, 2);
        if (count($parts) !== 2) return null;

        [$selector, $validator] = $parts;
        if (!ctype_xdigit($selector) || !ctype_xdigit($validator)) return null;

        return [
            'selector'  => strtolower($selector),
            'validator' => strtolower($validator),
        ];
    }

    public function is_user_logged_in(): bool {
        $user = $this->session->get('current_user');
        return is_array($user) && isset($user['id']) && is_numeric($user['id']);
    }

    public function get_current_user(): ?array {
        return $this->session->get('current_user');
    }

    public function set_auth_cookie(array $user_data, bool $remember = false): void {
        $this->session->regenerate();
        $this->session->set('current_user', $user_data);

        // Backwards-compat: set simple user_id for older dashboard pages
        if (isset($user_data['id'])) {
            $this->session->set('user_id', (int) $user_data['id']);
        }

        if ($remember) {
            $selector    = bin2hex(random_bytes(9));
            $validator   = bin2hex(random_bytes(32));
            $dayInSeconds = 86400;
            $expiration  = time() + (30 * $dayInSeconds);

            $this->account->save_remember_token($selector, [
                'user_id'    => (int) ($user_data['id'] ?? 0),
                'token_hash' => hash('sha256', $validator),
                'expires_at' => $expiration,
            ]);

            $this->cookies->set('logged_in', "$selector:$validator", $expiration);
        }
    }

    public function logout(): void {
        $auth_cookie = $this->cookies->get('logged_in');
        if ($auth_cookie) {
            [$selector] = explode(':', $auth_cookie, 2);
            $this->account->delete_remember_token($selector);
        }
        $this->cookies->delete('logged_in');
        $this->session->destroy();
    }
}