<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Services;

use RuntimeException;

/**
 * Entrada e saída de itens — ajusta o saldo do catálogo e registra o movimento.
 */
final class MovimentoEstoque
{
    public const string TIPO = 'movimento';

    public const string ENTRADA = 'entrada';

    public const string SAIDA = 'saida';

    /**
     * @return array{id:int,saldo:int}
     */
    public static function registrar(
        string $sentido,
        string $sku,
        string $titulo,
        int $quantidade,
        string $origem = 'manual',
        ?int $pedidoId = null,
        ?string $observacao = null,
        ?string $responsavel = null,
        ?int $responsavelId = null,
    ): array {
        $sentido = $sentido === self::ENTRADA ? self::ENTRADA : self::SAIDA;
        $sku = strtoupper(trim($sku));
        $titulo = trim($titulo);
        $quantidade = max(1, $quantidade);

        if ($sku === '' || $titulo === '') {
            throw new RuntimeException('SKU e título são obrigatórios no movimento.');
        }

        $item = self::encontrarItem($sku);
        if ($item === null) {
            throw new RuntimeException("Item {$sku} não cadastrado no depósito.");
        }

        $saldoAtual = (int) ($item['payload']['saldo'] ?? 0);
        if ($sentido === self::SAIDA && $quantidade > $saldoAtual) {
            throw new RuntimeException("Saldo insuficiente de {$sku}: disponível {$saldoAtual}, pedido {$quantidade}.");
        }

        $novoSaldo = $sentido === self::ENTRADA
            ? $saldoAtual + $quantidade
            : $saldoAtual - $quantidade;

        $payloadItem = $item['payload'];
        $payloadItem['saldo'] = $novoSaldo;
        PocRegistro::atualizar((int) $item['id'], $payloadItem);

        $id = PocRegistro::gravar(self::TIPO, $titulo, strtoupper($sentido).' '.$sku, [
            'sentido' => $sentido,
            'sku' => $sku,
            'titulo' => $titulo,
            'quantidade' => $quantidade,
            'saldo_antes' => $saldoAtual,
            'saldo_depois' => $novoSaldo,
            'origem' => $origem,
            'pedido_id' => $pedidoId,
            'observacao' => $observacao,
            'responsavel' => $responsavel,
            'responsavel_id' => $responsavelId,
            'quando' => now()->toDateTimeString(),
        ]);

        return ['id' => $id, 'saldo' => $novoSaldo];
    }

    /**
     * @return list<array{id:int,tipo:string,titulo:string,meta:?string,payload:array<string,mixed>,created_at:?string}>
     */
    public static function listar(int $limite = 200): array
    {
        return PocRegistro::listar(self::TIPO, $limite);
    }

    /** @return array{id:int,tipo:string,titulo:string,meta:?string,payload:array<string,mixed>,created_at:?string}|null */
    private static function encontrarItem(string $sku): ?array
    {
        foreach (PocRegistro::listar('item', 500) as $r) {
            $skuItem = strtoupper((string) ($r['payload']['sku'] ?? ''));
            if ($skuItem === $sku) {
                return $r;
            }
        }

        return null;
    }
}
