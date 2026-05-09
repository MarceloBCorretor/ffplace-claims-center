<?php
/**
 * RCP Claims Center — Chaves de API para IA
 *
 * SEGURANÇA: Este arquivo é protegido por _config/.htaccess (Deny from all).
 * NUNCA versione chaves reais. Use variáveis de ambiente em produção.
 *
 * Como usar:
 *   1. Copie este arquivo para _config/ai_keys.php
 *   2. Preencha as chaves abaixo
 *   3. Confirme que _config/.htaccess existe com: Order Allow,Deny / Deny from all
 */
$AI_KEYS = [
    // Anthropic Claude — https://console.anthropic.com/
    'anthropic' => '',   // ex: sk-ant-api03-...

    // OpenAI GPT-4o — https://platform.openai.com/
    'openai'    => '',   // ex: sk-proj-...

    // DeepSeek — https://platform.deepseek.com/
    'deepseek'  => '',   // ex: sk-...
];
