<?php
/**
 * RCP Claims Center — Configuração do Aplicativo
 * Protegido por _config/.htaccess (Deny from all)
 * NUNCA versione senhas ou tokens reais.
 */

// ─── Banco de Dados ──────────────────────────────────────────────────────────
// Suporta variáveis de ambiente (Vercel) ou valores diretos (hospedagem)
define('DB_HOST',    getenv('DB_HOST') ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME') ?: 'rcp_claims');
define('DB_USER',    getenv('DB_USER') ?: 'rcp_user');
define('DB_PASS',    getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ─── Aplicação ────────────────────────────────────────────────────────────────────
define('APP_TIMEZONE', 'America/Sao_Paulo');

// Token de autenticação (Vercel env var ou valor direto)
// Gere um token com: php -r "echo bin2hex(random_bytes(24));"
define('APP_TOKEN',      getenv('APP_TOKEN')      ?: '');
define('ANTHROPIC_KEY',  getenv('ANTHROPIC_KEY')  ?: '');
define('OPENAI_KEY',     getenv('OPENAI_KEY')      ?: '');
define('DEEPSEEK_KEY',   getenv('DEEPSEEK_KEY')   ?: '');

date_default_timezone_set(APP_TIMEZONE);

// ─── Fábrica PDO (singleton) ──────────────────────────────────────────────────
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHARSET
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
