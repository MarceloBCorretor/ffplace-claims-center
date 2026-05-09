# RCP Claims Center — Guia de Instalação Hostinger

## Pré-requisitos
- Conta Hostinger com plano Business ou superior (PHP 8.0+ e MySQL)
- Arquivo `rcp-claims.zip` (este pacote)

---

## PASSO 1 — Criar banco de dados

1. Acesse **hPanel** (hpanel.hostinger.com) → **Banco de Dados MySQL**
2. Clique em **Criar banco de dados**
3. Preencha:
   - Nome do banco: `rcp_claims`
   - Usuário: `rcp_user` (ou o nome que quiser)
   - Senha: crie uma senha forte e **anote**
4. Clique em **Criar**

---

## PASSO 2 — Importar o schema (tabelas + dados demo)

1. No hPanel → **phpMyAdmin** → selecione o banco `rcp_claims`
2. Clique na aba **Importar**
3. Clique em **Escolher arquivo** → selecione `_config/schema.sql` deste pacote
4. Clique em **Executar**

Resultado: 3 tabelas criadas + dados do sinistro FAI303306 já inseridos.

---

## PASSO 3 — Fazer upload dos arquivos

1. No hPanel → **Gerenciador de Arquivos**
2. Navegue até `public_html` (ou a pasta do seu domínio)
3. Clique em **Upload** → selecione `rcp-claims.zip`
4. Após o upload, clique com botão direito no zip → **Extrair**
5. Mova o conteúdo extraído para a raiz de `public_html`

---

## PASSO 4 — Configurar banco de dados

1. No Gerenciador de Arquivos, abra `_config/config.php`
2. Clique em **Editar** e atualize as 3 linhas:

```php
define('DB_USER', 'seu_usuario_criado_no_passo_1');
define('DB_PASS', 'sua_senha_criada_no_passo_1');
define('DB_NAME', 'rcp_claims');
```

3. Salve o arquivo.

---

## PASSO 5 — Chave da API Anthropic (IA)

O proxy de IA lê automaticamente a chave que já está em:

```
public_html/_config/anthropic_key.php
```

Se esse arquivo ainda não existir no servidor, crie-o com o conteúdo:

```php
<?php
define('ANTHROPIC_API_KEY', 'sk-ant-api03-SUA-CHAVE-REAL-AQUI');
```

> **Se a chave já está lá** (de outro projeto seu), não precisa fazer nada — o sistema a detecta e usa automaticamente.

---

## Pronto! Acesse o sistema

```
https://seudominio.com.br/
```

### URLs do sistema:
| Página | URL |
|--------|-----|
| Dashboard | `/index.html` |
| Sinistro FAI303306 | `/sinistro.html` |
| Checklist | `/checklist.html` |
| Documentos / PDF | `/documentos.html` |
| Análise IA | `/analise-ia.html` |
| Mensagens | `/mensagens.html` |

---

## Verificação rápida

Após instalar, teste se a API está funcionando:
```
https://seudominio.com.br/api/claims-api.php?id=1
```
Deve retornar JSON com os dados do Dr. Raymundo.

---

## Suporte
SUSEP 202031070 — Mar Del Plata Corretora de Seguros
