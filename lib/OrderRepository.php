<?php
class OrderRepository {
  public function __construct(private PDO $db) {}

  public function list(array $filters): array {
    $sql  = "SELECT * FROM `orders` WHERE 1";
    $args = [];

    if (!empty($filters['q'])) {
      $sql .= " AND ("
            . " `phone`   LIKE :q1"
            . " OR `comment`  LIKE :q2"
            . " OR `supplier` LIKE :q3"
            . " OR `parts`    LIKE :q4"
            . ")";
      $like = '%'.$filters['q'].'%';
      $args[':q1'] = $like;
      $args[':q2'] = $like;
      $args[':q3'] = $like;
      $args[':q4'] = $like;
    }

    if (!empty($filters['status']))   { $sql .= " AND `status` = :st";   $args[':st'] = $filters['status']; }
    if (!empty($filters['supplier'])) { $sql .= " AND `supplier` = :sp"; $args[':sp'] = $filters['supplier']; }

    // Безопасная сортировка
    $sort = $filters['sort'] ?? 'created_at';
    $dir  = strtolower($filters['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $sortMap = [
      'created_at' => '`created_at`',
      'deadline'   => '`deadline`',
      'id'         => '`id`',
    ];
    $orderBy = $sortMap[$sort] ?? $sortMap['created_at'];
    $sql .= " ORDER BY $orderBy $dir";

    // LIMIT/OFFSET подставляем как числа, без плейсхолдеров
    $limit  = max(1, min(200, (int)($filters['limit'] ?? 20)));
    $offset = max(0, (int)($filters['offset'] ?? 0));
    $sql   .= " LIMIT $offset, $limit";

    $stmt = $this->db->prepare($sql);
    foreach ($args as $k=>$v) $stmt->bindValue($k,$v);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
      $r['parts']   = $r['parts'] ? (json_decode($r['parts'], true) ?: []) : [];
      $dt           = new DateTime($r['created_at']);
      $r['date']    = $dt->format('d.m.Y H:i');
      $r['dateRaw'] = $dt->format(DateTime::ATOM);
    }
    return $rows;
  }

  public function create(array $d): int {
    $stmt = $this->db->prepare("
      INSERT INTO `orders` (`phone`,`comment`,`status`,`supplier`,`deadline`,`parts`,`created_at`)
      VALUES (:phone,:comment,:status,:supplier,:deadline,:parts,NOW())
    ");
    $stmt->execute([
      ':phone'    => $d['phone'],
      ':comment'  => $d['comment'] ?? null,
      ':status'   => $d['status'] ?? 'new',
      ':supplier' => $d['supplier'] ?? null,
      ':deadline' => $d['deadline'] ?: null,
      ':parts'    => !empty($d['parts']) ? json_encode(array_values($d['parts']), JSON_UNESCAPED_UNICODE) : null,
    ]);
    return (int)$this->db->lastInsertId();
  }

  public function update(int $id, array $d): bool {
    $stmt = $this->db->prepare("
      UPDATE `orders`
      SET `phone`=:phone, `comment`=:comment, `status`=:status, `supplier`=:supplier,
          `deadline`=:deadline, `parts`=:parts
      WHERE `id`=:id
    ");
    return $stmt->execute([
      ':phone'    => $d['phone'],
      ':comment'  => $d['comment'] ?? null,
      ':status'   => $d['status'] ?? 'new',
      ':supplier' => $d['supplier'] ?? null,
      ':deadline' => $d['deadline'] ?: null,
      ':parts'    => !empty($d['parts']) ? json_encode(array_values($d['parts']), JSON_UNESCAPED_UNICODE) : null,
      ':id'       => $id,
    ]);
  }

  public function delete(int $id): bool {
    return $this->db->prepare("DELETE FROM `orders` WHERE `id`=:id")->execute([':id'=>$id]);
  }

  public function phoneExists(string $phone, ?int $excludeId=null): bool {
    $sql  = "SELECT 1 FROM `orders` WHERE `phone`=:p";
    $args = [':p'=>$phone];
    if ($excludeId) { $sql .= " AND `id`<>:id"; $args[':id']=$excludeId; }
    $stmt = $this->db->prepare($sql); $stmt->execute($args);
    return (bool)$stmt->fetchColumn();
  }

  public function phones(string $q, int $limit=5): array {
    $limit = max(1, min(20, (int)$limit));
    $stmt = $this->db->prepare("
      SELECT DISTINCT `phone`
      FROM `orders`
      WHERE `phone` LIKE :q
      ORDER BY `phone`
      LIMIT $limit
    ");
    $stmt->bindValue(':q', '%'.$q.'%');
    $stmt->execute();
    return array_column($stmt->fetchAll(), 'phone');
  }
}