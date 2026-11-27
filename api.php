<?php
// Stash noliktavas API
// Visi pieprasijumi nak uz api.php?action=... un atbilde vienmer ir JSON.
// Dati glabajas MySQL datubaze (skat db.php un schema.sql).

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
session_start();

const ROLES = ['admin', 'worker', 'sorter'];
const STATUSES = ['Jauns', 'Komplektē', 'Nosūtīts', 'Piegādāts'];
const LOW_STOCK = 5; // ja preces ir mazak par so skaitli, tad rada bridinajumu


// ---------- palidzes funkcijas ----------

// nosuta atbildi un beidz darbu
function reply($ok, $message, $extra = [], $code = 200) {
    http_response_code($code);
    $data = array_merge(['success' => $ok, 'message' => $message], $extra);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// izpilda SQL ar parametriem
function run($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// ieraksta preces kustibu vesture
function addLog($productId, $productName, $action, $qtyChange, $from, $to) {
    global $me;
    run('INSERT INTO stock_log (product_id, product, action, qty_change, from_loc, to_loc, by_user, created_at)
         VALUES (?,?,?,?,?,?,?,?)',
        [$productId, $productName, $action, $qtyChange, $from, $to, $me, date('Y-m-d H:i:s')]);
}


// ---------- datu parbaudes ----------

function okLogin($s) {
    return is_string($s) && preg_match('/^[A-Za-z0-9_]{3,30}$/', $s);
}

function okPassword($s) {
    return is_string($s) && strlen($s) >= 8 && strlen($s) <= 100;
}

function okText($s, $max) {
    return is_string($s) && mb_strlen($s) >= 1 && mb_strlen($s) <= $max;
}

function okLocation($s) {
    return is_string($s) && preg_match('/^[\p{L}0-9 \-\/]{0,30}$/u', $s);
}

function okCode($s) {
    return is_string($s) && preg_match('/^[A-Za-z0-9_\-]{2,30}$/', $s);
}

function okInt($value, $min, $max) {
    $options = ['options' => ['min_range' => $min, 'max_range' => $max]];
    return filter_var($value, FILTER_VALIDATE_INT, $options) !== false;
}

function okPrice($value) {
    if (filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
        return false;
    }
    return (float)$value >= 0 && (float)$value <= 1000000;
}


// ---------- sakums ----------

$pdo = db();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}
$action = $_GET['action'] ?? '';


// kas ir pieteicies
if ($action === 'me') {
    if (empty($_SESSION['login'])) {
        reply(false, 'Nav pieteicies.', [], 401);
    }
    reply(true, 'OK', [
        'login' => $_SESSION['login'],
        'role' => $_SESSION['role'],
        'csrf' => $_SESSION['csrf'],
    ]);
}

// pieteiksanas
if ($action === 'login') {
    $login = trim($input['login'] ?? '');
    $password = (string)($input['password'] ?? '');

    $user = run('SELECT * FROM users WHERE login = ? LIMIT 1', [$login])->fetch();

    if ($user) {
        // vecie konti varetu but ar paroli bez hash (sak. ar $), tos nomainam uz hash
        $isHash = str_starts_with($user['password'], '$');
        if ($isHash) {
            $passwordOk = password_verify($password, $user['password']);
        } else {
            $passwordOk = hash_equals($user['password'], $password);
        }

        if ($passwordOk) {
            if (!$isHash) {
                run('UPDATE users SET password = ? WHERE id = ?',
                    [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            if ($user['role'] === '') {
                reply(false, 'Konts gaida administratora apstiprinājumu (loma nav piešķirta).');
            }
            session_regenerate_id(true);
            $_SESSION = [
                'login' => $user['login'],
                'role' => $user['role'],
                'csrf' => bin2hex(random_bytes(16)),
            ];
            reply(true, 'OK', ['role' => $user['role']]);
        }
    }

    sleep(1); // lai nevar atminet paroli atri
    reply(false, 'Nepareizs lietotājvārds vai parole!');
}


// ---------- tiesibu parbaude ----------
// kura loma ko drikst darit
$permissions = [
    'logout'          => ROLES,
    'products_list'   => ROLES,
    'report'          => ROLES,
    'suppliers_list'  => ROLES,

    'users_list'      => ['admin'],
    'user_create'     => ['admin'],
    'user_update'     => ['admin'],
    'user_delete'     => ['admin'],

    'product_save'    => ['admin', 'worker'],
    'product_receive' => ['admin', 'worker'],
    'product_delete'  => ['admin'],

    'supplier_save'   => ['admin'],
    'supplier_delete' => ['admin'],

    'orders_list'     => ['worker'],
    'order_save'      => ['worker'],
    'order_status'    => ['worker'],
    'order_delete'    => ['worker'],

    'place'           => ['sorter'],
];

if (!isset($permissions[$action])) {
    reply(false, 'Nezināma darbība.', [], 404);
}
if (empty($_SESSION['login'])) {
    reply(false, 'Nav pieteicies.', [], 401);
}
if (!in_array($_SESSION['role'], $permissions[$action], true)) {
    reply(false, 'Nav piekļuves tiesību!', [], 403);
}
// CSRF tokens jasuta header X-CSRF-Token
if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    reply(false, 'Nederīgs pieprasījums.', [], 403);
}

$me = $_SESSION['login'];


// ---------- iziet ----------

if ($action === 'logout') {
    session_destroy();
    reply(true, 'OK');
}


// ---------- lietotaji (tikai admin) ----------

if ($action === 'users_list') {
    $users = $pdo->query('SELECT login, role FROM users ORDER BY id')->fetchAll();
    reply(true, 'OK', ['users' => $users, 'me' => $me]);
}

if ($action === 'user_create') {
    $login = trim($input['login'] ?? '');
    $password = $input['password'] ?? '';
    $newRole = $input['newRole'] ?? '';

    if (!okLogin($login)) {
        reply(false, 'Lietotājvārds: 3-30 simboli (burti, cipari, _).');
    }
    if (!okPassword($password)) {
        reply(false, 'Parolei jābūt vismaz 8 simbolus garai.');
    }
    if (!in_array($newRole, ROLES, true)) {
        reply(false, 'Izvēlies lomu!');
    }

    $exists = run('SELECT id FROM users WHERE login = ?', [$login])->fetch();
    if ($exists) {
        reply(false, 'Šāds lietotājvārds jau eksistē!');
    }

    run('INSERT INTO users (login, password, role) VALUES (?, ?, ?)',
        [$login, password_hash($password, PASSWORD_DEFAULT), $newRole]);
    reply(true, 'Lietotājs izveidots!');
}

if ($action === 'user_update') {
    $login = $input['login'] ?? '';
    $newRole = $input['newRole'] ?? '';
    $newPassword = $input['newPassword'] ?? '';

    if (!in_array($newRole, ROLES, true)) {
        reply(false, 'Nederīga loma!');
    }
    if ($newPassword !== '' && !okPassword($newPassword)) {
        reply(false, 'Parolei jābūt vismaz 8 simbolus garai.');
    }

    $user = run('SELECT * FROM users WHERE login = ?', [$login])->fetch();
    if (!$user) {
        reply(false, 'Lietotājs nav atrasts!');
    }

    // vismaz vienam adminam japaliek
    $adminCount = (int)run("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch()['c'];
    if ($user['role'] === 'admin' && $newRole !== 'admin' && $adminCount <= 1) {
        reply(false, 'Nevar noņemt pēdējo administratoru!');
    }

    if ($newPassword !== '') {
        run('UPDATE users SET role = ?, password = ? WHERE login = ?',
            [$newRole, password_hash($newPassword, PASSWORD_DEFAULT), $login]);
    } else {
        run('UPDATE users SET role = ? WHERE login = ?', [$newRole, $login]);
    }
    reply(true, 'Lietotājs atjaunināts!');
}

if ($action === 'user_delete') {
    $login = $input['login'] ?? '';
    if ($login === $me) {
        reply(false, 'Savu kontu dzēst nevar!');
    }
    run('DELETE FROM users WHERE login = ?', [$login]);
    reply(true, 'Lietotājs dzēsts!');
}


// ---------- piegadataji ----------

if ($action === 'suppliers_list') {
    $suppliers = $pdo->query('SELECT * FROM suppliers ORDER BY name')->fetchAll();
    reply(true, 'OK', ['suppliers' => $suppliers]);
}

if ($action === 'supplier_save') {
    $name = trim($input['name'] ?? '');
    $contact = trim($input['contact'] ?? '');
    $id = (int)($input['id'] ?? 0);

    if (!okText($name, 100)) {
        reply(false, 'Nosaukums: 1-100 simboli.');
    }
    if ($contact !== '' && !okText($contact, 100)) {
        reply(false, 'Kontakti: līdz 100 simboliem.');
    }

    if ($id) {
        run('UPDATE suppliers SET name = ?, contact = ? WHERE id = ?', [$name, $contact, $id]);
    } else {
        run('INSERT INTO suppliers (name, contact) VALUES (?, ?)', [$name, $contact]);
    }
    reply(true, 'Piegādātājs saglabāts!');
}

if ($action === 'supplier_delete') {
    $id = (int)($input['id'] ?? 0);
    run('DELETE FROM suppliers WHERE id = ?', [$id]);
    reply(true, 'Piegādātājs dzēsts!');
}


// ---------- preces ----------

if ($action === 'products_list') {
    // meklesana pec nosaukuma un kategorijas (nav obligata)
    $q = trim($input['q'] ?? ($_GET['q'] ?? ''));
    $category = trim($input['category'] ?? ($_GET['category'] ?? ''));

    $sql = 'SELECT p.*, s.name AS supplier_name
            FROM products p
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            WHERE 1=1';
    $params = [];
    if ($q !== '') {
        $sql .= ' AND p.name LIKE ?';
        $params[] = '%' . $q . '%';
    }
    if ($category !== '') {
        $sql .= ' AND p.category LIKE ?';
        $params[] = '%' . $category . '%';
    }
    $sql .= ' ORDER BY p.id';

    $products = run($sql, $params)->fetchAll();
    foreach ($products as $i => $p) {
        $products[$i]['qty'] = (int)$p['qty'];
        $products[$i]['price'] = (float)$p['price'];
        $products[$i]['low'] = $products[$i]['qty'] < LOW_STOCK;
    }
    reply(true, 'OK', ['products' => $products]);
}

if ($action === 'product_save') {
    $id = (int)($input['id'] ?? 0);
    $name = trim($input['name'] ?? '');
    $category = trim($input['category'] ?? '');
    $code = trim($input['code'] ?? '');
    $location = trim($input['location'] ?? '');
    $supplierId = (int)($input['supplier_id'] ?? 0);
    if ($supplierId === 0) {
        $supplierId = null; // nav piegadataja
    }

    if (!okText($name, 100)) {
        reply(false, 'Nosaukums: 1-100 simboli.');
    }
    if (!okCode($code)) {
        reply(false, 'Preces kods: 2-30 simboli (burti, cipari, - vai _).');
    }
    if ($category !== '' && !okText($category, 50)) {
        reply(false, 'Kategorija: līdz 50 simboliem.');
    }
    if (!okPrice($input['price'] ?? '')) {
        reply(false, 'Cena: 0-1 000 000.');
    }
    if (!okInt($input['qty'] ?? '', 0, 1000000)) {
        reply(false, 'Daudzums: 0-1 000 000.');
    }
    if (!okLocation($location)) {
        reply(false, 'Atrašanās vieta: līdz 30 simboliem (burti, cipari, - /).');
    }

    $price = round((float)$input['price'], 2);
    $qty = (int)$input['qty'];

    // vai tads kods jau ir citai precei
    $sameCode = run('SELECT id FROM products WHERE code = ? AND id <> ?', [$code, $id])->fetch();
    if ($sameCode) {
        reply(false, 'Šāds preces kods jau eksistē!');
    }

    if ($id) {
        // labo esosu preci
        $found = run('SELECT id FROM products WHERE id = ?', [$id])->fetch();
        if (!$found) {
            reply(false, 'Produkts nav atrasts!');
        }
        run('UPDATE products SET code=?, name=?, category=?, price=?, qty=?, location=?, supplier_id=? WHERE id=?',
            [$code, $name, $category, $price, $qty, $location, $supplierId, $id]);
    } else {
        // jauna prece
        run('INSERT INTO products (code, name, category, price, qty, location, supplier_id) VALUES (?,?,?,?,?,?,?)',
            [$code, $name, $category, $price, $qty, $location, $supplierId]);
    }
    reply(true, 'Produkts saglabāts!');
}

if ($action === 'product_delete') {
    $id = (int)($input['id'] ?? 0);
    run('DELETE FROM products WHERE id = ?', [$id]);
    reply(true, 'Produkts dzēsts!');
}

// jauna piegade - palielina atlikumu
if ($action === 'product_receive') {
    $id = (int)($input['id'] ?? 0);
    if (!okInt($input['qty'] ?? '', 1, 1000000)) {
        reply(false, 'Daudzums: 1-1 000 000.');
    }
    $qty = (int)$input['qty'];

    $product = run('SELECT * FROM products WHERE id = ?', [$id])->fetch();
    if (!$product) {
        reply(false, 'Produkts nav atrasts!');
    }

    run('UPDATE products SET qty = qty + ? WHERE id = ?', [$qty, $id]);
    addLog($id, $product['name'], 'piegāde', $qty, '', $product['location']);
    reply(true, 'Piegāde reģistrēta, atlikums palielināts!');
}


// ---------- pasutijumi (worker) ----------

if ($action === 'orders_list') {
    $orders = $pdo->query('SELECT * FROM orders ORDER BY id DESC')->fetchAll();
    reply(true, 'OK', ['orders' => $orders, 'statuses' => STATUSES]);
}

if ($action === 'order_save') {
    $client = trim($input['client'] ?? '');
    $productId = (int)($input['product_id'] ?? 0);

    if (!okText($client, 100)) {
        reply(false, 'Klients: 1-100 simboli.');
    }
    if (!okInt($input['qty'] ?? '', 1, 10000)) {
        reply(false, 'Daudzums: 1-10 000.');
    }
    $qty = (int)$input['qty'];

    $product = run('SELECT * FROM products WHERE id = ?', [$productId])->fetch();
    if (!$product) {
        reply(false, 'Produkts nav atrasts!');
    }
    if ((int)$product['qty'] < $qty) {
        reply(false, 'Noliktavā nepietiek preču!');
    }

    // atlikuma samazinasana un pasutijuma izveide jadara kopa
    $pdo->beginTransaction();
    run('UPDATE products SET qty = qty - ? WHERE id = ?', [$qty, $productId]);
    run('INSERT INTO orders (product_id, product, qty, client, status, created_at) VALUES (?,?,?,?,?,?)',
        [$productId, $product['name'], $qty, $client, 'Jauns', date('Y-m-d H:i:s')]);
    addLog($productId, $product['name'], 'izsniegšana', -$qty, $product['location'], '');
    $pdo->commit();

    reply(true, 'Pasūtījums izveidots!');
}

if ($action === 'order_status') {
    $id = (int)($input['id'] ?? 0);
    $status = $input['status'] ?? '';
    if (!in_array($status, STATUSES, true)) {
        reply(false, 'Nederīgs statuss!');
    }
    run('UPDATE orders SET status = ? WHERE id = ?', [$status, $id]);
    reply(true, 'Statuss nomainīts!');
}

if ($action === 'order_delete') {
    $id = (int)($input['id'] ?? 0);
    run('DELETE FROM orders WHERE id = ?', [$id]);
    reply(true, 'Pasūtījums dzēsts!');
}


// ---------- preču izvietosana (sorter) ----------

if ($action === 'place') {
    $id = (int)($input['id'] ?? 0);
    $location = trim($input['location'] ?? '');

    if ($location === '' || !okLocation($location)) {
        reply(false, 'Atrašanās vieta: 1-30 simboli (burti, cipari, - /).');
    }

    $product = run('SELECT * FROM products WHERE id = ?', [$id])->fetch();
    if (!$product) {
        reply(false, 'Produkts nav atrasts!');
    }

    run('UPDATE products SET location = ? WHERE id = ?', [$location, $id]);
    addLog($id, $product['name'], 'pārvietošana', 0, $product['location'], $location);
    reply(true, 'Izvietojums saglabāts!');
}


// ---------- parskats ----------

if ($action === 'report') {
    $products = $pdo->query('SELECT * FROM products')->fetchAll();

    $totalQty = 0;
    $totalValue = 0.0;
    $lowStock = [];
    $noPlace = [];
    foreach ($products as $p) {
        $totalQty += (int)$p['qty'];
        $totalValue += (float)$p['qty'] * (float)$p['price'];
        if ((int)$p['qty'] < LOW_STOCK) {
            $lowStock[] = $p;
        }
        if ($p['location'] === '') {
            $noPlace[] = $p;
        }
    }

    // cik pasutijumu katra statusa
    $ordersByStatus = [];
    $rows = $pdo->query('SELECT status, COUNT(*) AS c FROM orders GROUP BY status')->fetchAll();
    foreach ($rows as $row) {
        $ordersByStatus[$row['status']] = (int)$row['c'];
    }

    $log = $pdo->query('SELECT * FROM stock_log ORDER BY id DESC LIMIT 10')->fetchAll();

    reply(true, 'OK', [
        'date' => date('Y-m-d H:i'),
        'products' => $products,
        'total_qty' => $totalQty,
        'total_value' => round($totalValue, 2),
        'low_stock' => $lowStock,
        'no_place' => $noPlace,
        'orders' => $ordersByStatus,
        'log' => $log,
    ]);
}

reply(false, 'Nezināma darbība.', [], 404);
