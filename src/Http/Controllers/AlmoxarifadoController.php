<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Http\Controllers;

use iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\MovimentoEstoque;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PocRegistro;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\PermissoesAlmoxarifado;
use iEducar\Packages\BuritiLagunaAmbiente\Support\AuditoriaFluxo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class AlmoxarifadoController extends Controller
{
    public function index(): View
    {
        $itens = $this->carregarItens();
        $criticosLista = array_values(array_filter($itens, fn ($i) => $this->critico($i)));
        $criticos = count($criticosLista);
        $saldoTotal = array_sum(array_map(static fn ($i) => (int) ($i['saldo'] ?? 0), $itens));
        $abertos = 0;
        $entregues = 0;
        $pedidosRecentes = [];
        foreach (PocRegistro::listar('pedido', 500) as $r) {
            $st = (string) (($r['payload']['status'] ?? '') ?: '');
            if (in_array($st, [PedidoCarrinho::STATUS_ENTREGUE, PedidoCarrinho::STATUS_CONFERIDO, 'Entregue'], true)) {
                $entregues++;
            } else {
                $abertos++;
                $pedidosRecentes[] = $r;
            }
        }
        $movimentos = PocRegistro::contar('movimento');

        $analise = [];
        foreach (array_slice($criticosLista, 0, 8) as $i) {
            $analise[] = [
                'titulo' => $i['titulo'],
                'meta' => 'SKU '.$i['sku'].' · mínimo '.$i['minimo'],
                'valor' => $i['saldo'].' un.',
                'badge' => 'Crítico',
                'badge_tom' => 'fail',
                'url' => '/almoxarifado-escolar/saldos',
            ];
        }
        foreach (array_slice($pedidosRecentes, 0, 5) as $r) {
            $p = $r['payload'];
            $analise[] = [
                'titulo' => 'Pedido #'.$r['id'],
                'meta' => ($p['escola'] ?? $r['titulo'] ?? 'Escola').' · '.($p['status'] ?? 'aberto'),
                'valor' => count($p['itens'] ?? []).' item(ns)',
                'badge' => (string) ($p['status'] ?? 'aberto'),
                'badge_tom' => 'warn',
                'url' => '/almoxarifado-escolar/pedidos',
            ];
        }
        if ($analise === []) {
            $analise[] = [
                'titulo' => 'Estoque estável',
                'meta' => 'Nenhum saldo crítico nem pedido pendente',
                'badge' => 'OK',
                'badge_tom' => 'ok',
                'valor' => (string) $saldoTotal,
            ];
        }

        return view('almoxarifado::painel', [
            'kpis' => [
                ['rotulo' => 'Itens', 'valor' => count($itens), 'icone' => 'fa-cubes', 'tom' => 'var(--cor)'],
                ['rotulo' => 'Saldo total', 'valor' => $saldoTotal, 'icone' => 'fa-database', 'tom' => 'var(--destaque)'],
                ['rotulo' => 'Saldos críticos', 'valor' => $criticos, 'icone' => 'fa-exclamation-triangle', 'tom' => $criticos ? 'var(--erro)' : 'var(--ok)'],
                ['rotulo' => 'Pedidos abertos', 'valor' => $abertos, 'icone' => 'fa-truck', 'tom' => $abertos ? 'var(--ambar)' : 'var(--ok)'],
                ['rotulo' => 'Entregues', 'valor' => $entregues, 'icone' => 'fa-check', 'tom' => 'var(--ok)'],
                ['rotulo' => 'Movimentos', 'valor' => $movimentos, 'icone' => 'fa-exchange', 'tom' => '#0e7490'],
            ],
            'destaques' => [
                [
                    'titulo' => 'Depósito central',
                    'meta' => 'SME · alerta mínimo '.config('almoxarifado.estoque_minimo_alerta', 1),
                    'icone' => 'fa-cubes',
                    'tom' => $criticos ? 'var(--erro)' : 'var(--cor)',
                    'corpo' => $criticos
                        ? $criticos.' item(ns) abaixo do limite — reposição sugerida.'
                        : 'Nenhum item crítico. '.$abertos.' pedido(s) em andamento.',
                ],
            ],
            'analise' => $analise,
            'analiseTitulo' => 'Críticos e pedidos em aberto',
            'resumo' => 'Almoxarifado escolar · catálogo, pedidos por escola, movimentos de entrada/saída e saldos.',
            'atalhos' => [
                ['url' => '/almoxarifado-escolar/itens', 'icone' => 'fa-cubes', 'rotulo' => '1. Catálogo', 'meta' => 'Itens e mínimo'],
                ['url' => '/almoxarifado-escolar/pedidos', 'icone' => 'fa-shopping-cart', 'rotulo' => '2. Pedido', 'meta' => 'Abrir / carrinho'],
                ['url' => '/almoxarifado-escolar/pedidos', 'icone' => 'fa-check', 'rotulo' => '3. Entregar', 'meta' => 'Registrar e finalizar'],
                ['url' => '/almoxarifado-escolar/movimentos', 'icone' => 'fa-exchange', 'rotulo' => '4. Movimentos', 'meta' => 'Entrada / saída'],
                ['url' => '/almoxarifado-escolar/saldos', 'icone' => 'fa-bar-chart', 'rotulo' => 'Saldos', 'meta' => 'Por escola'],
            ],
            'fluxoTitulo' => 'Fluxo do almoxarife',
        ]);
    }

    public function itens(): View
    {
        $linhas = [];
        foreach ($this->carregarItens() as $i) {
            $critico = $this->critico($i);
            $linhas[] = [
                'titulo' => $i['titulo'],
                'meta' => 'SKU '.$i['sku'],
                'badge' => $i['saldo'].' un.',
                'badge_tom' => $critico ? 'fail' : 'ok',
                'corpo' => 'Mínimo operacional: '.$i['minimo'],
            ];
        }

        return view('almoxarifado::itens', compact('linhas'));
    }

    public function salvarItem(Request $request): RedirectResponse
    {
        if (! PocRegistro::disponivel()) {
            return back()->with('erro', 'Schema almoxarifado.poc_registro ausente.');
        }

        $sku = strtoupper(trim((string) $request->input('sku', '')));
        $titulo = trim((string) $request->input('titulo', ''));
        $saldo = max(0, (int) $request->input('saldo', 0));
        $minimo = max(0, (int) $request->input('minimo', 0));

        if ($sku === '' || $titulo === '') {
            return back()->with('erro', 'SKU e título são obrigatórios.');
        }

        PocRegistro::gravar('item', $titulo, 'SKU '.$sku, [
            'sku' => $sku,
            'titulo' => $titulo,
            'saldo' => $saldo,
            'minimo' => $minimo,
        ]);

        return redirect('/almoxarifado-escolar/itens')->with('ok', "Item {$sku} ({$titulo}) cadastrado.");
    }

    public function saldos(): View
    {
        $linhas = [];
        foreach ($this->carregarItens() as $i) {
            $ok = ! $this->critico($i);
            $linhas[] = [
                'titulo' => $i['titulo'],
                'meta' => 'Saldo '.$i['saldo'].' / mínimo '.$i['minimo'],
                'badge' => $ok ? 'OK' : 'Crítico',
                'badge_tom' => $ok ? 'ok' : 'fail',
                'corpo' => $ok ? 'Dentro da faixa.' : 'Acionar compra / redistribuição.',
            ];
        }

        return view('almoxarifado::saldos', compact('linhas'));
    }

    public function pedidos(Request $request): View
    {
        $this->garantirCatalogo();
        $filtroSolicitanteId = (int) $request->query('solicitante_id', 0);
        $filtroSolicitante = '';
        if ($filtroSolicitanteId > 0) {
            $filtroSolicitante = $this->nomeUsuarioPorId($filtroSolicitanteId) ?? '';
        }
        $linhas = [];
        $carrinhos = [];
        foreach (PocRegistro::listar('pedido', 300) as $r) {
            try {
                $pedido = PedidoCarrinho::obter((int) $r['id']);
            } catch (Throwable) {
                continue;
            }
            $p = $pedido['payload'];
            $solicitante = (string) ($p['solicitante'] ?? '');
            $solicitanteId = (int) ($p['solicitante_id'] ?? 0);
            if ($filtroSolicitanteId > 0) {
                if ($solicitanteId !== $filtroSolicitanteId
                    && ! str_contains(mb_strtolower($solicitante), mb_strtolower($filtroSolicitante))) {
                    continue;
                }
            }
            $qtdItens = count($p['itens'] ?? []);
            $qtdUn = array_sum(array_map(static fn ($i) => (int) ($i['quantidade'] ?? 0), $p['itens'] ?? []));
            $status = (string) ($p['status'] ?? PedidoCarrinho::STATUS_CARRINHO);
            $linha = [
                'id' => (int) $r['id'],
                'titulo' => ($p['escola'] ?? '—').' — '.$qtdItens.' item(ns)',
                'meta' => 'Pedido #'.$r['id'].' · '.$qtdUn.' un.'.($solicitante !== '' ? ' · '.$solicitante : ''),
                'badge' => PedidoCarrinho::rotuloStatus($status),
                'badge_tom' => in_array($status, [PedidoCarrinho::STATUS_ENTREGUE, PedidoCarrinho::STATUS_CONFERIDO], true) ? 'ok'
                    : ($status === PedidoCarrinho::STATUS_CARRINHO ? 'warn' : 'warn'),
                'corpo' => 'Criado em '.($r['created_at'] ?? '—'),
                'status' => $status,
                'itens' => $p['itens'] ?? [],
            ];
            if ($status === PedidoCarrinho::STATUS_CARRINHO) {
                $carrinhos[] = $linha;
            }
            $linhas[] = $linha;
        }

        return view('almoxarifado::pedidos', [
            'linhas' => $linhas,
            'carrinhos' => $carrinhos,
            'escolas' => $this->escolasDaRede(),
            'itens' => $this->carregarItens(),
            'usuarios' => $this->usuariosRede(),
            'filtroSolicitanteId' => $filtroSolicitanteId > 0 ? $filtroSolicitanteId : null,
        ]);
    }

    public function abrirPedido(Request $request): RedirectResponse
    {
        $solicitanteId = (int) $request->input('solicitante_id', 0);
        $solicitante = $solicitanteId > 0 ? $this->nomeUsuarioPorId($solicitanteId) : null;

        try {
            $aberto = PedidoCarrinho::abrir(
                trim((string) $request->input('escola', '')),
                $solicitante,
                $solicitanteId > 0 ? $solicitanteId : null,
            );
        } catch (Throwable $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect('/almoxarifado-escolar/pedidos/'.$aberto['id'])
            ->with('ok', 'Carrinho #'.$aberto['id'].' aberto — adicione os itens.');
    }

    public function verPedido(int $id): View|RedirectResponse
    {
        try {
            $pedido = PedidoCarrinho::obter($id);
        } catch (Throwable $e) {
            return redirect('/almoxarifado-escolar/pedidos')->with('erro', $e->getMessage());
        }

        return view('almoxarifado::pedido-detalhe', [
            'pedido' => $pedido,
            'itensCatalogo' => $this->carregarItens(),
            'etapas' => PedidoCarrinho::ETAPAS_ASSINATURA,
        ]);
    }

    public function adicionarItemPedido(Request $request, int $id): RedirectResponse
    {
        $skuRaw = trim((string) $request->input('item', ''));
        $qtd = max(1, (int) $request->input('quantidade', 1));
        [$sku, $titulo] = $this->resolverItem($skuRaw);

        try {
            PedidoCarrinho::adicionarItem($id, $sku, $titulo, $qtd);
        } catch (Throwable $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect('/almoxarifado-escolar/pedidos/'.$id)->with('ok', "{$qtd}× {$titulo} no carrinho.");
    }

    public function removerItemPedido(Request $request, int $id): RedirectResponse
    {
        try {
            PedidoCarrinho::removerItem($id, (string) $request->input('sku', ''));
        } catch (Throwable $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect('/almoxarifado-escolar/pedidos/'.$id)->with('ok', 'Item removido do carrinho.');
    }

    public function fecharPedido(int $id): RedirectResponse
    {
        try {
            PedidoCarrinho::fechar($id);
        } catch (Throwable $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect('/almoxarifado-escolar/pedidos/'.$id.'/nota')
            ->with('ok', "Pedido #{$id} fechado. Emita a nota e colete as assinaturas.");
    }

    public function notaPedido(int $id): View|RedirectResponse
    {
        try {
            $pedido = PedidoCarrinho::obter($id);
        } catch (Throwable $e) {
            return redirect('/almoxarifado-escolar/pedidos')->with('erro', $e->getMessage());
        }

        $etapas = PedidoCarrinho::ETAPAS_ASSINATURA;
        $p = $pedido['payload'];
        $proxima = null;
        foreach ($etapas as $etapa) {
            if (empty($p['assinaturas'][$etapa])) {
                $proxima = $etapa;
                break;
            }
        }

        $usuario = PermissoesAlmoxarifado::usuarioAtual();
        $podeAssinar = $proxima !== null
            && ($p['status'] ?? '') !== PedidoCarrinho::STATUS_CARRINHO
            && PermissoesAlmoxarifado::podeAssinar($usuario, $proxima);

        return view('almoxarifado::pedido-nota', [
            'pedido' => $pedido,
            'municipio' => (string) (
                config('legacy.config.ieducar_entity_name')
                ?? config('legacy.app.entity.name')
                ?? 'i-Educar'
            ),
            'etapas' => $etapas,
            'proxima' => $proxima,
            'podeAssinar' => $podeAssinar,
            'usuarioLogado' => PermissoesAlmoxarifado::dadosUsuarioLogado(),
            'niveisEtapa' => $proxima ? PermissoesAlmoxarifado::niveisAssinatura($proxima) : [],
        ]);
    }

    public function assinarPedido(Request $request, int $id): RedirectResponse
    {
        $etapa = (string) $request->input('etapa', '');
        $usuario = PermissoesAlmoxarifado::usuarioAtual();

        if (! PermissoesAlmoxarifado::podeAssinar($usuario, $etapa)) {
            return back()->with('erro', 'Seu perfil não tem permissão para assinar «'
                .PedidoCarrinho::rotuloEtapa($etapa)
                .'». Ajuste em config/almoxarifado.php → permissoes.assinaturas.');
        }

        $dados = PermissoesAlmoxarifado::dadosUsuarioLogado();

        try {
            PedidoCarrinho::assinar(
                $id,
                $etapa,
                $dados['nome'],
                trim((string) $request->input('observacao', '')) ?: null,
                $dados['id'],
                $dados['login'],
            );
        } catch (Throwable $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect('/almoxarifado-escolar/pedidos/'.$id.'/nota')
            ->with('ok', PedidoCarrinho::rotuloEtapa($etapa).' registrada por '.$dados['nome'].' em '.now()->format('d/m/Y H:i:s').'.');
    }

    /** @deprecated legado: 1 item por POST — redireciona ao fluxo de carrinho */
    public function salvarPedido(Request $request): RedirectResponse
    {
        $escola = trim((string) $request->input('escola', ''));
        $item = trim((string) $request->input('item', ''));
        $qtd = max(1, (int) $request->input('quantidade', 1));

        try {
            $aberto = PedidoCarrinho::abrir($escola);
            [$sku, $titulo] = $this->resolverItem($item);
            PedidoCarrinho::adicionarItem($aberto['id'], $sku, $titulo, $qtd);
        } catch (Throwable $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect('/almoxarifado-escolar/pedidos/'.$aberto['id'])
            ->with('ok', 'Carrinho criado com o item. Adicione mais ou feche o pedido.');
    }

    public function movimentos(): View
    {
        $this->garantirCatalogo();
        $linhas = [];
        foreach (MovimentoEstoque::listar(200) as $r) {
            $p = $r['payload'];
            $sentido = (string) ($p['sentido'] ?? '');
            $linhas[] = [
                'titulo' => ($p['titulo'] ?? $r['titulo']).' · '.strtoupper((string) ($p['sku'] ?? '')),
                'meta' => ($p['quando'] ?? $r['created_at'] ?? '—').' · origem '.($p['origem'] ?? '—'),
                'badge' => $sentido === MovimentoEstoque::ENTRADA
                    ? '+'.($p['quantidade'] ?? 0)
                    : '−'.($p['quantidade'] ?? 0),
                'badge_tom' => $sentido === MovimentoEstoque::ENTRADA ? 'ok' : 'warn',
                'corpo' => 'Saldo '.$p['saldo_antes'].' → '.$p['saldo_depois']
                    .(empty($p['pedido_id']) ? '' : ' · pedido #'.$p['pedido_id'])
                    .(empty($p['responsavel']) ? '' : ' · '.$p['responsavel']),
            ];
        }

        return view('almoxarifado::movimentos', [
            'linhas' => $linhas,
            'itens' => $this->carregarItens(),
            'usuarios' => $this->usuariosRede(),
            'usuarioLogado' => $this->nomeUsuario(),
            'usuarioLogadoId' => Auth::id(),
        ]);
    }

    public function salvarMovimento(Request $request): RedirectResponse
    {
        $skuRaw = trim((string) $request->input('item', ''));
        [$sku, $titulo] = $this->resolverItem($skuRaw);
        $sentido = (string) $request->input('sentido', MovimentoEstoque::ENTRADA);
        $sentido = $sentido === MovimentoEstoque::SAIDA ? MovimentoEstoque::SAIDA : MovimentoEstoque::ENTRADA;

        // Entrada: responsável sempre = usuário logado (ignora input do cliente).
        if ($sentido === MovimentoEstoque::ENTRADA) {
            $responsavelId = Auth::id() ? (int) Auth::id() : null;
            $responsavel = $this->nomeUsuario();
        } else {
            $responsavelId = (int) $request->input('responsavel_id', 0);
            $responsavel = $responsavelId > 0 ? $this->nomeUsuarioPorId($responsavelId) : null;
            if ($responsavel === null || $responsavel === '') {
                return back()->with('erro', 'Selecione o responsável pela saída na lista.');
            }
        }

        try {
            $res = MovimentoEstoque::registrar(
                $sentido,
                $sku,
                $titulo,
                max(1, (int) $request->input('quantidade', 1)),
                'manual',
                null,
                trim((string) $request->input('observacao', '')) ?: null,
                $responsavel,
                $responsavelId,
            );
        } catch (Throwable $e) {
            return back()->with('erro', $e->getMessage());
        }

        AuditoriaFluxo::registrar(
            static fn (string $t, string $tit, ?string $m, array $pay): int => PocRegistro::gravar($t, $tit, $m, $pay),
            'movimento',
            strtoupper($sentido)." {$sku} · movimento #{$res['id']}",
            (int) $res['id']
        );

        return redirect('/almoxarifado-escolar/movimentos')
            ->with('ok', 'Movimento #'.$res['id'].' gravado. Novo saldo: '.$res['saldo'].' un.');
    }

    /** Persiste catálogo demo uma vez para permitir baixa e movimentos reais. */
    private function garantirCatalogo(): void
    {
        if (! PocRegistro::disponivel() || PocRegistro::contar('item') > 0) {
            return;
        }
        if (! (bool) config('almoxarifado.seed_demo_se_vazio', true)) {
            return;
        }
        foreach ([
            ['sku' => 'CAD-A4', 'titulo' => 'Caderno A4', 'saldo' => 120, 'minimo' => 40],
            ['sku' => 'LAP-HB', 'titulo' => 'Lápis HB', 'saldo' => 18, 'minimo' => 50],
            ['sku' => 'SULFITE', 'titulo' => 'Resma sulfite', 'saldo' => 64, 'minimo' => 20],
            ['sku' => 'GIZ', 'titulo' => 'Giz branco', 'saldo' => 8, 'minimo' => 30],
        ] as $i) {
            PocRegistro::gravar('item', $i['titulo'], 'SKU '.$i['sku'], $i);
        }
    }

    /**
     * @return list<array{sku:string,titulo:string,saldo:int,minimo:int}>
     */
    private function carregarItens(): array
    {
        if (PocRegistro::contar('item') > 0) {
            $out = [];
            foreach (PocRegistro::listar('item', 500) as $r) {
                $p = $r['payload'];
                $out[] = [
                    'sku' => (string) ($p['sku'] ?? $r['meta'] ?? 'SKU'.$r['id']),
                    'titulo' => (string) ($p['titulo'] ?? $r['titulo']),
                    'saldo' => (int) ($p['saldo'] ?? 0),
                    'minimo' => (int) ($p['minimo'] ?? 0),
                ];
            }

            return $out;
        }

        if (! (bool) config('almoxarifado.seed_demo_se_vazio', true)) {
            return [];
        }

        return [
            ['sku' => 'CAD-A4', 'titulo' => 'Caderno A4', 'saldo' => 120, 'minimo' => 40],
            ['sku' => 'LAP-HB', 'titulo' => 'Lápis HB', 'saldo' => 18, 'minimo' => 50],
            ['sku' => 'SULFITE', 'titulo' => 'Resma sulfite', 'saldo' => 64, 'minimo' => 20],
            ['sku' => 'GIZ', 'titulo' => 'Giz branco', 'saldo' => 8, 'minimo' => 30],
        ];
    }

    /** @param array{saldo:int,minimo:int} $item */
    private function critico(array $item): bool
    {
        $fator = (float) config('almoxarifado.estoque_minimo_alerta', 1.0);

        return $item['saldo'] < ($item['minimo'] * $fator);
    }

    /** @return array{0:string,1:string} sku, titulo */
    private function resolverItem(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['', ''];
        }

        if (str_contains($raw, '·')) {
            [$sku, $titulo] = array_map('trim', explode('·', $raw, 2));

            return [strtoupper($sku), $titulo !== '' ? $titulo : $sku];
        }

        foreach ($this->carregarItens() as $i) {
            if (strcasecmp($i['sku'], $raw) === 0 || strcasecmp($i['titulo'], $raw) === 0) {
                return [$i['sku'], $i['titulo']];
            }
        }

        return [strtoupper($raw), $raw];
    }

    /**
     * @return list<array{cod:int|null,nome:string}>
     */
    private function escolasDaRede(): array
    {
        if (! (bool) config('almoxarifado.escolas_do_nucleo', true)) {
            return [['cod' => null, 'nome' => 'EMEF Norte'], ['cod' => null, 'nome' => 'EMEF Sul']];
        }
        if (! Schema::hasTable('pmieducar.escola') || ! Schema::hasTable('cadastro.juridica')) {
            return [['cod' => null, 'nome' => 'EMEF Norte'], ['cod' => null, 'nome' => 'EMEF Sul']];
        }
        try {
            return DB::table('pmieducar.escola')
                ->join('cadastro.juridica', 'cadastro.juridica.idpes', '=', 'pmieducar.escola.ref_idpes')
                ->where('pmieducar.escola.ativo', 1)
                ->orderBy('cadastro.juridica.fantasia')
                ->limit(200)
                ->get(['pmieducar.escola.cod_escola as cod', 'cadastro.juridica.fantasia as nome'])
                ->map(static fn ($r): array => ['cod' => (int) $r->cod, 'nome' => (string) $r->nome])
                ->all();
        } catch (Throwable) {
            return [['cod' => null, 'nome' => 'EMEF Norte']];
        }
    }

    private function nomeUsuario(): string
    {
        $u = Auth::user();

        return $u?->name ?? $u?->login ?? $u?->email ?? 'sistema';
    }

    private function nomeUsuarioPorId(int $id): ?string
    {
        if ($id < 1) {
            return null;
        }
        foreach ($this->usuariosRede() as $u) {
            if ((int) $u['id'] === $id) {
                return (string) $u['nome'];
            }
        }

        try {
            if (Schema::hasTable('cadastro.pessoa')) {
                $nome = DB::table('cadastro.pessoa')->where('idpes', $id)->value('nome');
                if (is_string($nome) && trim($nome) !== '') {
                    return trim($nome);
                }
            }
        } catch (Throwable) {
            //
        }

        return null;
    }

    /**
     * Solicitante / responsável: usuários com acesso a pedidos ou movimentos,
     * no escopo escolar/institucional do ator.
     *
     * @return list<array{id:int,nome:string,login:string,meta?:string}>
     */
    private function usuariosRede(): array
    {
        if (class_exists(\iEducar\Packages\BuritiLagunaAmbiente\Support\UsuariosSelecionaveis::class)) {
            return \iEducar\Packages\BuritiLagunaAmbiente\Support\UsuariosSelecionaveis::paraSelect(
                Auth::user(),
                [
                    \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos::PEDIDOS,
                    \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos::MOVIMENTOS,
                    \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos::PAINEL,
                ],
            );
        }

        if (! Schema::hasTable('pmieducar.usuario') || ! Schema::hasTable('portal.funcionario')) {
            return [];
        }

        try {
            $rows = DB::table('pmieducar.usuario as u')
                ->join('portal.funcionario as f', 'f.ref_cod_pessoa_fj', '=', 'u.cod_usuario')
                ->leftJoin('cadastro.pessoa as p', 'p.idpes', '=', 'u.cod_usuario')
                ->where('u.ativo', 1)
                ->where('f.ativo', 1)
                ->orderBy('p.nome')
                ->orderBy('f.matricula')
                ->limit(400)
                ->get([
                    'u.cod_usuario as id',
                    'p.nome',
                    'f.matricula as login',
                ]);
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $login = (string) ($r->login ?? '');
            $nome = trim((string) ($r->nome ?? ''));
            if ($nome === '') {
                $nome = $login !== '' ? $login : ('Usuário #'.$r->id);
            }
            $out[] = [
                'id' => (int) $r->id,
                'nome' => $nome,
                'login' => $login,
                'meta' => $login,
            ];
        }

        return $out;
    }
}
