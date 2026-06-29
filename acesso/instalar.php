<?php
// ── INSTALAR.PHP — Corre uma vez para criar a base de dados ──
// Apaga este ficheiro após correr!

require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Tabela de clientes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clientes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            nome VARCHAR(255),
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ultimo_acesso TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Tabela de produtos por cliente
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acessos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cliente_id INT NOT NULL,
            produto VARCHAR(100) NOT NULL,
            stripe_session_id VARCHAR(255),
            data_compra TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cliente_id) REFERENCES clientes(id),
            UNIQUE KEY unique_acesso (cliente_id, produto)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    echo '<p style="color:green;font-family:sans-serif">✓ Base de dados instalada com sucesso! Apaga este ficheiro agora.</p>';

} catch (PDOException $e) {
    echo '<p style="color:red;font-family:sans-serif">Erro: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
