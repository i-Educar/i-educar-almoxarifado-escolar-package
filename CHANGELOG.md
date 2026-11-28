# Changelog

## [0.2.3] — 2026-11-27

- Cabeçalho de Pedidos: lead enxuto; ações só com ícone (carrinho, semáforo vermelho de alertas, entrada/saída).

## [0.2.2] — 2026-11-27

- Ciclo operacional alinhado: solicitado → aceite → separação → separado → em trânsito → entregue → conferido.
- Assinaturas formais no PDF/verificação: **Aceite, Entrega, Conferência** (Separação e Em trânsito só operacionais).
- PDF da nota: bloco «Solicitado por» com data/hora (sem assinatura do solicitante).
- Em trânsito (remessa/QR) notifica o solicitante; não entra na verificação de autenticidade.
- Ação «Marcar separado» + reenquadramento de pedidos legados no `normalizarPayload`.
- Balanço PDF, fotos de itens, permissões de depósito/retirar faltosos.

## [0.2.1] — 2026-11-26

- Ajustes de UI, demos e registro de `almoxarifado:demo` no HTTP.

## [0.2.0] — 2026-11-25

- Lançamento Buriti/Laguna com UI tema Buriti, demos PoC e declaração explícita de compatibilidade com i-Educar 2.12 e i-Diário (Portabilis / espelho Laguna).
