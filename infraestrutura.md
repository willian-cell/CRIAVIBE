# Infraestrutura CriaVibe

Este documento descreve a infraestrutura atual do CriaVibe.

## Arquitetura Atual

```text
Navegador
  |
  v
Railway - Servico CRIAVIBE
  |
  |-- PHP nativo via Docker
  |-- router.php
  |-- endpoints em /api
  |
  +--> Railway MySQL via endpoint privado
  |
  +--> Cloudflare R2 para fotos e capas
```

## Servico da Aplicacao

- Hospedagem: Railway.
- Runtime: Docker com `php:8.2-cli`.
- Comando: `php -S 0.0.0.0:${PORT:-8080} router.php`.
- Porta: fornecida pelo Railway via `PORT`.

### O que o router entrega

O servidor embutido do PHP entrega **qualquer arquivo que exista no diretorio**, e
a imagem carrega o repositorio inteiro. Por isso `router.php` recusa
explicitamente arquivos ocultos, travessia de diretorio, as pastas internas
(`documentacao/`, `DOCUMENTATION/`, `scripts/`, `agente-willianbo/`), os nomes de
configuracao (`Dockerfile`, `Procfile`, `docker-compose.yml`, `router.php`,
`env_example.txt`) e as extensoes de documentacao e configuracao.

**Ao adicionar um arquivo novo na raiz do projeto, considere se ele deve ser
publico.** Sem entrada na lista de bloqueio, ele sera servido.

### Sessoes e deploy

As sessoes do PHP ficam no filesystem do container, que e efemero. **Todo deploy
reinicia o container e apaga todas as sessoes ativas** — quem estava logado
recebe `Nao autenticado.` na acao seguinte. Um `401` logo depois de um deploy
quase sempre e isso, e nao um problema de permissao.

## Banco de Dados

- Tipo: MySQL.
- Provedor: Railway MySQL.
- Conexao recomendada: `MYSQL_URL` privado.
- Evitar: `MYSQL_PUBLIC_URL`, `RAILWAY_TCP_PROXY_DOMAIN` e endpoints publicos para conexao interna.

## Storage

- Provedor: Cloudflare R2.
- Bucket: `criavibe-galeria`.
- Uso: **todo** arquivo enviado pelo sistema. Nada e gravado em disco local,
  porque o filesystem do container Railway e efemero e some a cada deploy.
- Integracao: `api/lib/Storage.php` (camada unica, sem fallback local),
  `api/lib/R2Storage.php` (protocolo S3) e `api/lib/R2Presigner.php`
  (URLs assinadas para upload direto do navegador).

### Organizacao do bucket

```text
galerias/{id}/                  fotos originais
galerias/{id}/derivados/        miniaturas geradas
galerias/{id}/capas/            capa de apresentacao
musicas/{galeria_id}/           trilhas da galeria
clientes/{cliente_id}/          foto do cliente
perfis/{usuario_id}/            foto de perfil do fotografo
alunos/{aluno_id}/              foto do aluno (modulo de agendamento)
```

### Requisitos de configuracao

Quatro coisas precisam estar simultaneamente corretas para o upload funcionar:

1. **`R2_ACCOUNT_ID` da conta certa.** Ele monta o hostname do endpoint
   (`https://<account_id>.r2.cloudflarestorage.com/<bucket>`). Um id de outra
   conta tambem tem 32 hexadecimais e passa em qualquer validacao de formato, mas
   a Cloudflare **recusa o handshake TLS** para um subdominio de conta
   inexistente — nao responde erro HTTP. O sintoma que chega e
   `ssl/tls alert handshake failure` no servidor e `Failed to fetch` no
   navegador, nenhum dos dois lembrando a causa. Foi a falha de 09/09/2026.
2. **R2 habilitado na conta.** Se estiver desabilitado, toda operacao responde
   `403 NotEntitled`.
3. **Acesso publico (`r2.dev`) habilitado.** Sem ele as fotos nao carregam e
   `api/fotos/process_thumbs.php` nao consegue baixar o original para gerar
   miniatura. Teste: objeto inexistente deve responder `404`, nao `403`.
4. **Politica de CORS com a origem do frontend.** A lista e literal; ver
   README e `documentacao/trabalho/trabalho_05_09_2026.md`.

As cinco variaveis `R2_*` precisam ser **da mesma conta Cloudflare**. Misturar o
account id de uma com a credencial de outra e o modo mais rapido de produzir um
erro que nao aponta para lugar nenhum.

### Diagnostico

`/api/admin/diagnostico_r2.php`, aberto no navegador logado como administrador do
sistema, percorre o caminho completo de um upload real (formato do account id,
handshake TLS em cinco variantes de transporte, `PUT`, `HEAD`, leitura publica e
`DELETE`) e devolve o codigo HTTP e a causa concreta de cada etapa. E o caminho
mais curto para separar os quatro requisitos acima.

## Variaveis Necessarias

```env
MYSQL_URL=${{MySQL.MYSQL_URL}}

# As cinco abaixo tem de ser da mesma conta Cloudflare. O account id monta o
# hostname do endpoint: errado, a conexao morre no handshake TLS.
R2_ACCOUNT_ID=
R2_BUCKET_NAME=
R2_PUBLIC_URL=
R2_ACCESS_KEY_ID=
R2_SECRET_KEY=
SECRET_KEY=

# Conta com acesso ao painel administrativo (/admin.html).
# Lista separada por virgula. Sem definir, vale o padrao em api/config.php.
ADMIN_EMAILS=

# Enfileiramento de miniaturas no Redis. Manter 0 enquanto nao houver um
# servico worker consumindo a fila, senao os jobs se acumulam indefinidamente.
ENABLE_IMAGE_QUEUE=0
```

`ADMIN_EMAIL` e `ADMIN_SENHA` aparecem em arquivos `.env` antigos mas nao sao
lidas por nenhum ponto do runtime atual. `SECRET_KEY` tambem nao e usada hoje.

## Bootstrap do Banco

`api/db_migrations.php` e idempotente:

- cria tabelas quando o MySQL esta vazio;
- adiciona colunas faltantes em bancos existentes;
- permite execucao inicial antes de existir usuario;
- exige sessao de `admin` ou `fotografo` quando ja existem usuarios.

Tabelas mantidas:

- `usuarios`
- `clientes`
- `galerias`
- `imagens`
- `musicas`

## Arquivos Removidos

Foram removidos arquivos que nao fazem parte do runtime atual ou eram risco operacional:

- `reset_admin.php`
- `check_db.php`
- `check_deploy.php`
- `check_limits.php`
- `api/teste_db.php`
- `api/test_r2.php`
- `api/ver_logs.php`
- `Manual_Tecnico_criavibe_site.pdf`
- registros antigos e desatualizados do agente WillianBO
- referencia externa de manual tecnico que nao pertence ao CriaVibe
- `CREDENCIAIS.md` local

## Validacao Registrada

- Conexao Railway com MySQL: ok.
- Migracao `/api/db_migrations.php`: ok.
- Cadastro: ok.
- Login: ok.
- Sessao `/api/auth/me.php`: ok.

## Regras de Manutencao

- Nao versionar `.env`, credenciais, logs ou uploads reais.
- Nao adicionar endpoints publicos de debug em producao.
- Atualizar este documento quando deploy, banco ou storage mudarem.
