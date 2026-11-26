# EPIC — Almoxarifado Escolar

| Campo | Valor |
| --- | --- |
| Pacote | `buriti/i-educar-almoxarifado-escolar-package` |
| TR Laguna | **5.7** |
| Prioridade | **P0** |
| Nota atual (executivo) | **D** |
| Fonte | `packages/buriti/docs/laguna/DOC-EXECUTIVO-PE-77-2026-LAGUNA-IEDUCAR.md` |
| Regra de ouro | Pregão Eletrônico 77/2026 · Laguna/SC · PoC ≥ 85% por módulo · gaps ≤ 30 dias |
| Base atual | Sem módulo dedicado maduro (distinto da merenda) |

## Objetivo

Estoque e distribuição de materiais pedagógicos/administrativos às escolas.

## Padrão técnico (i-Educar 2.12)

| Item | Valor |
| --- | --- |
| Alvo | i-Educar **2.12** · PHP **≥ 8.3** · Illuminate **^11|^12|^13** (Laravel 13 no app) |
| Composer | `buriti/i-educar-almoxarifado-escolar-package` |
| Namespace | `iEducar\Packages\BuritiAlmoxarifadoEscolar\` → `src/` |
| Compat | `extra.ieducar.compatible: ["2.12"]` · GPL-2.0-or-later · `type: library` |
| Schema / dados | almoxarifado |
| Rotas | prefixo `almoxarifado-escolar`; SME com middleware `ieducar.*` + `auth`; API apps via Sanctum quando exposto |
| Tenancy | `PackageTenant::allows('buriti/i-educar-almoxarifado-escolar-package')` antes de migrations/menu |
| Plug-and-play | path em `packages/buriti/…`; manter em `ignore` até instalável |
| Referência | `packages/buriti/PADRAO-PACOTE-2.12.md` |

**Nota de arquitetura:** Material pedagógico/admin — distinto de `alimentacao`.

## Cobertura 100% do TR

Este EPIC cobre **todos** os requisitos do módulo(s) 5.7 listados em [`REQUISITOS-TR.md`](./REQUISITOS-TR.md) (13 itens OCR).

- PoC: ≥ **85%** demonstráveis e operacionais.
- Até **15%** restante: produção em ≤ **30 dias**, sem ônus.
- Nenhum item do `REQUISITOS-TR.md` pode ficar fora do `ROADMAP.md` / `TODO.md` (direto ou por grupo rastreável).

## Épicos filhos

1. Catálogo de materiais e saldos por depósito/escola.
2. Requisição escola → SME, aprovação e atendimento.
3. Entradas, saídas, inventário e inventário ciclico.
4. Relatórios de consumo e pendências.

## Critérios de aceite (PoC)

- [ ] Checklist [`REQUISITOS-TR.md`](./REQUISITOS-TR.md) com **100%** dos itens classificados (atende / gap / N/A)
- [ ] Itens **atende** demonstráveis na PoC (≥ 85% do módulo 5.7)
- [ ] Itens **gap** ≤ 15% com plano ≤ 30 dias em produção, sem ônus
- [ ] Nenhum requisito do TR omitido da matriz


## Fora de escopo deste EPIC

- Orçamento comercial da proposta Laguna.
- Substituição do core i-Educar fora do domínio do módulo.
