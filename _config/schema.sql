-- =============================================================
--  RCP Claims Center — Schema MySQL
--  Compatível com MySQL 8.0+ / MariaDB 10.5+
--  Execute: mysql -u root -p < _config/schema.sql
-- =============================================================

CREATE DATABASE IF NOT EXISTS rcp_claims
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE rcp_claims;

-- ─── Sinistros ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS claims (
  id                      INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  claim_number            VARCHAR(50)     UNIQUE NOT NULL  COMMENT 'Ex: FAI303306',
  segurado                VARCHAR(200)    NOT NULL,
  especialidade           VARCHAR(100),
  cidade_uf               VARCHAR(100),
  apolice                 VARCHAR(100),
  seguradora              VARCHAR(100),
  cobertura               DECIMAL(15,2)   DEFAULT 0,
  ocorrencia_data         DATE,
  ocorrencia_procedimento VARCHAR(300),
  processo_numero         VARCHAR(100),
  vara                    VARCHAR(200),
  reclamante              VARCHAR(200),
  advogado_defesa         VARCHAR(200),
  audiencia_data          DATE,
  estimativa_prejuizo     DECIMAL(15,2)   DEFAULT 0,
  reguladora              VARCHAR(100),
  reguladora_contato      VARCHAR(200),
  status                  ENUM('aberto','em_regulacao','em_julgamento','encerrado')
                            NOT NULL DEFAULT 'em_regulacao',
  prioridade              ENUM('alta','media','baixa')
                            NOT NULL DEFAULT 'media',
  created_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status     (status),
  INDEX idx_prioridade (prioridade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Checklist de Documentos ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS checklist_items (
  id                  INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  claim_id            INT UNSIGNED   NOT NULL,
  phase               TINYINT UNSIGNED NOT NULL
                        COMMENT '1=Abertura 2=Regulação 3=Defesa 4=Encerramento',
  item_code           VARCHAR(10)    NOT NULL  COMMENT 'Ex: 2.10',
  description         VARCHAR(500)   NOT NULL,
  status              ENUM('pendente','concluido','na','condicional')
                        NOT NULL DEFAULT 'pendente',
  conditional         TINYINT(1)     NOT NULL DEFAULT 0,
  conditional_answer  ENUM('sim','nao') NULL,
  notes               TEXT,
  updated_at          TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE,
  INDEX idx_claim_phase (claim_id, phase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Timeline de Eventos ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS claim_timeline (
  id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  claim_id    INT UNSIGNED   NOT NULL,
  event_date  DATE           NOT NULL,
  title       VARCHAR(200)   NOT NULL,
  description TEXT,
  event_type  ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
  created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE,
  INDEX idx_claim_date (claim_id, event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Dados demo: caso Dr. Raymundo ───────────────────────────────────────────
INSERT INTO claims (
  claim_number, segurado, especialidade, cidade_uf,
  apolice, seguradora, cobertura,
  ocorrencia_data, ocorrencia_procedimento,
  processo_numero, vara, reclamante, advogado_defesa, audiencia_data,
  estimativa_prejuizo, reguladora, reguladora_contato,
  status, prioridade
) VALUES (
  'FAI303306',
  'Dr. Raymundo Nonato Almeida Junior',
  'Cirurgia Bariátrica', 'São Paulo/SP',
  '47-5-0031448', 'Fairfax Brasil (FFPlace)', 2000000.00,
  '2024-11-12', 'Cirurgia Bariátrica',
  '0012345-67.2025.8.26.0100', '15ª Vara Cível — São Paulo',
  'Suzana Gonçalves Ferraz', 'Cândido & Henrique Advogados',
  '2026-05-07',
  1784733.00,
  'Addvalora', 'Aline R. Lonardoni — alonardoni@addvaloraglobal.com',
  'em_regulacao', 'alta'
);

-- Checklist padrão para FAI303306 (claim_id = 1)
INSERT INTO checklist_items (claim_id, phase, item_code, description, status, conditional) VALUES
  (1, 1, '1.1',  'Formulário de Aviso de Sinistro Fairfax',                       'concluido', 0),
  (1, 1, '1.2',  'Declaração de não existência de outros seguros (Addvalora)',      'concluido', 0),
  (1, 1, '1.3',  'Cópia da notificação extrajudicial ou citação judicial',          'pendente',  0),
  (1, 1, '1.4',  'Instrumento de procuração para o corretor',                       'pendente',  0),
  (1, 2, '2.1',  'Qualificação completa do segurado',                                'pendente',  0),
  (1, 2, '2.2',  'Apólice de seguro vigente',                                        'pendente',  0),
  (1, 2, '2.3',  'Termo de consentimento informado assinado pelo paciente',          'condicional',1),
  (1, 2, '2.4',  'Laudos, exames e receitários relacionados ao caso',                'pendente',  0),
  (1, 2, '2.5',  'Notificação ao CRM ou conselho de saúde',                         'condicional',1),
  (1, 2, '2.6',  'Laudo do perito nomeado pelo juízo',                               'condicional',1),
  (1, 2, '2.7',  'Cópia integral da petição inicial da reclamante',                   'pendente',  0),
  (1, 2, '2.8',  'Apólice de seguro RC do hospital (se aplicável)',                  'condicional',1),
  (1, 2, '2.9',  'Resultado de exames pré-operatórios',                               'pendente',  0),
  (1, 2, '2.10', 'Prontuário médico completo do paciente',                            'pendente',  0),
  (1, 2, '2.11', 'Documentos complementares da reguladora',                          'pendente',  0),
  (1, 3, '3.1',  'Contestação apresentada pelo advogado de defesa',                   'pendente',  0),
  (1, 3, '3.2',  'Despachos e decisões interlocutórias relevantes',                   'pendente',  0),
  (1, 3, '3.3',  'Resultado de perícia judicial',                                     'na',        0),
  (1, 3, '3.4',  'Proposta de acordo ou contraproposta',                              'pendente',  0),
  (1, 3, '3.5',  'Sentença ou acórdão',                                               'pendente',  0),
  (1, 4, '4.1',  'Laudo final da reguladora Addvalora',                               'pendente',  0),
  (1, 4, '4.2',  'Carta de encerramento da Fairfax',                                  'pendente',  0),
  (1, 4, '4.3',  'Comprovante de pagamento / quitação',                               'pendente',  0);

-- Timeline de FAI303306
INSERT INTO claim_timeline (claim_id, event_date, title, description, event_type) VALUES
  (1, '2025-03-20', 'Abertura do Sinistro',         'Segurado notificado judicialmente. Fairfax acionada.',              'info'),
  (1, '2025-03-28', 'Reguladora Designada',          'Addvalora designada como reguladora do sinistro.',                  'info'),
  (1, '2025-04-05', 'Documentação Solicitada',      'Addvalora solicita 11 documentos com prazo de 30 dias.',            'warning'),
  (1, '2025-04-20', 'Docs Parciais Enviados',        'Itens 1.1 e 1.2 enviados à Addvalora.',                              'success'),
  (1, '2026-05-07', 'Audiência Realizada',            'Data de audiência passou. Aguardando retorno do advogado de defesa.','danger');
