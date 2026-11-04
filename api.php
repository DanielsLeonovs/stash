<?php
// Dati glabājas data/ mapē (router.php liedz tai piekļūt pa tīklu)
const DATA = __DIR__ . '/data';
if (!is_dir(DATA)) mkdir(DATA, 0700);
if (!file_exists(DATA . '/users.json') && file_exists(__DIR__ . '/users.json')) {
    copy(__DIR__ . '/users.json', DATA . '/users.json');   // vecā faila pārnešana
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
session_start();

const ROLES = ['admin', 'worker', 'sorter'];
const STATUSES = ['Jauns', 'Komplektē', 'Nosūtīts', 'Piegādāts'];

function reply($ok, $msg, $extra = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}
function load($name) {
    $f = DATA . "/$name.json";
    $d = file_exists($f) ? json_decode(file_get_contents($f), true) : [];
    return is_array($d) ? $d : [];
}
function store($name, $d) {
    file_put_contents(DATA . "/$name.json", json_encode(array_values($d), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function nextId($list) {
    $max = 0;
    foreach ($list as $i) $max = max($max, $i['id']);
    return $max + 1;
}

/* ---------- validācija ---------- */
function vLogin($s)  { return is_string($s) && preg_match('/^[A-Za-z0-9_]{3,30}$/', $s); }
function vPass($s)   { return is_string($s) && strlen($s) >= 8 && strlen($s) <= 100; }
function vText($s, $max) { return is_string($s) && mb_strlen($s) >= 1 && mb_strlen($s) <= $max; }
function vLoc($s)    { return is_string($s) && preg_match('/^[\p{L}0-9 \-\/]{0,30}$/u', $s); }
function vInt($v, $min, $max) { return filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]) !== false; }

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];
$action = $_GET['action'] ?? '';
$users = load('users');

/* ---------- publiskās darbības ---------- */
if ($action === 'me') {
    if (empty($_SESSION['login'])) reply(false, 'Nav pieteicies.', [], 401);
    reply(true, 'OK', ['login' => $_SESSION['login'], 'role' => $_SESSION['role'], 'csrf' => $_SESSION['csrf']]);
}

if ($action === 'register') {            // loma netiek piešķirta - to dara administrators
    $login = trim($input['login'] ?? '');
    $password = $input['password'] ?? '';
    if (!vLogin($login)) reply(false, 'Lietotājvārds: 3-30 simboli (burti, cipari, _).');
    if (!vPass($password)) reply(false, 'Parolei jābūt vismaz 8 simbolus garai.');
    foreach ($users as $u) if (strcasecmp($u['login'], $login) === 0) reply(false, 'Šāds lietotājvārds jau eksistē!');
    $users[] = ['login' => $login, 'password' => password_hash($password, PASSWORD_DEFAULT), 'role' => ''];
    store('users', $users);
    reply(true, 'Konts izveidots! Administrators tev piešķirs lomu.');
}

if ($action === 'login') {
    $login = trim($input['login'] ?? '');
    $password = (string)($input['password'] ?? '');
    foreach ($users as $i => $u) {
        if (strcasecmp($u['login'], $login) !== 0) continue;
        $hashed = str_starts_with($u['password'], '$');
        $ok = $hashed ? password_verify($password, $u['password']) : hash_equals($u['password'], $password);
        if (!$ok) break;
        if (!$hashed) { $users[$i]['password'] = password_hash($password, PASSWORD_DEFAULT); store('users', $users); }
        if ($u['role'] === '') reply(false, 'Konts gaida administratora apstiprinājumu (loma nav piešķirta).');
        session_regenerate_id(true);
        $_SESSION = ['login' => $u['login'], 'role' => $u['role'], 'csrf' => bin2hex(random_bytes(16))];
        reply(true, 'OK', ['role' => $u['role']]);
    }
    sleep(1);                             // palēnina paroļu minēšanu
    reply(false, 'Nepareizs lietotājvārds vai parole!');
}

/* ---------- tiesību tabula: darbība => atļautās lomas ---------- */
$all = ROLES;
$perm = [
    'logout' => $all, 'products_list' => $all, 'report' => $all,
    'users_list' => ['admin'], 'user_create' => ['admin'], 'user_update' => ['admin'], 'user_delete' => ['admin'],
    'product_save' => ['admin', 'worker'], 'product_delete' => ['admin'],
    'orders_list' => ['worker'], 'order_save' => ['worker'], 'order_status' => ['worker'], 'order_delete' => ['worker'],
    'place' => ['sorter'],
];
if (!isset($perm[$action])) reply(false, 'Nezināma darbība.', [], 404);
if (empty($_SESSION['login'])) reply(false, 'Nav pieteicies.', [], 401);
if (!in_array($_SESSION['role'], $perm[$action], true)) reply(false, 'Nav piekļuves tiesību!', [], 403);
if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) reply(false, 'Nederīgs pieprasījums.', [], 403);

