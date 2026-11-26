# ROADMAP — Almoxarifado Escolar

> Pacote: `buriti/i-educar-almoxarifado-escolar-package` · TR **5.7** · Prioridade **P0** · Nota **D**  
> Alinhado a `packages/buriti/docs/laguna/DOC-EXECUTIVO-PE-77-2026-LAGUNA-IEDUCAR.md`

## Visão

Estoque e distribuição de materiais pedagógicos/administrativos às escolas.

## Fases

### F0 · Domínio

Separar merenda (5.2) de material (5.7).

### F1 · Estoque

Itens, saldos, movimentações.

### F2 · Requisições

Fluxo escola/SME.

### F3 · Inventário

Contagens e ajustes.

### F4 · PoC

Demo de distribuição.

## Cobertura 100% dos requisitos (TR)

Fonte: [`REQUISITOS-TR.md`](./REQUISITOS-TR.md) — **13 itens**.

Cada lote deve sair da PoC como **atende** ou entrar no plano de 30 dias.

### Lote R1 — itens 5.7.1 … 5.7.140 (13)

- `5.7.1`, `5.7.2`, `5.7.3`, `5.7.4`, `5.7.5`, `5.7.6`, `5.7.7`, `5.7.8`, `5.7.9`, `5.7.10`, `5.7.12`, `5.7.13`, `5.7.140`

- [ ] Implementar / validar / evidenciar na matriz PoC

### Gate 100%

- [ ] Todos os lotes R* com status atende ou gap≤30dias
- [ ] Soma atende ≥ 85% na demonstração

## Conformidade Laravel / pacote i-Educar 2.12

Antes da fase de código:

1. Criar `composer.json` com `php >=8.3`, `illuminate/support`, provider em `extra.laravel`, `compatible: ["2.12"]`.
2. Scaffold: `src/Providers`, `Http/Controllers`, `Services`, `Support`, `database/migrations`, `routes/web.php`, views Blade.
3. Schema `almoxarifado` (ou estratégia documentada); FKs lógicas para núcleo (`ref_cod_aluno`, `ref_cod_escola`, etc.).
4. Menu/processos em migration própria; respeitar multi-tenant.
5. Não acoplar UI mobile; dados para lojas só via ``buriti/i-educar-apps-api-package``.
6. Seguir `packages/buriti/PADRAO-PACOTE-2.12.md`.

## Dependências

- Cadastro mestre de aluno/escola/turma no i-Educar (SGE).
- Matriz item a item OCR do Anexo I antes de cravar %.
- Alinhamento com `i-educar-apps-api-package` (APIs 5.13/5.14; UI/lojas em equipe externa) e exports (4.x) quando o módulo expõe dados ao cidadão/SME.

## Riscos

- Valor global do edital incompatível com greenfield total — reuso e MVP demonstrável são obrigatórios.
- PDF escaneado: numerações OCR podem exigir reconciliação manual.
- PoC só conta o que estiver operacional no dia.

## Definição de pronto da fase PoC

Checklist do `TODO.md` marcado para a fatia MVP + roteiro de demonstração ensaiado.
