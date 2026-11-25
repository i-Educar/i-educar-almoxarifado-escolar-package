<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Services;

use Illuminate\Support\Facades\DB;

final class AlmoxarifadoDemoService
{
    public const DEMO_TAG = 'laguna-carga-v1';

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
            ['sku' => 'CAD-A4', 'titulo' => 'Caderno A4', 'saldo' => 120, 'minimo' => 40],
            ['sku' => 'LAP-HB', 'titulo' => 'Lápis HB', 'saldo' => 18, 'minimo' => 50],
            ['sku' => 'SULFITE', 'titulo' => 'Resma sulfite', 'saldo' => 64, 'minimo' => 20],
            ['sku' => 'GIZ', 'titulo' => 'Giz branco', 'saldo' => 8, 'minimo' => 30],
            ['sku' => 'TINTA', 'titulo' => 'Tinta guache sortida', 'saldo' => 22, 'minimo' => 10],
        ];
        foreach ($itens as $i) {
            PocRegistro::gravar('item', $i['titulo'], 'SKU '.$i['sku'], array_merge($i, ['demo' => self::DEMO_TAG]));
        }

        $movs = 0;
        foreach ([
            ['item' => 'CAD-A4', 'sentido' => 'entrada', 'qtd' => 40, 'motivo' => 'Compra central'],
            ['item' => 'LAP-HB', 'sentido' => 'saida', 'qtd' => 12, 'motivo' => 'Distribuição EMEF Anita'],
            ['item' => 'GIZ', 'sentido' => 'saida', 'qtd' => 10, 'motivo' => 'Pedido urgente polo norte'],
        ] as $m) {
            PocRegistro::gravar('movimento', $m['item'].' · '.$m['sentido'], $m['motivo'], array_merge($m, [
                'demo' => self::DEMO_TAG,
                'quando' => now()->toDateTimeString(),
            ]));
            $movs++;
        }

        $pedidos = 0;
        foreach ([
            ['escola' => 'EMEF Anita Garibaldi', 'status' => 'enviado', 'itens' => [['sku' => 'LAP-HB', 'qtd' => 20]]],
            ['escola' => 'EMEI Lagoa', 'status' => 'carrinho', 'itens' => [['sku' => 'CAD-A4', 'qtd' => 10], ['sku' => 'GIZ', 'qtd' => 5]]],
        ] as $p) {
            PocRegistro::gravar('pedido', $p['escola'], $p['status'], array_merge($p, [
                'demo' => self::DEMO_TAG,
                'criado_em' => now()->toDateTimeString(),
            ]));
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

    private function jaPovoado(): bool
    {
        foreach (PocRegistro::listar('item', 50) as $r) {
            if (($r['payload']['demo'] ?? null) === self::DEMO_TAG) {
                return true;
            }
        }

        return false;
    }

    private function limparDemo(): void
    {
        foreach (['item', 'movimento', 'pedido'] as $tipo) {
            foreach (PocRegistro::listar($tipo, 500) as $r) {
                if (($r['payload']['demo'] ?? null) === self::DEMO_TAG) {
                    DB::table(PocRegistro::TABELA)->where('id', $r['id'])->delete();
                }
            }
        }
    }
}
