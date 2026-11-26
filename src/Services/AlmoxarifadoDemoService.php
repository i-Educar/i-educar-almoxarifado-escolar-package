<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Services;

use Illuminate\Support\Facades\DB;

final class AlmoxarifadoDemoService
{
    public const DEMO_TAG = 'laguna-carga-v2';

    /**
     * @return array{itens:int,movimentos:int,pedidos:int,pulado:bool,mensagem:string}
     */
    public function executar(bool $force = false): array
    {
        $vazio = ['itens' => 0, 'movimentos' => 0, 'pedidos' => 0, 'pulado' => false, 'mensagem' => ''];
        if (! PocRegistro::disponivel()) {
            $vazio['mensagem'] = 'schema almoxarifado ausente';

            return $vazio;
        }
        if (! $force && $this->jaPovoado()) {
            $vazio['pulado'] = true;
            $vazio['mensagem'] = 'já povoado (use --force)';

            return $vazio;
        }
        if ($force) {
            $this->limparDemo();
        }

        $itens = [
            ['sku' => 'CAD-A4', 'titulo' => 'Caderno A4', 'saldo' => 120, 'minimo' => 40, 'categoria' => 'papelaria'],
            ['sku' => 'LAP-HB', 'titulo' => 'Lápis HB', 'saldo' => 18, 'minimo' => 50, 'categoria' => 'papelaria'],
            ['sku' => 'SULFITE', 'titulo' => 'Resma sulfite', 'saldo' => 64, 'minimo' => 20, 'categoria' => 'papelaria'],
            ['sku' => 'GIZ', 'titulo' => 'Giz branco', 'saldo' => 8, 'minimo' => 30, 'categoria' => 'sala'],
            ['sku' => 'TINTA', 'titulo' => 'Tinta guache sortida', 'saldo' => 22, 'minimo' => 10, 'categoria' => 'arte'],
            ['sku' => 'APAGADOR', 'titulo' => 'Apagador quadro', 'saldo' => 35, 'minimo' => 15, 'categoria' => 'sala'],
            ['sku' => 'MARCADOR', 'titulo' => 'Marcador permanente', 'saldo' => 12, 'minimo' => 20, 'categoria' => 'sala'],
            ['sku' => 'COPIA-A4', 'titulo' => 'Folha sulfite colorida', 'saldo' => 200, 'minimo' => 50, 'categoria' => 'papelaria'],
            ['sku' => 'LIMPEZA-1', 'titulo' => 'Detergente 500ml', 'saldo' => 40, 'minimo' => 25, 'categoria' => 'limpeza'],
            ['sku' => 'LIMPEZA-2', 'titulo' => 'Papel higiênico fardo', 'saldo' => 15, 'minimo' => 20, 'categoria' => 'limpeza'],
            ['sku' => 'EPI-MASC', 'titulo' => 'Máscara descartável cx', 'saldo' => 6, 'minimo' => 10, 'categoria' => 'epi'],
            ['sku' => 'MERENDA-DESC', 'titulo' => 'Copo descartável 200ml', 'saldo' => 90, 'minimo' => 40, 'categoria' => 'merenda'],
        ];
        foreach ($itens as $i) {
            PocRegistro::gravar('item', $i['titulo'], 'SKU '.$i['sku'], array_merge($i, [
                'demo' => self::DEMO_TAG,
                'alerta_minimo' => $i['saldo'] < $i['minimo'],
            ]));
        }

        $movs = 0;
        foreach ([
            ['item' => 'CAD-A4', 'sentido' => 'entrada', 'qtd' => 40, 'motivo' => 'Compra central SME'],
            ['item' => 'LAP-HB', 'sentido' => 'saida', 'qtd' => 12, 'motivo' => 'Distribuição EMEF Anita'],
            ['item' => 'GIZ', 'sentido' => 'saida', 'qtd' => 10, 'motivo' => 'Pedido urgente polo norte'],
            ['item' => 'LIMPEZA-2', 'sentido' => 'entrada', 'qtd' => 30, 'motivo' => 'Empenho limpeza'],
            ['item' => 'LIMPEZA-1', 'sentido' => 'saida', 'qtd' => 8, 'motivo' => 'Remessa EMEI Pequeno Príncipe'],
            ['item' => 'EPI-MASC', 'sentido' => 'saida', 'qtd' => 4, 'motivo' => 'Campanha saúde escolar'],
            ['item' => 'MERENDA-DESC', 'sentido' => 'entrada', 'qtd' => 50, 'motivo' => 'Compra merenda'],
            ['item' => 'TINTA', 'sentido' => 'saida', 'qtd' => 5, 'motivo' => 'Oficina de artes'],
        ] as $m) {
            PocRegistro::gravar('movimento', $m['item'].' · '.$m['sentido'], $m['motivo'], array_merge($m, [
                'demo' => self::DEMO_TAG,
                'quando' => now()->subHours($movs + 1)->toDateTimeString(),
            ]));
            $movs++;
        }

        $escolas = $this->escolasRede();
        if ($escolas === []) {
            $escolas = ['EMEF Anita Garibaldi', 'EMEI Lagoa', 'EMEF Ribeirão Pequeno'];
        }

        $statusFluxo = ['carrinho', 'enviado', 'em_separacao', 'entregue', 'recusado_parcial'];
        $pedidos = 0;
        foreach ($escolas as $i => $escola) {
            $status = $statusFluxo[$i % count($statusFluxo)];
            $itensPedido = match ($i % 3) {
                0 => [['sku' => 'LAP-HB', 'qtd' => 20], ['sku' => 'CAD-A4', 'qtd' => 10]],
                1 => [['sku' => 'GIZ', 'qtd' => 5], ['sku' => 'LIMPEZA-1', 'qtd' => 4]],
                default => [['sku' => 'TINTA', 'qtd' => 3], ['sku' => 'MERENDA-DESC', 'qtd' => 20], ['sku' => 'SULFITE', 'qtd' => 2]],
            };
            PocRegistro::gravar('pedido', $escola, $status, [
                'escola' => $escola,
                'status' => $status,
                'itens' => $itensPedido,
                'demo' => self::DEMO_TAG,
                'criado_em' => now()->subDays($i)->toDateTimeString(),
                'prioridade' => $status === 'enviado' ? 'alta' : 'normal',
            ]);
            $pedidos++;
        }

        return [
            'itens' => count($itens),
            'movimentos' => $movs,
            'pedidos' => $pedidos,
            'pulado' => false,
            'mensagem' => 'itens='.count($itens)." movimentos={$movs} pedidos={$pedidos}",
        ];
    }

    /** @return list<string> */
    private function escolasRede(): array
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('pmieducar.escola')) {
                return [];
            }

            return DB::table('pmieducar.escola as e')
                ->join('cadastro.juridica as j', 'j.idpes', '=', 'e.ref_idpes')
                ->where('e.ativo', 1)
                ->orderBy('e.cod_escola')
                ->limit(8)
                ->pluck('j.fantasia')
                ->map(static fn ($n) => (string) $n)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function jaPovoado(): bool
    {
        foreach (PocRegistro::listar('item', 80) as $r) {
            if (($r['payload']['demo'] ?? null) === self::DEMO_TAG) {
                return true;
            }
        }

        return false;
    }

    private function limparDemo(): void
    {
        foreach (['item', 'movimento', 'pedido'] as $tipo) {
            foreach (PocRegistro::listar($tipo, 800) as $r) {
                $tag = $r['payload']['demo'] ?? null;
                if (in_array($tag, [self::DEMO_TAG, 'laguna-carga-v1'], true)) {
                    DB::table(PocRegistro::TABELA)->where('id', $r['id'])->delete();
                }
            }
        }
    }
}
