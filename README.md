# Almoxarifado escolar

Estoque e distribuição de materiais pedagógicos e administrativos da rede para as escolas.

Composer: `buriti/i-educar-almoxarifado-escolar-package`

## Para que serve

Centraliza o ciclo de pedido de materiais: da solicitação da escola ao aceite, separação, remessa, entrega e conferência — com saldo, balanço e documentos assináveis.

## Desenho de funções

| Função | Entrega |
| --- | --- |
| Catálogo de itens | Cadastro com foto, unidade e atributos do material |
| Saldos e balanço | Posição de estoque e exportação de balanço |
| Pedidos (carrinho) | Abertura, itens, fechamento e nota/etiquetas/QR |
| Trilho operacional | solicitado → aceite → separação → separado → em trânsito → entregue → conferido |
| Assinaturas formais | Aceite, entrega e conferência integráveis à assinatura digital |
| Remessa / notificação | Marcação em trânsito e aviso ao solicitante |
| Painel SME/escola | `/almoxarifado-escolar` |

## Quem usa

- Almoxarifado / SME
- Direção e secretaria da escola
- Conferentes na entrega

## Integrações

- Assinatura digital (LibreSign)
- Exportação unificada
- Núcleo (escolas, usuários, permissões)

## Compatibilidade

| Produto | Versão |
| --- | --- |
| **i-Educar** | **2.12.x** |
| **i-Diário** | Portabilis i-Diário (API / SSO do núcleo) |
| Pacote | `0.2.4` |

Declarado em `composer.json` → `extra.ieducar` / `extra.idiario`.

## Documentação

| Documento | Uso |
| --- | --- |
| [`EPIC.md`](./EPIC.md) | Escopo de produto, aceite e critérios |
| [`ROADMAP.md`](./ROADMAP.md) | Fases e entregas |
| [`TODO.md`](./TODO.md) | Backlog operacional |
| [`REQUISITOS-TR.md`](./REQUISITOS-TR.md) | Rastreio de requisitos (planejamento) |
| [`CHANGELOG.md`](./CHANGELOG.md) | Histórico de versões |

---

Licença: **GPL-2.0-or-later** · Organização [i-Educar](https://github.com/i-Educar)
