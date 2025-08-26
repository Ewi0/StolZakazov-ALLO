<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__.'/config.php';
require __DIR__.'/lib/Database.php';
require __DIR__.'/lib/OrderRepository.php';

try {
  $cfg = require __DIR__.'/config.php';
  $db  = (new Database($cfg['db']))->pdo();
  $rep = new OrderRepository($db);

  $method = $_SERVER['REQUEST_METHOD'];
  $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

  if ($method === 'GET' && $action === 'list') {
    $data = $rep->list([
      'q'        => $_GET['q'] ?? '',
      'status'   => $_GET['status'] ?? '',
      'supplier' => $_GET['supplier'] ?? '',
      'sort'     => $_GET['sort'] ?? 'created_at',
      'dir'      => $_GET['dir'] ?? 'desc',
      'limit'    => $_GET['limit'] ?? 20,
      'offset'   => $_GET['offset'] ?? 0,
    ]);
    echo json_encode(['ok'=>true, 'items'=>$data], JSON_UNESCAPED_UNICODE); exit;
  }

  if ($method === 'GET' && $action === 'phones') {
    $q = trim($_GET['q'] ?? '');
    $limit = (int)($_GET['limit'] ?? 5);
    $items = $q==='' ? [] : $rep->phones($q, max(1,min(20,$limit)));
    echo json_encode(['ok'=>true, 'items'=>$items], JSON_UNESCAPED_UNICODE); exit;
  }

  if ($method === 'POST' && $action === 'create') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $phone = trim($payload['phone'] ?? '');
    if ($phone==='') { echo json_encode(['ok'=>false,'error'=>'phone_required']); exit; }

    // Мягкое предупреждение: дубликаты допускаем
    $duplicate = $rep->phoneExists($phone);

    $id = $rep->create($payload);
    echo json_encode(['ok'=>true,'id'=>$id,'duplicate'=>$duplicate], JSON_UNESCAPED_UNICODE); exit;
  }

  if ($method === 'POST' && $action === 'update') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = (int)($payload['id'] ?? 0);
    $phone = trim($payload['phone'] ?? '');
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'id_required']); exit; }
    if ($phone==='') { echo json_encode(['ok'=>false,'error'=>'phone_required']); exit; }

    // Дубликаты разрешены — просто сохраняем, но вернём флаг duplicate
    $duplicate = $rep->phoneExists($phone, $id);

    $ok = $rep->update($id, $payload);
    echo json_encode(['ok'=>$ok,'duplicate'=>$duplicate], JSON_UNESCAPED_UNICODE); exit;
  }

  if ($method === 'POST' && $action === 'delete') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = (int)($payload['id'] ?? 0);
    $ok = $id ? $rep->delete($id) : false;
    echo json_encode(['ok'=>$ok], JSON_UNESCAPED_UNICODE); exit;
  }

  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'bad_action']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error'], JSON_UNESCAPED_UNICODE);
}