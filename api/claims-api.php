<?php
/**
 * RCP Claims Center — Claims REST API
 *
 * GET    (sem parâmetros)  → lista todos os sinistros
 * GET    ?id=X             → sinistro + checklist + timeline
 * GET    ?status=X         → filtrar por status
 * POST   (body JSON)       → criar sinistro
 * PUT    ?id=X (body JSON) → atualizar campos do sinistro
 */

require __DIR__ . '/../_config/config.php';

// ─── Headers de segurança ──────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$host   = $_SERVER['HTTP_HOST'] ?? '';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if ($origin && parse_url($origin, PHP_URL_HOST) === $host) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-App-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ─── Autenticação por token (opcional) ─────────────────────────────────────────
if (APP_TOKEN && ($_SERVER['HTTP_X_APP_TOKEN'] ?? '') !== APP_TOKEN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$id     = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

try {
    $db = getDB();

    switch ($method) {

        // ─── GET ─────────────────────────────────────────────────────────────
        case 'GET':
            if ($id) {
                // Sinistro único + checklist summary + timeline
                $stmt = $db->prepare('SELECT * FROM claims WHERE id = ?');
                $stmt->execute([$id]);
                $claim = $stmt->fetch();
                if (!$claim) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Sinistro não encontrado']);
                    exit;
                }

                // Resumo por fase
                $stmt = $db->prepare('
                    SELECT
                        phase,
                        COUNT(*) AS total,
                        SUM(status = "concluido") AS done,
                        SUM(status = "na")        AS na,
                        SUM(conditional = 1)      AS conditional
                    FROM checklist_items
                    WHERE claim_id = ?
                    GROUP BY phase
                    ORDER BY phase
                ');
                $stmt->execute([$id]);
                $claim['checklist_summary'] = $stmt->fetchAll();

                // Progress geral
                $total = 0; $done = 0;
                foreach ($claim['checklist_summary'] as $row) {
                    $total += $row['total'];
                    $done  += $row['done'];
                }
                $claim['progress_pct'] = $total > 0
                    ? round(($done / $total) * 100, 1)
                    : 0;

                // Timeline
                $stmt = $db->prepare(
                    'SELECT * FROM claim_timeline WHERE claim_id = ? ORDER BY event_date'
                );
                $stmt->execute([$id]);
                $claim['timeline'] = $stmt->fetchAll();

                echo json_encode($claim);

            } else {
                // Lista
                $allowedStatus = ['aberto','em_regulacao','em_julgamento','encerrado'];
                $status = $_GET['status'] ?? null;

                if ($status && in_array($status, $allowedStatus)) {
                    $stmt = $db->prepare(
                        'SELECT * FROM claims WHERE status = ? ORDER BY updated_at DESC'
                    );
                    $stmt->execute([$status]);
                } else {
                    $stmt = $db->query('SELECT * FROM claims ORDER BY updated_at DESC');
                }
                echo json_encode($stmt->fetchAll());
            }
            break;

        // ─── POST (criar) ───────────────────────────────────────────────────
        case 'POST':
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) {
                http_response_code(400);
                echo json_encode(['error' => 'JSON inválido ou ausente']);
                exit;
            }

            $requiredFields = ['claim_number', 'segurado'];
            foreach ($requiredFields as $f) {
                if (empty($body[$f])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Campo obrigatório ausente: $f"]);
                    exit;
                }
            }

            $stmt = $db->prepare('
                INSERT INTO claims (
                    claim_number, segurado, especialidade, cidade_uf, apolice, seguradora,
                    cobertura, ocorrencia_data, ocorrencia_procedimento, processo_numero,
                    vara, reclamante, advogado_defesa, audiencia_data,
                    estimativa_prejuizo, reguladora, reguladora_contato, status, prioridade
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ');
            $stmt->execute([
                san($body['claim_number']),
                san($body['segurado']),
                san($body['especialidade']           ?? ''),
                san($body['cidade_uf']               ?? ''),
                san($body['apolice']                 ?? ''),
                san($body['seguradora']              ?? ''),
                (float)($body['cobertura']           ?? 0),
                validDate($body['ocorrencia_data']   ?? null),
                san($body['ocorrencia_procedimento'] ?? ''),
                san($body['processo_numero']         ?? ''),
                san($body['vara']                    ?? ''),
                san($body['reclamante']              ?? ''),
                san($body['advogado_defesa']         ?? ''),
                validDate($body['audiencia_data']    ?? null),
                (float)($body['estimativa_prejuizo'] ?? 0),
                san($body['reguladora']              ?? ''),
                san($body['reguladora_contato']      ?? ''),
                validEnum($body['status']    ?? '', ['aberto','em_regulacao','em_julgamento','encerrado'], 'em_regulacao'),
                validEnum($body['prioridade'] ?? '', ['alta','media','baixa'], 'media'),
            ]);

            http_response_code(201);
            echo json_encode(['id' => (int)$db->lastInsertId(), 'message' => 'Sinistro criado']);
            break;

        // ─── PUT (atualizar) ──────────────────────────────────────────────────
        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Parâmetro id obrigatório']);
                exit;
            }
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) {
                http_response_code(400);
                echo json_encode(['error' => 'JSON inválido']);
                exit;
            }

            $allowed = [
                'status', 'prioridade', 'audiencia_data', 'estimativa_prejuizo',
                'processo_numero', 'vara', 'advogado_defesa', 'reguladora_contato',
                'ocorrencia_procedimento',
            ];
            $sets = []; $values = [];
            foreach ($allowed as $f) {
                if (!array_key_exists($f, $body)) continue;
                $sets[]   = "$f = ?";
                $values[] = match ($f) {
                    'status'     => validEnum($body[$f], ['aberto','em_regulacao','em_julgamento','encerrado'], 'em_regulacao'),
                    'prioridade' => validEnum($body[$f], ['alta','media','baixa'], 'media'),
                    'audiencia_data', 'ocorrencia_data' => validDate($body[$f]),
                    'estimativa_prejuizo', 'cobertura'  => (float)$body[$f],
                    default => san((string)$body[$f]),
                };
            }

            if (!$sets) {
                http_response_code(400);
                echo json_encode(['error' => 'Nenhum campo válido para atualizar']);
                exit;
            }

            $values[] = $id;
            $db->prepare('UPDATE claims SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
            echo json_encode(['message' => 'Sinistro atualizado']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro no banco de dados: ' . $e->getMessage()]);
}

// ─── Helpers ────────────────────────────────────────────────────────────────────

function san(string $v): string {
    return mb_substr(strip_tags(trim($v)), 0, 500);
}

function validDate(?string $v): ?string {
    if (!$v) return null;
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
}

function validEnum(string $v, array $allowed, string $default): string {
    return in_array($v, $allowed, true) ? $v : $default;
}