$me = $_SESSION['login'];

if ($action === 'logout') { session_destroy(); reply(true, 'OK'); }

/* ---------- lietotāji (admin) ---------- */
if ($action === 'users_list') {
    reply(true, 'OK', ['users' => array_map(fn($u) => ['login' => $u['login'], 'role' => $u['role']], $users), 'me' => $me]);
}

if ($action === 'user_create') {
    $login = trim($input['login'] ?? '');
    if (!vLogin($login)) reply(false, 'Lietotājvārds: 3-30 simboli (burti, cipari, _).');
    if (!vPass($input['password'] ?? '')) reply(false, 'Parolei jābūt vismaz 8 simbolus garai.');
    if (!in_array($input['newRole'] ?? '', ROLES, true)) reply(false, 'Izvēlies lomu!');
    foreach ($users as $u) if (strcasecmp($u['login'], $login) === 0) reply(false, 'Šāds lietotājvārds jau eksistē!');
    $users[] = ['login' => $login, 'password' => password_hash($input['password'], PASSWORD_DEFAULT), 'role' => $input['newRole']];
    store('users', $users);
    reply(true, 'Lietotājs izveidots!');
}

if ($action === 'user_update') {
    $login = $input['login'] ?? '';
    $newRole = $input['newRole'] ?? '';
    $newPass = $input['newPassword'] ?? '';
    if (!in_array($newRole, ROLES, true)) reply(false, 'Nederīga loma!');
    if ($newPass !== '' && !vPass($newPass)) reply(false, 'Parolei jābūt vismaz 8 simbolus garai.');
    $admins = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
    foreach ($users as &$u) {
        if ($u['login'] !== $login) continue;
        if ($u['role'] === 'admin' && $newRole !== 'admin' && $admins <= 1) reply(false, 'Nevar noņemt pēdējo administratoru!');
        $u['role'] = $newRole;
        if ($newPass !== '') $u['password'] = password_hash($newPass, PASSWORD_DEFAULT);
        store('users', $users);
        reply(true, 'Lietotājs atjaunināts!');
    }
    reply(false, 'Lietotājs nav atrasts!');
}

if ($action === 'user_delete') {
    $login = $input['login'] ?? '';
    if ($login === $me) reply(false, 'Savu kontu dzēst nevar!');
    store('users', array_filter($users, fn($u) => $u['login'] !== $login));
    reply(true, 'Lietotājs dzēsts!');
}

/* ---------- preces ---------- */
if ($action === 'products_list') reply(true, 'OK', ['products' => load('products')]);

if ($action === 'product_save') {
    $products = load('products');
    $name = trim($input['name'] ?? '');
    $loc = trim($input['location'] ?? '');
    if (!vText($name, 100)) reply(false, 'Nosaukums: 1-100 simboli.');
    if (!vInt($input['qty'] ?? '', 0, 1000000)) reply(false, 'Daudzums: 0-1 000 000.');
    if (!vLoc($loc)) reply(false, 'Atrašanās vieta: līdz 30 simboliem (burti, cipari, - /).');
    $qty = (int)$input['qty'];
    $id = (int)($input['id'] ?? 0);
    if ($id) {
        $found = false;
        foreach ($products as &$p) if ($p['id'] === $id) { $p['name'] = $name; $p['qty'] = $qty; $p['location'] = $loc; $found = true; }
        unset($p);
        if (!$found) reply(false, 'Produkts nav atrasts!');
    } else {
        $products[] = ['id' => nextId($products), 'name' => $name, 'qty' => $qty, 'location' => $loc];
    }
    store('products', $products);
    reply(true, 'Produkts saglabāts!');
}

