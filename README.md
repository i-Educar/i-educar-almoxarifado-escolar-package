# Almoxarifado escolar (TR 5.7)

Pacote Buriti para o i-Educar **2.12** (organização [i-Educar](https://github.com/i-Educar)).

Composer: `buriti/i-educar-almoxarifado-escolar-package`

## Compatibilidade

| Produto | Versão |
| --- | --- |
| **i-Educar** | **2.12.x** (min. 2.12.0) |
| **i-Diário** | Portabilis i-Diário (espelho Laguna) via API/SSO do core |
| Pacote | `0.2.2` |

Declarado em `composer.json` → `extra.ieducar` e `extra.idiario`.

## Ciclo do pedido

```
solicitado → aceite → separação → separado → em trânsito → entregue → conferido
```

| Etapa | Tipo | Onde aparece |
| --- | --- | --- |
| Solicitado | operacional | Trilho / status (quem pediu + data/hora no PDF) |
| **Aceite** | **assinatura** | PDF, verificação pública, LibreSign |
| Separação / Separado | operacional | Trilho; botão «Marcar separado» |
| Em trânsito | operacional | Remessa/QR; **notifica o solicitante**; **não** na verificação |
| **Entrega** | **assinatura** | PDF / verificação (após Em trânsito) |
| **Conferência** | **assinatura** | PDF / verificação (finaliza o pedido) |

Assinaturas formais (`PedidoCarrinho::ETAPAS_ASSINATURA`): `aceite`, `entrega`, `conferencia`.

## Documentos Laguna / PE-77/2026

- `EPIC.md` · `ROADMAP.md` · `TODO.md` · `REQUISITOS-TR.md` · `CHANGELOG.md`
- Padrão: `packages/buriti/PADRAO-PACOTE-2.12.md` (no monorepo)

## Smoke

```bash
php packages/buriti/i-educar-almoxarifado-escolar-package/tests/run.php
```
