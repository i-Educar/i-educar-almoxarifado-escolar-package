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
    'permissoes' => [
        'menu' => [
            'painel' => true,
            'itens' => true,
            'saldos' => true,
            'pedidos' => true,
            'movimentos' => true,
        ],

        'assinaturas' => [
            // Separação no depósito — tipicamente SME / institucional
            'separacao' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('ALMOXARIFADO_ASSINA_SEPARACAO', 'admin,institucional'))
            ))),
            // Aceite da escola
            'aceite' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('ALMOXARIFADO_ASSINA_ACEITE', 'admin,institucional,escolar'))
            ))),
            // Entrega (dispara baixa de estoque)
            'entrega' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('ALMOXARIFADO_ASSINA_ENTREGA', 'admin,institucional,escolar'))
            ))),
            // Conferência final
            'conferencia' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('ALMOXARIFADO_ASSINA_CONFERENCIA', 'admin,institucional,escolar'))
            ))),
        ],
    ],
];
