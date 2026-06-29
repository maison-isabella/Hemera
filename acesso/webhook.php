<?php
// ── WEBHOOK.PHP — Recebe pagamentos do Stripe ────────────────
// URL a configurar no Stripe: https://andreiarodrigues.com/webhook.php

require_once 'config.php';

// Lê o payload do Stripe
$payload = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verificação de segurança
if (!function_exists('stripe_verify_webhook')) {
    function stripe_verify_webhook($payload, $sig_header, $secret) {
        $parts = explode(',', $sig_header);
        $timestamp = null;
        $signatures = [];
        foreach ($parts as $part) {
            if (strpos($part, 't=') === 0) $timestamp = substr($part, 2);
            if (strpos($part, 'v1=') === 0) $signatures[] = substr($part, 3);
        }
        if (!$timestamp || empty($signatures)) return false;
        $signed_payload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signed_payload, $secret);
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) return true;
        }
        return false;
    }
}

if (!stripe_verify_webhook($payload, $sig_header, STRIPE_WEBHOOK_SECRET)) {
    http_response_code(400);
    exit('Webhook inválido');
}

$event = json_decode($payload, true);

// Só processamos pagamentos concluídos
if ($event['type'] !== 'checkout.session.completed') {
    http_response_code(200);
    exit('OK');
}

$session = $event['data']['object'];
$email = strtolower(trim($session['customer_details']['email'] ?? ''));
$nome = $session['customer_details']['name'] ?? '';
$stripe_session_id = $session['id'];

// Identificar o produto comprado
$line_items_url = "https://api.stripe.com/v1/checkout/sessions/{$stripe_session_id}/line_items";
$ch = curl_init($line_items_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');
$items_response = json_decode(curl_exec($ch), true);
curl_close($ch);

$produto = null;
if (!empty($items_response['data'])) {
    $price_id = $items_response['data'][0]['price']['id'] ?? '';
    $produtos_map = [
        PRODUTO_REVELATION => 'revelation',
        PRODUTO_ACADEMIA   => 'academia',
        PRODUTO_COFFRET    => 'coffret',
        PRODUTO_COLLECTION => 'collection',
    ];
    $produto = $produtos_map[$price_id] ?? null;
}

if (!$email || !$produto) {
    http_response_code(200);
    exit('Sem email ou produto');
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Verificar se cliente já existe
    $stmt = $pdo->prepare("SELECT id, password_hash FROM clientes WHERE email = ?");
    $stmt->execute([$email]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    $password_plain = null;

    if (!$cliente) {
        // Nova cliente — gerar password
        $password_plain = gerar_password();
        $password_hash = password_hash($password_plain, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO clientes (email, password_hash, nome) VALUES (?, ?, ?)");
        $stmt->execute([$email, $password_hash, $nome]);
        $cliente_id = $pdo->lastInsertId();
    } else {
        $cliente_id = $cliente['id'];
    }

    // Adicionar acesso ao produto
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO acessos (cliente_id, produto, stripe_session_id)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$cliente_id, $produto, $stripe_session_id]);

    // Enviar email
    if ($password_plain) {
        enviar_email_boas_vindas($email, $nome, $password_plain, $produto);
    } else {
        enviar_email_novo_produto($email, $nome, $produto);
    }

} catch (PDOException $e) {
    error_log('Webhook DB error: ' . $e->getMessage());
    http_response_code(500);
    exit('Erro interno');
}

http_response_code(200);
echo 'OK';

// ── FUNÇÕES ──────────────────────────────────────────────────

function gerar_password() {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $password = '';
    for ($i = 0; $i < 10; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

function nome_produto($produto) {
    $nomes = [
        'revelation' => 'Révélation™',
        'academia'   => "L'Académie de l'Élévation™",
        'coffret'    => 'Coffret™',
        'collection' => 'La Collection™',
    ];
    return $nomes[$produto] ?? $produto;
}

function enviar_email_boas_vindas($email, $nome, $password, $produto) {
    $primeiro_nome = explode(' ', $nome)[0] ?: 'Bem-vinda';
    $nome_prod = nome_produto($produto);
    $link = SITE_URL . '/acesso/';

    $assunto = "Bem-vinda à Maison Isabella — O teu acesso está aqui";

    $corpo = "
Bem-vinda, {$primeiro_nome}.

A tua decisão foi tomada. O teu acesso à Maison Isabella está pronto.

─────────────────────────
{$nome_prod}
─────────────────────────

Acede aqui: {$link}

Email: {$email}
Password: {$password}

Guarda esta informação em local seguro.

─────────────────────────

Com elegância,
Andreia · Directrice Créative
Maison Isabella
";

    $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    mail($email, $assunto, $corpo, $headers);
}

function enviar_email_novo_produto($email, $nome, $produto) {
    $primeiro_nome = explode(' ', $nome)[0] ?: 'Bem-vinda';
    $nome_prod = nome_produto($produto);
    $link = SITE_URL . '/acesso/';

    $assunto = "Novo acesso disponível — {$nome_prod}";

    $corpo = "
{$primeiro_nome},

O teu novo produto está disponível na plataforma.

─────────────────────────
{$nome_prod}
─────────────────────────

Acede com as tuas credenciais habituais:
{$link}

─────────────────────────

Com elegância,
Andreia · Directrice Créative
Maison Isabella
";

    $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    mail($email, $assunto, $corpo, $headers);
}
