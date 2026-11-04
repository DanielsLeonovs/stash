<?php
header('Content-Type: application/json; charset=utf-8');

$file = __DIR__ . '/users.json';
$roles = ['admin', 'worker', 'sorter'];
$statuses = ['Jauns', 'Komplektē', 'Nosūtīts', 'Piegādāts'];

function reply($success, $message, $extra = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}
function load($name) {
    $f = __DIR__ . "/$name.json";
    $d = file_exists($f) ? json_decode(file_get_contents($f), true) : [];
    return is_array($d) ? $d : [];
}
function store($name, $d) {
    file_put_contents(__DIR__ . "/$name.json", json_encode(array_values($d), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function nextId($list) {
    $max = 0;
    foreach ($list as $i) $max = max($max, $i['id']);
    return $max + 1;
}

$users = load('users');
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];

$action   = $_GET['action'] ?? '';
$login    = trim($input['login'] ?? '');
$password = $input['password'] ?? '';

if ($action === 'register') {
    $role = $input['role'] ?? '';
    if ($login === '' || $password === '') reply(false, 'Aizpildi visus laukus!');
    if (!in_array($role, $roles)) reply(false, 'Izvēlies lomu!');
    foreach ($users as $u) {
        if (strtolower($u['login']) === strtolower($login)) reply(false, 'Šāds lietotājvārds jau eksistē!');
    }
    $users[] = ['login' => $login, 'password' => $password, 'role' => $role];
    store('users', $users);
    reply(true, 'Konts izveidots!');
}

if ($action === 'login') {
    foreach ($users as $u) {
        if ($u['login'] === $login && $u['password'] === $password) {
            reply(true, 'OK', ['user' => ['login' => $u['login'], 'role' => $u['role']]]);
        }
    }
    reply(false, 'Nepareizs lietotājvārds vai parole!');
}

if ($action === 'users_list') {
    need(['admin']);
    $out = array_map(fn($u) => ['login' => $u['login'], 'role' => $u['role']], $users);
    reply(true, 'OK', ['users' => array_values($out)]);
}

if ($action === 'user_update') {         
    need(['admin']);
    $newRole = $input['newRole'] ?? '';
    if (!in_array($newRole, $roles)) reply(false, 'Nederīga loma!');
    foreach ($users as &$u) {
        if ($u['login'] === $login) {
            $u['role'] = $newRole;
            if (($input['newPassword'] ?? '') !== '') $u['password'] = $input['newPassword'];
            store('users', $users);
            reply(true, 'Lietotājs atjaunināts!');
        }
    }
    reply(false, 'Lietotājs nav atrasts!');
}

if ($action === 'user_delete') {
    need(['admin']);
    $users = array_filter($users, fn($u) => $u['login'] !== $login);
    store('users', $users);
    reply(true, 'Lietotājs dzēsts!');
}

if ($action === 'products_list') {
    reply(true, 'OK', ['products' => load('products')]);
}

if ($action === 'product_save') {
    need(['admin', 'worker']);
    $products = load('products');
    $name = trim($input['name'] ?? '');
    $qty  = (int)($input['qty'] ?? 0);
    $loc  = trim($input['location'] ?? '');
    $id   = (int)($input['id'] ?? 0);
    if ($name === '' || $qty < 0) reply(false, 'Pārbaudi nosaukumu un daudzumu!');

    if ($id) {
        foreach ($products as &$p) {
            if ($p['id'] === $id) { $p['name'] = $name; $p['qty'] = $qty; $p['location'] = $loc; }
        }
    } else {
        $products[] = ['id' => nextId($products), 'name' => $name, 'qty' => $qty, 'location' => $loc];
    }
    store('products', $products);
    reply(true, 'Produkts saglabāts!');
}

if ($action === 'product_delete') {
    need(['admin']);
    $id = (int)($input['id'] ?? 0);
    store('products', array_filter(load('products'), fn($p) => $p['id'] !== $id));
    reply(true, 'Produkts dzēsts!');
}

if ($action === 'orders_list') {
    need(['worker']);
    reply(true, 'OK', ['orders' => load('orders'), 'statuses' => $statuses]);
}

if ($action === 'order_save') {
    need(['worker']);
    $orders = load('orders');
    $products = load('products');
    $pid = (int)($input['product_id'] ?? 0);
    $qty = (int)($input['qty'] ?? 0);
    $client = trim($input['client'] ?? '');
    if ($client === '' || $qty < 1) reply(false, 'Aizpildi klientu un daudzumu!');

    foreach ($products as &$p) {
        if ($p['id'] === $pid) {
            if ($p['qty'] < $qty) reply(false, 'Noliktavā nepietiek preču!');
            $p['qty'] -= $qty;
            $orders[] = ['id' => nextId($orders), 'product_id' => $pid, 'product' => $p['name'],
                         'qty' => $qty, 'client' => $client, 'status' => 'Jauns', 'date' => date('Y-m-d H:i')];
            store('products', $products);
            store('orders', $orders);
            reply(true, 'Pasūtījums izveidots!');
        }
    }
    reply(false, 'Produkts nav atrasts!');
}

if ($action === 'order_status') {
    need(['worker']);
    $orders = load('orders');
    $id = (int)($input['id'] ?? 0);
    $status = $input['status'] ?? '';
    if (!in_array($status, $statuses)) reply(false, 'Nederīgs statuss!');
    foreach ($orders as &$o) if ($o['id'] === $id) $o['status'] = $status;
    store('orders', $orders);
    reply(true, 'Statuss nomainīts!');
}

if ($action === 'order_delete') {
    need(['worker']);
    $id = (int)($input['id'] ?? 0);
    store('orders', array_filter(load('orders'), fn($o) => $o['id'] !== $id));
    reply(true, 'Pasūtījums dzēsts!');
}

if ($action === 'place') {
    need(['sorter']);
    $products = load('products');
    $log = load('log');
    $id = (int)($input['id'] ?? 0);
    $loc = trim($input['location'] ?? '');
    if ($loc === '') reply(false, 'Norādi atrašanās vietu!');
    foreach ($products as &$p) {
        if ($p['id'] === $id) {
            $log[] = ['date' => date('Y-m-d H:i'), 'product' => $p['name'], 'from' => $p['location'], 'to' => $loc];
            $p['location'] = $loc;
            store('products', $products);
            store('log', $log);
            reply(true, 'Izvietojums saglabāts!');
        }
    }
    reply(false, 'Produkts nav atrasts!');
}

if ($action === 'report') {
    $products = load('products');
    $orders = load('orders');
    $byStatus = [];
    foreach ($orders as $o) $byStatus[$o['status']] = ($byStatus[$o['status']] ?? 0) + 1;
    reply(true, 'OK', [
        'date'       => date('Y-m-d H:i'),
        'products'   => $products,
        'total_qty'  => array_sum(array_column($products, 'qty')),
        'low_stock'  => array_values(array_filter($products, fn($p) => $p['qty'] < 5)),
        'no_place'   => array_values(array_filter($products, fn($p) => $p['location'] === '')),
        'orders'     => $byStatus,
        'log'        => array_slice(array_reverse(load('log')), 0, 10),
    ]);
}

reply(false, 'Nezināma darbība.');
