<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Services;

use iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos;

/**
 * Alertas de compra gerados por pedidos de itens sem cadastro ou sem saldo.
 */
final class AlertaCompra
{
    public const string TIPO = 'alerta_compra';

    public const string STATUS_ABERTO = 'aberto';

    public const string STATUS_EM_COMPRA = 'em_compra';

    public const string STATUS_ATENDIDO = 'atendido';

    public const string STATUS_DESCARTADO = 'descartado';

    /**
     * @param  array{sku:string,titulo:string,quantidade:int,motivo:string,escola?:string,setor?:?string,pedido_id?:?int,solicitante?:?string,solicitante_id?:?int}  $dados
     */
    public static function registrar(array $dados): int
    {
        $sku = strtoupper(trim((string) ($dados['sku'] ?? '')));
        $titulo = trim((string) ($dados['titulo'] ?? $sku));
        $motivo = (string) ($dados['motivo'] ?? 'fora_catalogo');
        $qtd = max(1, (int) ($dados['quantidade'] ?? 1));

        $payload = [
            'sku' => $sku !== '' ? $sku : 'NOVO',
            'titulo' => $titulo !== '' ? $titulo : 'Item não cadastrado',
            'quantidade' => $qtd,
            'motivo' => $motivo,
            'escola' => (string) ($dados['escola'] ?? ''),
            'setor' => isset($dados['setor']) ? trim((string) $dados['setor']) : null,
            'pedido_id' => isset($dados['pedido_id']) ? (int) $dados['pedido_id'] : null,
            'solicitante' => $dados['solicitante'] ?? null,
            'solicitante_id' => $dados['solicitante_id'] ?? null,
            'status' => self::STATUS_ABERTO,
            'criado_em' => now()->toDateTimeString(),
        ];

        $id = PocRegistro::gravar(
            self::TIPO,
            $payload['titulo'],
            $motivo.' · '.$qtd.' un.',
            $payload
        );

        if (class_exists(\iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::class)) {
            $msg = match ($motivo) {
                'sem_saldo' => "Almoxarifado: item sem saldo «{$payload['titulo']}» ({$qtd} un.) — avaliar compra.",
                'cobertura' => "Almoxarifado: falta de cobertura «{$payload['titulo']}» ({$qtd} un.) — compra para entrega parcial.",
                default => "Almoxarifado: item fora do catálogo «{$payload['titulo']}» ({$qtd} un.) — cadastrar/comprar.",
            };
            \iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::paraProcesso(
                Processos::ALERTAS_COMPRA,
                $msg,
                '/almoxarifado-escolar/alertas',
            );
        }

        return $id;
    }

    /**
     * @return list<array{id:int,titulo:string,meta:?string,payload:array<string,mixed>,created_at:?string}>
     */
    public static function listar(int $limite = 100, ?string $status = null): array
    {
        $out = [];
        foreach (PocRegistro::listar(self::TIPO, $limite) as $r) {
            $st = (string) ($r['payload']['status'] ?? self::STATUS_ABERTO);
            if ($status !== null && $status !== '' && $st !== $status) {
                continue;
            }
            $out[] = $r;
        }

        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    public static function obter(int $id): array
    {
        $row = PocRegistro::buscar($id);
        if ($row === null || $row['tipo'] !== self::TIPO) {
            throw new \RuntimeException("Alerta #{$id} não encontrado.");
        }
        $p = $row['payload'];
        $p['id'] = (int) $row['id'];

        return $p;
    }

    public static function atualizarStatus(int $id, string $status): void
    {
        if ($status === self::STATUS_ATENDIDO) {
            throw new \RuntimeException(
                'O alerta não pode ser marcado como atendido daqui. Registre a compra e confira a entrada do item no depósito.'
            );
        }
        if (! in_array($status, [self::STATUS_ABERTO, self::STATUS_EM_COMPRA, self::STATUS_DESCARTADO], true)) {
            throw new \RuntimeException('Status de alerta inválido.');
        }

        $p = self::obter($id);
        if (($p['status'] ?? '') === self::STATUS_ATENDIDO) {
            throw new \RuntimeException('Alerta já concluído pela entrada conferida. O pedido gerado segue o fluxo de separação.');
        }
        $p['status'] = $status;
        $p['atualizado_em'] = now()->toDateTimeString();
        self::persistir($p);
    }

    /** Grava a entrada conferida sem encerrar o alerta — o pedido ainda será gerado. */
    public static function registrarEntradaConferida(int $id, int $movimentoId): array
    {
        $p = self::obter($id);
        if (($p['status'] ?? '') === self::STATUS_DESCARTADO) {
            throw new \RuntimeException('Alerta descartado. Reabra antes de conferir a entrada.');
        }
        if (! empty($p['pedido_gerado_id'])) {
            return $p;
        }
        if (empty($p['entrada_movimento_id'])) {
            $p['entrada_movimento_id'] = $movimentoId;
            $p['entrada_em'] = now()->toDateTimeString();
        }
        $p['status'] = self::STATUS_EM_COMPRA;
        $p['atualizado_em'] = now()->toDateTimeString();
        self::persistir($p);

        return self::obter($id);
    }

    public static function vincularPedidoGerado(int $id, int $pedidoId): void
    {
        $p = self::obter($id);
        $p['pedido_gerado_id'] = $pedidoId;
        $p['status'] = self::STATUS_ATENDIDO;
        $p['atendido_em'] = now()->toDateTimeString();
        $p['atendido_via'] = 'entrada';
        $p['atualizado_em'] = now()->toDateTimeString();
        self::persistir($p);
    }

    /** @param array<string,mixed> $p */
    private static function persistir(array $p): void
    {
        $id = (int) ($p['id'] ?? 0);
        unset($p['id']);
        PocRegistro::atualizar($id, $p, (string) ($p['titulo'] ?? 'Alerta'), (string) ($p['status'] ?? ''));
    }

    public static function rotuloMotivo(string $motivo): string
    {
        return match ($motivo) {
            'sem_saldo' => 'Sem saldo',
            'cobertura' => 'Falta de cobertura',
            'fora_catalogo' => 'Não cadastrado',
            default => $motivo,
        };
    }
}
