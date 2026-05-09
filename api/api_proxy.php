<?php
/**
 * RCP Claims Center — Secure AI API Proxy
 * Suporte: Claude (Anthropic), OpenAI GPT-4o, DeepSeek
 * Chaves carregadas de _config/ai_keys.php ou variáveis de ambiente
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin');

$host   = $_SERVER['HTTP_HOST'] ?? '';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$self   = "$scheme://$host";

if ($origin && rtrim($origin, '/') === rtrim($self, '/')) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: $self");
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ─── Rate limiting (arquivo por IP, 20 req/hora) ──────────────────────────────
$ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateFile = sys_get_temp_dir() . '/rcp_rl_' . md5($ip);
$limit    = 20;
$window   = 3600;

$hits = 0;
if (file_exists($rateFile)) {
    $d = json_decode(file_get_contents($rateFile), true);
    if (is_array($d) && (time() - ($d['ts'] ?? 0)) < $window) {
        $hits = (int)($d['hits'] ?? 0);
    }
}
if ($hits >= $limit) {
    http_response_code(429);
    echo json_encode(['error' => 'Limite de requisições atingido. Tente novamente mais tarde.']);
    exit;
}
file_put_contents($rateFile, json_encode(['ts' => time(), 'hits' => $hits + 1]), LOCK_EX);

// ─── Carregar chaves de API ───────────────────────────────────────────────────
// Prioridade: variáveis de ambiente (Vercel) > _config/ai_keys.php
$keysFile = __DIR__ . '/../_config/ai_keys.php';
if (file_exists($keysFile)) {
    require $keysFile;
} else {
    $AI_KEYS = [];
}
if (getenv('ANTHROPIC_KEY')) $AI_KEYS['anthropic'] = getenv('ANTHROPIC_KEY');
if (getenv('OPENAI_KEY'))    $AI_KEYS['openai']    = getenv('OPENAI_KEY');
if (getenv('DEEPSEEK_KEY'))  $AI_KEYS['deepseek']  = getenv('DEEPSEEK_KEY');

// ─── Validar e sanitizar entrada ──────────────────────────────────────────────
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input) || empty($input['prompt'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Campo obrigatório ausente: prompt']);
    exit;
}

$model   = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)($input['model'] ?? 'claude')));
$prompt  = mb_substr(strip_tags((string)$input['prompt']), 0, 4000);
$context = is_array($input['context'] ?? null) ? $input['context'] : [];

$ctxStr = '';
if (!empty($context)) {
    $ctxStr = "\n\nCONTEXTO DO SINISTRO:\n";
    foreach ($context as $k => $v) {
        $ctxStr .= '- ' . htmlspecialchars("$k: $v", ENT_QUOTES, 'UTF-8') . "\n";
    }
}

$systemPrompt =
    'Você é um assistente especializado em sinistros de Responsabilidade Civil Profissional (RCP) '
    . 'para médicos no Brasil. Responda sempre em português do Brasil, de forma profissional e objetiva. '
    . 'Foque em: regulação de sinistros, documentação exigida, prazos, estratégias de defesa '
    . 'e comunicação com reguladoras.' . $ctxStr;

// ─── Roteamento por provedor ──────────────────────────────────────────────────
try {
    if (str_starts_with($model, 'claude') || $model === 'claude') {
        $response = callClaude($systemPrompt, $prompt, $AI_KEYS['anthropic'] ?? '');
    } elseif (str_starts_with($model, 'gpt') || str_starts_with($model, 'openai')) {
        $response = callOpenAI($systemPrompt, $prompt, $AI_KEYS['openai'] ?? '');
    } elseif (str_starts_with($model, 'deep')) {
        $response = callDeepSeek($systemPrompt, $prompt, $AI_KEYS['deepseek'] ?? '');
    } else {
        $response = callClaude($systemPrompt, $prompt, $AI_KEYS['anthropic'] ?? '');
    }
    echo json_encode(['response' => $response, 'model' => $model]);
} catch (Exception $e) {
    http_response_code(502);
    echo json_encode(['error' => 'Erro no provedor IA: ' . $e->getMessage()]);
}

function callClaude(string $system, string $prompt, string $key): string
{
    if (!$key) throw new Exception('Chave Anthropic não configurada');
    $body = json_encode(['model'=>'claude-3-5-sonnet-20241022','max_tokens'=>1024,'system'=>$system,'messages'=>[['role'=>'user','content'=>$prompt]]]);
    $res  = httpPost('https://api.anthropic.com/v1/messages', $body, ['x-api-key: '.$key,'anthropic-version: 2023-06-01','content-type: application/json']);
    $data = json_decode($res, true);
    $text = $data['content'][0]['text'] ?? null;
    if ($text === null) throw new Exception('Resposta inesperada do Claude');
    return $text;
}

function callOpenAI(string $system, string $prompt, string $key): string
{
    if (!$key) throw new Exception('Chave OpenAI não configurada');
    $body = json_encode(['model'=>'gpt-4o','max_tokens'=>1024,'messages'=>[['role'=>'system','content'=>$system],['role'=>'user','content'=>$prompt]]]);
    $res  = httpPost('https://api.openai.com/v1/chat/completions', $body, ['Authorization: Bearer '.$key,'Content-Type: application/json']);
    $data = json_decode($res, true);
    $text = $data['choices'][0]['message']['content'] ?? null;
    if ($text === null) throw new Exception('Resposta inesperada do OpenAI');
    return $text;
}

function callDeepSeek(string $system, string $prompt, string $key): string
{
    if (!$key) throw new Exception('Chave DeepSeek não configurada');
    $body = json_encode(['model'=>'deepseek-chat','max_tokens'=>1024,'messages'=>[['role'=>'system','content'=>$system],['role'=>'user','content'=>$prompt]]]);
    $res  = httpPost('https://api.deepseek.com/v1/chat/completions', $body, ['Authorization: Bearer '.$key,'Content-Type: application/json']);
    $data = json_decode($res, true);
    $text = $data['choices'][0]['message']['content'] ?? null;
    if ($text === null) throw new Exception('Resposta inesperada do DeepSeek');
    return $text;
}

function httpPost(string $url, string $body, array $headers): string
{
    if (!function_exists('curl_init')) throw new Exception('cURL não disponível');
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>true]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err)         throw new Exception('cURL: '.$err);
    if ($code >= 400) throw new Exception("HTTP $code do provedor");
    return (string)$res;
}
