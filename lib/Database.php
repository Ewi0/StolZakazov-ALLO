<?php
class Database {
  private PDO $pdo;
  public function __construct(array $cfg) {
    $this->pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], $cfg['opts']);
  }
  public function pdo(): PDO { return $this->pdo; }
}