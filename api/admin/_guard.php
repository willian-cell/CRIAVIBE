<?php
/**
 * Guarda da area administrativa do sistema.
 *
 * Diferente de require_fotografo(), que libera qualquer conta do tipo admin,
 * aqui o acesso e restrito a uma unica identidade: a conta definida em
 * ADMIN_EMAIL. E o painel que enxerga e altera dados de todos os fotografos,
 * entao a superficie fica deliberadamente estreita.
 */

function require_super_admin(): array {
    $u = require_auth();

    if (!is_super_admin($u)) {
        json_out(['status' => 'erro', 'mensagem' => 'Area restrita ao administrador do sistema.'], 403);
    }

    return $u;
}

/**
 * Colunas usadas pelo painel administrativo que podem nao existir em bancos
 * criados antes desta tela. Idempotente, no mesmo padrao do restante da API.
 */
function admin_ensure_schema(): void {
    try { db()->exec("ALTER TABLE usuarios ADD COLUMN bloqueado TINYINT(1) NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { db()->exec("ALTER TABLE usuarios ADD COLUMN criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $e) {}
    try { db()->exec("ALTER TABLE usuarios ADD COLUMN foto_perfil VARCHAR(512) DEFAULT NULL"); } catch (Exception $e) {}
    try { db()->exec("ALTER TABLE usuarios ADD COLUMN telefone VARCHAR(40) DEFAULT NULL"); } catch (Exception $e) {}
    try { db()->exec("ALTER TABLE usuarios ADD COLUMN cidade VARCHAR(120) DEFAULT NULL"); } catch (Exception $e) {}
    try { db()->exec("ALTER TABLE usuarios ADD COLUMN ultimo_acesso TIMESTAMP NULL DEFAULT NULL"); } catch (Exception $e) {}
    try { db()->exec("ALTER TABLE clientes ADD COLUMN foto_cliente VARCHAR(512) DEFAULT NULL"); } catch (Exception $e) {}
}

/**
 * Impede que o administrador se auto-bloqueie ou se auto-exclua, o que
 * deixaria o sistema sem nenhuma conta capaz de abrir este painel.
 */
function admin_bloquear_auto_alvo(array $alvo): void {
    if (is_super_admin($alvo)) {
        json_out([
            'status' => 'erro',
            'mensagem' => 'Esta e uma conta administradora do sistema e nao pode ser bloqueada nem excluida por aqui.'
        ], 400);
    }
}
