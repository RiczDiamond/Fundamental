<?php
// Simple JSON API for dashboard user management
require_once __DIR__ . '/../core/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

$account = $account ?? new Account($link);
$auth = $auth ?? new Auth($link);
$session = $session ?? new Session($link);

$user_id = $_SESSION['user_id'] ?? $session->get('user_id');

$action = $_REQUEST['action'] ?? 'list';

try {
    // ADMIN-only actions
    if (in_array($action, ['list','view','ban','unban','restore'])) {
        if (empty($user_id) || !$auth->perm_check($user_id, 'admin')) {
            http_response_code(403);
            echo json_encode(['error' => 'forbidden']);
            exit;
        }
    }

    if ($action === 'list') {
        $stmt = $link->prepare("SELECT id, username, email, first_name, last_name, display_name, role, status, banned, banned_until, ban_reason, email_verified, last_login, last_ip, deletion_requested_at, trash, created_at, updated_at FROM users ORDER BY created_at DESC LIMIT 200");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'users' => $rows]);
        exit;
    }

    if ($action === 'view') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) throw new Exception('invalid id');
        $user = $account->get($id);
        $groups = $account->get_groups($id);
        $perms = $account->get_permissions($id);
        echo json_encode(['ok' => true, 'user' => $user, 'groups' => $groups, 'permissions' => $perms]);
        exit;
    }

    // current user data (no admin required)
    if ($action === 'me') {
        if (empty($user_id)) throw new Exception('not_logged_in');
        $user = $account->get($user_id);
        $groups = $account->get_groups($user_id);
        $perms = $account->get_permissions($user_id);
        echo json_encode(['ok' => true, 'user' => $user, 'groups' => $groups, 'permissions' => $perms]);
        exit;
    }

    if ($action === 'update') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('method');
        $csrf = $_POST['csrf'] ?? '';
        if (empty($csrf) || $csrf !== $session->get('csrf')) throw new Exception('csrf');

        $id = isset($_POST['id']) ? (int)$_POST['id'] : $user_id;
        if (empty($id)) throw new Exception('invalid id');

        // only allow updating other users when admin
        if ($id !== (int)$user_id && !$auth->perm_check($user_id, 'admin')) {
            throw new Exception('forbidden');
        }

        $data = [];
        $fields = ['username','email','comment','bio','street','street_num','postal','city','country','first_name','last_name','display_name','birth_date'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) $data[$f] = trim($_POST[$f]);
        }

        if (!empty($_POST['password'])) {
            $data['password'] = $_POST['password'];
        }

        $data['id'] = $id;
        $res = $account->put($data);

        // if current user updated own username/display, update session display_name
        if ($res && $id == $user_id && isset($data['username'])) {
            if (method_exists($session,'set')) $session->set('display_name', $data['username']);
        }

        echo json_encode(['ok' => (bool)$res]);
        exit;
    }

    if ($action === 'delete' || $action === 'destroy') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('method');
        $csrf = $_POST['csrf'] ?? '';
        if (empty($csrf) || $csrf !== $session->get('csrf')) throw new Exception('csrf');
        $id = (int)($_POST['id'] ?? $user_id);
        if (empty($id)) throw new Exception('invalid id');

        // only allow deleting other users when admin
        if ($id !== (int)$user_id && !$auth->perm_check($user_id, 'admin')) {
            throw new Exception('forbidden');
        }

        if ($action === 'delete') {
            $res = $account->retrieve($id); // soft-delete
        } else {
            $res = $account->delete($id); // hard delete
        }

        // if user deleted their own account, destroy session
        if ($res && $id == $user_id) {
            if (method_exists($session,'destroy')) $session->destroy();
        }

        echo json_encode(['ok' => (bool)$res]);
        exit;
    }

    if ($action === 'ban' || $action === 'unban') {
        // require POST and CSRF
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('method');
        $csrf = $_POST['csrf'] ?? '';
        if (empty($csrf) || $csrf !== $session->get('csrf')) throw new Exception('csrf');
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) throw new Exception('invalid id');
        if ($action === 'ban') {
            $reason = $_POST['reason'] ?? null;
            $time = isset($_POST['time']) ? (int)$_POST['time'] : null;
            $res = $account->ban($id, $reason, $time);
        } else {
            $res = $account->unban($id);
        }
        echo json_encode(['ok' => (bool)$res]);
        exit;
    }

    // unknown action
    throw new Exception('unknown action');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

