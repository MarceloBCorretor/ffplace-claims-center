<?php
/**
 * RCP Claims Center — Documents / Checklist API
 *
 * GET  ?claim_id=X          → todos os docs do sinistro + totais
 * GET  ?claim_id=X&phase=N  → filtrar por fase (1–4)
 * PUT  ?id=X (body JSON)    → atualizar status / conditional_answer / notes
 * POST (body JSON)          → inicializar checklist padrão para novo sinistro
 */

require __DIR__ . '/../_config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$host   = $_SERVER['HTTP_HOST'] ?? '';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && parse_url($origin, PHP_URL_HOST) === $host) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-App-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if (APP_TOKEN && ($_SERVER['HTTP_X_APP_TOKEN'] ?? '') !== APP_TOKEN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method  = $_SERVER['REQUEST_METHOD'];
$id      = filter_input(INPUT_GET, 'id',       FILTER_VALIDATE_INT);
$claimId = filter_input(INPUT_GET, 'claim_id', FILTER_VALIDATE_INT);
$phase   = filter_input(INPUT_GET, 'phase',    FILTER_VALIDATE_INT);

try {
    $db = getDB();

    switch ($method) {

        // ─── GET ─────────────────────────────────────────────────────────────
        case 'GET':
            if (!$claimId) {
                http_response_code(400);
                echo json_encode(['error' => 'Parâmetro claim_id obrigatório']);
                exit;
            }

            if ($phase && $phase >= 1 && $phase <= 4) {
                $stmt = $db->prepare(
                    'SELECT * FROM checklist_items WHERE claim_id = ? AND phase = ? ORDER BY item_code'
                );
                $stmt->execute([$claimId, $phase]);
            } else {
                $stmt = $db->prepare(
                    'SELECT * FROM checklist_items WHERE claim_id = ? ORDER BY phase, item_code'
                );
                $stmt->execute([$claimId]);
            }
            $items = $stmt->fetchAll();

            // Totais agregados
            $t = ['total' => 0, 'done' => 0, 'pending' => 0, 'na' => 0, 'conditional' => 0];
            foreach ($items as $item) {
                $t['total']++;
                if ($item['status'] === 'concluido')   $t['done']++;
                elseif ($item['status'] === 'na')       $t['na']++;
                elseif ($item['status'] === 'pendente') $t['pending']++;
                if ($item['conditional'])               $t['conditional']++;
            }
            $t['progress_pct'] = $t['total'] > 0
                ? round(($t['done'] / $t['total']) * 100, 1)
                : 0;

            echo json_encode(['items' => $items, 'totals' => $t]);
            break;

        // ─── PUT (atualizar item) ──────────────────────────────────────────────
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

            $sets = []; $values = [];

            if (isset($body['status'])) {
                $allowed = ['pendente', 'concluido', 'na', 'condicional'];
                if (!in_array($body['status'], $allowed, true)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Status inválido']);
                    exit;
                }
                $sets[]   = 'status = ?';
                $values[] = $body['status'];
            }

            if (isset($body['conditional_answer'])) {
                if (!in_array($body['conditional_answer'], ['sim', 'nao'], true)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'conditional_answer deve ser "sim" ou "nao"']);
                    exit;
                }
                $sets[]   = 'conditional_answer = ?';
                $values[] = $body['conditional_answer'];
            }

            if (isset($body['notes'])) {
                $sets[]   = 'notes = ?';
                $values[] = mb_substr(strip_tags((string)$body['notes']), 0, 2000);
            }

            if (!$sets) {
                http_response_code(400);
                echo json_encode(['error' => 'Nenhum campo válido para atualizar']);
                exit;
            }

            $values[] = $id;
            $db->prepare('UPDATE checklist_items SET ' . implode(', ', $sets) . ' WHERE id = ?')
               ->execute($values);
            echo json_encode(['message' => 'Item atualizado']);
            break;

        // ─── POST (inicializar checklist) ─────────────────────────────────────────
        case 'POST':
            $body    = json_decode(file_get_contents('php://input'), true);
            $claimId = (int)($body['claim_id'] ?? 0);
            if (!$claimId) {
                http_response_code(400);
                echo json_encode(['error' => 'claim_id obrigatório']);
                exit;
            }

            // Verificar se o sinistro existe
            $stmt = $db->prepare('SELECT id FROM claims WHERE id = ?');
            $stmt->execute([$claimId]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Sinistro não encontrado']);
                exit;
            }

            // Remover checklist anterior
            $db->prepare('DELETE FROM checklist_items WHERE claim_id = ?')->execute([$claimId]);

            // Inserir checklist padrão RCP
            $stmt = $db->prepare(
                'INSERT INTO checklist_items (claim_id, phase, item_code, description, conditional) VALUES (?,?,?,?,?)'
            );
            $checklist = defaultChecklist();
            foreach ($checklist as $item) {
                $stmt->execute([$claimId, $item[0], $item[1], $item[2], $item[3]]);
            }

            http_response_code(201);
            echo json_encode([
                'message' => 'Checklist inicializado',
                'items'   => count($checklist),
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro no banco de dados: ' . $e->getMessage()]);
}

// ─── Checklist padrão RCP (23 itens) ───────────────────────────────────────────────
function defaultChecklist(): array
{
    // [fase, código, descrição, condicional (0|1)]
    return [
        [1, '1.1',  'Formulário de Aviso de Sinistro Fairfax',                        0],
        [1, '1.2',  'Declaração de não existência de outros seguros (Addvalora)',      0],
        [1, '1.3',  'Cópia da notificação extrajudicial ou citação judicial',          0],
        [1, '1.4',  'Instrumento de procuração para o corretor',                       0],
        [2, '2.1',  'Qualificação completa do segurado',                                0],
        [2, '2.2',  'Apólice de seguro vigente',                                        0],
        [2, '2.3',  'Termo de consentimento informado assinado pelo paciente',          1],
        [2, '2.4',  'Laudos, exames e receitários relacionados ao caso',                0],
        [2, '2.5',  'Notificação ao CRM ou conselho de saúde',                         1],
        [2, '2.6',  'Laudo do perito nomeado pelo juízo',                               1],
        [2, '2.7',  'Cópia integral da petição inicial da reclamante',                   0],
        [2, '2.8',  'Apólice de seguro RC do hospital (se aplicável)',                  1],
        [2, '2.9',  'Resultado de exames pré-operatórios',                               0],
        [2, '2.10', 'Prontuário médico completo do paciente',                            0],
        [2, '2.11', 'Documentos complementares da reguladora',                          0],
        [3, '3.1',  'Contestação apresentada pelo advogado de defesa',                   0],
        [3, '3.2',  'Despachos e decisões interlocutórias relevantes',                   0],
        [3, '3.3',  'Resultado de perícia judicial',                                     0],
        [3, '3.4',  'Proposta de acordo ou contraproposta',                              0],
        [3, '3.5',  'Sentença ou acórdão',                                               0],
        [4, '4.1',  'Laudo final da reguladora Addvalora',                               0],
        [4, '4.2',  'Carta de encerramento da Fairfax',                                  0],
        [4, '4.3',  'Comprovante de pagamento / quitação',                               0],
    ];
}
