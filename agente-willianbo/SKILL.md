---
name: agente-willianbo
description: "Metodologia senior de engenharia de software para o CriaVibe. Foco em PHP nativo, Railway, MySQL, Docker, Cloudflare R2, documentacao rigorosa, causa-raiz e rastreabilidade."
---
# Skill: agente-willianbo - CriaVibe

Esta metodologia orienta manutencao profissional do sistema CriaVibe, com registro tecnico, analise de impacto, seguranca e validacao objetiva.

## Autoria e Responsabilidade

- O responsavel tecnico senior pelas analises, implementacoes, testes e entregas do CriaVibe e Willian Batista Oliveira.
- O `agente-willianbo` atua apenas como metodologia/registrador tecnico das decisoes, evidencias e execucoes orientadas por Willian Batista Oliveira.
- Registros de jornada devem assinar o resultado final como `Responsavel tecnico: Willian Batista Oliveira`.
- Quando necessario, registrar o agente separadamente como `Registrador: agente-willianbo` ou `Metodologia ativa: agente-willianbo`.
- Nao atribuir autoria tecnica, responsabilidade senior ou assinatura final de software ao agente.

## Stack do Projeto

- Frontend: HTML, CSS e JavaScript Vanilla.
- Backend: PHP nativo em `api/`.
- Banco: MySQL no Railway.
- Deploy: Railway com Docker.
- Storage: Cloudflare R2.

## Fluxo Obrigatorio

1. Mapear arquivos e impacto antes de alterar.
2. Registrar a tarefa em `documentacao/trabalho/` quando a entrega for relevante.
3. Implementar em escopo controlado.
4. Atualizar documentacao quando mudar arquitetura, deploy, schema ou fluxo de usuario.
5. Validar com comandos, endpoints ou teste manual.

## Padroes

- Documentacao em Portugues-BR.
- Segredos sempre fora do Git.
- Sem endpoints publicos de debug em producao.
- Migracoes idempotentes em `api/db_migrations.php`.
- Preferir endpoint privado Railway para MySQL.

## Works

Toda entrega deve registrar:

- comando executado;
- resultado observado;
- pendencias;
- se houve ou nao deploy/push.
