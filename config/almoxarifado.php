<?php

return [
    'public' => [
        'aparecer_na_home' => false,
        'titulo' => 'Almoxarifado escolar',
        'ordem' => 61,
        'url' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | PoC operacional — almoxarifado
    |--------------------------------------------------------------------------
    */
    'estoque_minimo_alerta' => (float) env('ALMOXARIFADO_MIN_FATOR', 1.0),
    'escolas_do_nucleo' => (bool) env('ALMOXARIFADO_ESCOLAS_NUCLEO', true),
    'seed_demo_se_vazio' => (bool) env('ALMOXARIFADO_SEED_DEMO', true),

    /*
    |--------------------------------------------------------------------------
    | Permissões do módulo
    |--------------------------------------------------------------------------
    |
    | menu.*            Liga/desliga itens do menu lateral.
    | assinaturas.*     Quem pode registrar cada etapa da nota.
    |                   Aceita: admin, institucional, escolar, biblioteca, *
    |                   (ou níveis numéricos 1 / 2 / 4 / 8 do tipo_usuario).
    |                   Separe por vírgula no .env correspondente.
    |
    */
    /*
    | Setores que podem solicitar material (carrinho por setor).
    */
    'setores' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'ALMOXARIFADO_SETORES',
            'Secretaria,Coordenação,Pedagógico,Manutenção,Cozinha,Biblioteca,Outro'
        ))
    ))),

    'permissoes' => [
        'menu' => [
            'painel' => true,
            'itens' => true,
            'saldos' => true,
            'pedidos' => true,
            'alertas' => true,
            'movimentos' => true,
        ],

        /*
        | Ações especiais.
        | niveis: admin,institucional,escolar,… ou * (após habilitar).
        | carrinho_setor: admin do sistema tem acesso por padrão.
        */
        'acoes' => [
            'carrinho_setor' => [
                'habilitado' => (bool) env('ALMOXARIFADO_CARRINHO_SETOR', true),
                'niveis' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) env('ALMOXARIFADO_CARRINHO_SETOR_NIVEIS', 'admin'))
                ))),
            ],
            'pedido_fora_catalogo' => [
                'habilitado' => (bool) env('ALMOXARIFADO_PEDIDO_FORA_CATALOGO', false),
                'niveis' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) env('ALMOXARIFADO_PEDIDO_FORA_CATALOGO_NIVEIS', 'admin'))
                ))),
            ],
            // Almoxarifado retira faltosos → itens não atendidos / nota parcial
            'retirar_faltosos' => [
                'habilitado' => (bool) env('ALMOXARIFADO_RETIRAR_FALTOSOS', true),
                'niveis' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) env('ALMOXARIFADO_RETIRAR_FALTOSOS_NIVEIS', 'admin,institucional'))
                ))),
            ],
        ],

        /*
        | Ciclo: solicitado → aceite → separação → separado → em trânsito → entregue → conferido
        | Assinaturas formais (PDF/verificação): aceite, entrega, conferência.
        | Separação = operacional (permissão ainda usada para marcar separado / depósito).
        | Em trânsito = remessa/QR (notifica o solicitante; não aparece na verificação).
        */
        'assinaturas' => [
            // Aceite da solicitação no depósito (antes da separação)
            'aceite' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('ALMOXARIFADO_ASSINA_ACEITE', 'admin,institucional'))
            ))),
            // Separação operacional no depósito (não gera assinatura no PDF)
            'separacao' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('ALMOXARIFADO_ASSINA_SEPARACAO', 'admin,institucional'))
            ))),
            // Entrega / baixa de estoque (após Em trânsito)
            'entrega' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('ALMOXARIFADO_ASSINA_ENTREGA', 'admin,institucional,escolar'))
            ))),
            // Conferência final (= pedido finalizado)
            'conferencia' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('ALMOXARIFADO_ASSINA_CONFERENCIA', 'admin,institucional,escolar'))
            ))),
        ],
    ],
];
