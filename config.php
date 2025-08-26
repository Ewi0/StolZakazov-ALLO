<?php
return [
  'db' => [
    // MAMP: MySQL по умолчанию на 8889, root без пароля
    'dsn'  => 'mysql:host=127.0.0.1;port=8889;dbname=orders;charset=utf8mb4',
    'user' => 'root',
    'pass' => '',
    'opts' => [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ],
  ],
];