if ($action === 'product_delete') {
    $id = (int)($input['id'] ?? 0);
    store('products', array_filter(load('products'), fn($p) => $p['id'] !== $id));
    reply(true, 'Produkts dzēsts!');
}

/* ---------- pasūtījumi (worker) ---------- */
if ($action === 'orders_list') reply(true, 'OK', ['orders' => load('orders'), 'statuses' => STATUSES]);

if ($action === 'order_save') {
    $orders = load('orders');
    $products = load('products');
    $client = trim($input['client'] ?? '');
    if (!vText($client, 100)) reply(false, 'Klients: 1-100 simboli.');
    if (!vInt($input['qty'] ?? '', 1, 10000)) reply(false, 'Daudzums: 1-10 000.');
    $qty = (int)$input['qty'];
    $pid = (int)($input['product_id'] ?? 0);
    foreach ($products as &$p) {
        if ($p['id'] !== $pid) continue;
        if ($p['qty'] < $qty) reply(false, 'Noliktavā nepietiek preču!');
        $p['qty'] -= $qty;
        $orders[] = ['id' => nextId($orders), 'product_id' => $pid, 'product' => $p['name'], 'qty' => $qty,
                     'client' => $client, 'status' => 'Jauns', 'date' => date('Y-m-d H:i')];
        store('products', $products);
        store('orders', $orders);
        reply(true, 'Pasūtījums izveidots!');
    }
    reply(false, 'Produkts nav atrasts!');
}

if ($action === 'order_status') {
    $orders = load('orders');
    $id = (int)($input['id'] ?? 0);
    if (!in_array($input['status'] ?? '', STATUSES, true)) reply(false, 'Nederīgs statuss!');
    foreach ($orders as &$o) if ($o['id'] === $id) $o['status'] = $input['status'];
    store('orders', $orders);
    reply(true, 'Statuss nomainīts!');
}

if ($action === 'order_delete') {
    $id = (int)($input['id'] ?? 0);
    store('orders', array_filter(load('orders'), fn($o) => $o['id'] !== $id));
    reply(true, 'Pasūtījums dzēsts!');
}

/* ---------- izvietojums (sorter) ---------- */
if ($action === 'place') {
    $products = load('products');
    $log = load('log');
    $loc = trim($input['location'] ?? '');
    if ($loc === '' || !vLoc($loc)) reply(false, 'Atrašanās vieta: 1-30 simboli (burti, cipari, - /).');
    $id = (int)($input['id'] ?? 0);
    foreach ($products as &$p) {
        if ($p['id'] !== $id) continue;
        $log[] = ['date' => date('Y-m-d H:i'), 'product' => $p['name'], 'from' => $p['location'], 'to' => $loc, 'by' => $me];
        $p['location'] = $loc;
        store('products', $products);
        store('log', $log);
        reply(true, 'Izvietojums saglabāts!');
    }
    reply(false, 'Produkts nav atrasts!');
}

/* ---------- pārskats ---------- */
if ($action === 'report') {
    $products = load('products');
    $byStatus = [];
    foreach (load('orders') as $o) $byStatus[$o['status']] = ($byStatus[$o['status']] ?? 0) + 1;
    reply(true, 'OK', [
        'date' => date('Y-m-d H:i'), 'products' => $products, 'total_qty' => array_sum(array_column($products, 'qty')),
        'low_stock' => array_values(array_filter($products, fn($p) => $p['qty'] < 5)),
        'no_place' => array_values(array_filter($products, fn($p) => $p['location'] === '')),
        'orders' => $byStatus, 'log' => array_slice(array_reverse(load('log')), 0, 10),
    ]);
}
