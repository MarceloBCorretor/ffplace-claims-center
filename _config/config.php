<?php
/**
 * RCP Claims Center — Configuração do Aplicativo
 * Protegido por _config/.htaccess (Deny from all)
 * NUNCA versione senhas ou tokens reais.
 */

// ─── Banco de Dados (MySQL / MariaDB) ────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'rcp_claims');  // rode _config/schema.sql primeiro
define('DB_USER',    'rcp_user');    // usuário MySQL com acesso ao banco
define('DB_PASS',    '');            // senha do usuário MySQL
define('DB_CHARSET', 'utf8mb4');

// ─── Aplicação ───────────────────────────────────────────────────────────
define('APP_TIMEZONE', 'America/Sao_Paulo');

// Token de autenticação para as APIs (header X-App-Token)
// Deixe vazio para desativar a verificação (apenas rede local/dev)
// Gere um token com: php -r "echo bin2hex(random_bytes(24));"
define('APP_TOKEN', '');

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
