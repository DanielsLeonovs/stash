<?php
header('Content-Type: application/json; charset=utf-8');

$file = __DIR__ . '/users.json';
$roles = ['admin', 'worker', 'sorter'];

function reply($success, $message, $extra = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

$users = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
if (!is_array($users)) $users = [];

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
        if (strtolower($u['login']) === strtolower($login)) {
            reply(false, 'Šāds lietotājvārds jau eksistē!');
        }
    }

    $users[] = ['login' => $login, 'password' => $password, 'role' => $role];
    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
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

reply(false, 'Nezināma darbība.');
