<?php
// ── MAISON ISABELLA — CONFIGURAÇÃO ──────────────────────────
// Preenche estes valores após instalar no Hostinger

// Base de dados MySQL (encontras no hPanel → Bases de dados)
define('DB_HOST', 'localhost');
define('DB_NAME', 'u_maisonisabella'); // nome da tua base de dados
define('DB_USER', 'u_maisonisabella'); // utilizador da base de dados
define('DB_PASS', 'SUBSTITUI_PELA_TUA_PASSWORD');

// Stripe (encontras em dashboard.stripe.com → Developers → API keys)
define('STRIPE_SECRET_KEY', 'sk_live_SUBSTITUI_PELA_TUA_CHAVE_SECRETA');
define('STRIPE_WEBHOOK_SECRET', 'whsec_SUBSTITUI_PELO_SEU_SEGREDO_WEBHOOK');

// Email (configura no hPanel → Email)
define('MAIL_FROM', 'andreia@andreiarodrigues.com');
define('MAIL_FROM_NAME', 'Maison Isabella');
define('SITE_URL', 'https://andreiarodrigues.com');

// IDs dos produtos no Stripe (encontras após criar os produtos)
define('PRODUTO_REVELATION', 'price_REVELATION_ID');
define('PRODUTO_ACADEMIA', 'price_ACADEMIA_ID');
define('PRODUTO_COFFRET', 'price_COFFRET_ID');
define('PRODUTO_COLLECTION', 'price_COLLECTION_ID');

// Sessão
define('SESSION_NAME', 'maison_session');
define('SESSION_LIFETIME', 86400 * 30); // 30 dias
