<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Http\Controllers;

use iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\AlertaCompra;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\MovimentoEstoque;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PocRegistro;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\QrCodeRemessa;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\SaldoRede;
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
                ['url' => '/almoxarifado-escolar/itens', 'icone' => 'fa-cubes', 'rotulo' => 'Catálogo', 'meta' => 'Itens e mínimo'],
                ['url' => '/almoxarifado-escolar/pedidos', 'icone' => 'fa-shopping-cart', 'rotulo' => 'Pedido', 'meta' => 'Abrir / carrinho'],
                ['url' => '/almoxarifado-escolar/pedidos', 'icone' => 'fa-check', 'rotulo' => 'Entregar', 'meta' => 'Registrar e finalizar'],
                ['url' => '/almoxarifado-escolar/movimentos', 'icone' => 'fa-exchange', 'rotulo' => 'Movimentos', 'meta' => 'Entrada / saída'],
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

    public function saldos(Request $request): View
    {
        $this->garantirCatalogo();
        $escolaFiltro = trim((string) $request->query('escola', ''));
        $busca = trim((string) $request->query('q', ''));
        $fator = (float) config('almoxarifado.estoque_minimo_alerta', 1.0);

        $panorama = SaldoRede::panorama(
            $this->carregarItens(),
            $escolaFiltro !== '' ? $escolaFiltro : null,
            $fator
        );

        if ($busca !== '') {
            $needle = mb_strtolower($busca);
            $panorama['deposito'] = array_values(array_filter(
                $panorama['deposito'],
                static fn (array $i): bool => str_contains(mb_strtolower($i['sku'].' '.$i['titulo']), $needle)
            ));
            $escolasFiltradas = [];
            foreach ($panorama['escolas'] as $esc) {
                $esc['itens'] = array_values(array_filter(
                    $esc['itens'],
                    static fn (array $i): bool => str_contains(mb_strtolower($i['sku'].' '.$i['titulo']), $needle)
                ));
                $esc['skus'] = count($esc['itens']);
                $esc['unidades'] = array_sum(array_map(static fn (array $i): int => (int) $i['quantidade'], $esc['itens']));
                if ($esc['itens'] !== [] || ($esc['pedidos_transito'] ?? 0) > 0) {
                    $escolasFiltradas[] = $esc;
                }
            }
            $panorama['escolas'] = $escolasFiltradas;
        }

        $escolasOpcoes = $this->escolasDaRede();
        foreach ($panorama['escolas'] as $esc) {
            if (! in_array($esc['escola'], $escolasOpcoes, true)) {
                $escolasOpcoes[] = $esc['escola'];
            }
        }
        sort($escolasOpcoes, SORT_NATURAL | SORT_FLAG_CASE);

        return view('almoxarifado::saldos', [
            'panorama' => $panorama,
            'escolaFiltro' => $escolaFiltro,
            'busca' => $busca,
            'escolasOpcoes' => $escolasOpcoes,
        ]);
    }

    public function pedidos(Request $request): View
    {
        $this->garantirCatalogo();
        $filtroSolicitanteId = (int) $request->query('solicitante_id', 0);
        $filtroEscola = trim((string) $request->query('escola', ''));
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
            if ($filtroEscola !== '' && strcasecmp((string) ($p['escola'] ?? ''), $filtroEscola) !== 0) {
                continue;
            }
            if ($filtroSolicitanteId > 0) {
                if ($solicitanteId !== $filtroSolicitanteId
                    && ! str_contains(mb_strtolower($solicitante), mb_strtolower($filtroSolicitante))) {
                    continue;
                }
            }
            $qtdItens = count($p['itens'] ?? []);
            $qtdUn = array_sum(array_map(static fn ($i) => (int) ($i['quantidade'] ?? 0), $p['itens'] ?? []));
            $status = (string) ($p['status'] ?? PedidoCarrinho::STATUS_CARRINHO);
            $setor = (string) ($p['setor'] ?? '');
            $bloqueado = PedidoCarrinho::bloqueiaEntrega($p);
            $naoAtendidos = count($p['itens_nao_atendidos'] ?? []);
            $visual = PedidoCarrinho::visualStatus($status, $p);
            $aguardandoRepo = count(PedidoCarrinho::itensAguardandoReposicao($p));
            $linha = [
                'id' => (int) $r['id'],
                'titulo' => ($p['escola'] ?? '—').($setor !== '' ? ' / '.$setor : '').' — '.$qtdItens.' item(ns)',
                'meta' => 'Pedido #'.$r['id'].' · '.$qtdUn.' un.'.($solicitante !== '' ? ' · '.$solicitante : '')
                    .($naoAtendidos > 0 ? ' · '.$naoAtendidos.' não atendido(s)' : '')
                    .(! empty($p['origem_pedido_id']) ? ' · reposição de #'.$p['origem_pedido_id'] : ''),
                'badge' => $visual['rotulo'],
                'badge_tom' => $visual['tom'],
                'status_icone' => $visual['icone'],
                'status_cor' => $visual['cor'],
                'status_classe' => $visual['classe'],
                'finalizado' => $visual['finalizado'],
                'corpo' => 'Criado em '.$this->formatarQuando($r['created_at'] ?? null)
                    .($bloqueado ? ' · entrega bloqueada até retirar faltosos' : '')
                    .($visual['finalizado'] && $aguardandoRepo > 0
                        ? ' · '.$aguardandoRepo.' item(ns) para pedido de reposição'
                        : ''),
                'status' => $status,
                'itens' => $p['itens'] ?? [],
                'parcial' => ! empty($p['parcial']) || $bloqueado || $naoAtendidos > 0,
            ];
            if ($status === PedidoCarrinho::STATUS_CARRINHO) {
                $carrinhos[] = $linha;
            }
            $linhas[] = $linha;
        }

        $user = PermissoesAlmoxarifado::usuarioAtual();

        return view('almoxarifado::pedidos', [
            'linhas' => $linhas,
            'carrinhos' => $carrinhos,
            'escolas' => $this->escolasDaRede(),
            'itens' => $this->carregarItens(),
            // Solicitantes: só quem pode pedir (carrinho/pedidos), com vínculo de escola no data-escolas.
            'usuarios' => $this->usuariosSolicitantes(),
            'setores' => (array) config('almoxarifado.setores', []),
            'podeCarrinhoSetor' => PermissoesAlmoxarifado::podeCarrinhoSetor($user),
            'filtroSolicitanteId' => $filtroSolicitanteId > 0 ? $filtroSolicitanteId : null,
        ]);
    }

    public function abrirPedido(Request $request): RedirectResponse
    {
        $user = PermissoesAlmoxarifado::usuarioAtual();
        if (! PermissoesAlmoxarifado::podeCarrinhoSetor($user)) {
            return back()->with('erro', 'Sem permissão para abrir carrinho por setor. Peça liberação do processo «Carrinho por setor» (cadastra) ou ALMOXARIFADO_CARRINHO_SETOR=true.');
        }

        $setor = trim((string) $request->input('setor', ''));
        if ($setor === '') {
            return back()->withInput()->with('erro', 'Informe o setor solicitante.');
        }

        $escola = trim((string) $request->input('escola', ''));
        if ($escola === '') {
            return back()->withInput()->with('erro', 'Informe a escola / unidade.');
        }

        $solicitanteId = (int) $request->input('solicitante_id', 0);
        if ($solicitanteId > 0 && ! $this->usuarioPertenceEscola($solicitanteId, $escola)) {
            return back()->withInput()->with('erro', 'O solicitante não está vinculado à escola selecionada. Escolha alguém da unidade.');
        }
        if ($solicitanteId <= 0) {
            $dados = PermissoesAlmoxarifado::dadosUsuarioLogado();
            $solicitanteId = (int) ($dados['id'] ?? 0);
            $solicitante = $dados['nome'];
            // Logado só entra como solicitante se pertencer à escola (ou for admin sem vínculo obrigatório).
            if ($solicitanteId > 0 && ! $this->usuarioPertenceEscola($solicitanteId, $escola)
                && ! $this->atorPodeSolicitarSemVinculo($user)) {
                return back()->withInput()->with('erro', 'Selecione um solicitante vinculado à escola «'.$escola.'».');
            }
        } else {
            $solicitante = $this->nomeUsuarioPorId($solicitanteId);
        }

        try {
            $aberto = PedidoCarrinho::abrir(
                $escola,
                $solicitante,
                $solicitanteId > 0 ? $solicitanteId : null,
                $setor,
            );
        } catch (Throwable $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect('/almoxarifado-escolar/pedidos/'.$aberto['id'])
            ->with('ok', 'Carrinho #'.$aberto['id'].' aberto para o setor '.$setor.' — adicione itens disponíveis.');
    }

    public function verPedido(int $id): View|RedirectResponse
    {
        try {
            $pedido = PedidoCarrinho::obter($id);
        } catch (Throwable $e) {
            return redirect('/almoxarifado-escolar/pedidos')->with('erro', $e->getMessage());
        }

        $catalogo = $this->carregarItens();
        $disponiveis = array_values(array_filter($catalogo, static fn (array $i): bool => (int) ($i['saldo'] ?? 0) > 0));
        $user = PermissoesAlmoxarifado::usuarioAtual();

        $payload = $pedido['payload'];
        $visual = PedidoCarrinho::visualStatus((string) ($payload['status'] ?? ''), $payload);
        $semCobertura = PedidoCarrinho::itensSemCobertura($payload);

        return view('almoxarifado::pedido-detalhe', [
            'pedido' => $pedido,
            'itensCatalogo' => $disponiveis,
            'itensTodos' => $catalogo,
            'etapas' => PedidoCarrinho::ETAPAS_ASSINATURA,
            'podeForaCatalogo' => PermissoesAlmoxarifado::podePedidoForaCatalogo($user),
            'faltosos' => PedidoCarrinho::itensFaltosos($payload),
            'bloqueioEntrega' => PedidoCarrinho::bloqueiaEntrega($payload),
            'podeRetirarFaltosos' => PermissoesAlmoxarifado::podeRetirarFaltosos($user),
            'finalizado' => PedidoCarrinho::finalizado($payload),
            'podeEmitirRemessa' => PedidoCarrinho::podeEmitirRemessa($payload),
            'aguardandoReposicao' => PedidoCarrinho::itensAguardandoReposicao($payload),
            'podeGerarReposicao' => PedidoCarrinho::finalizado($payload)
                && PedidoCarrinho::itensAguardandoReposicao($payload) !== []
                && PermissoesAlmoxarifado::podeRetirarFaltosos($user),
            'visual' => $visual,
            'semCobertura' => $semCobertura,
            'podeResolverCobertura' => $this->podeResolverCobertura($user),
        ]);
    }

    public function resolverCobertura(Request $request, int $id): RedirectResponse
    {
        $user = PermissoesAlmoxarifado::usuarioAtual();
        if (! $this->podeResolverCobertura($user)) {
            return back()->with('erro', 'Sem permissão para ajustar a cobertura do pedido. Libere «Retirar itens faltosos» ou a assinatura de entrega.');
        }

        $dados = PermissoesAlmoxarifado::dadosUsuarioLogado();

        try {
            $resultado = PedidoCarrinho::resolverCobertura(
                $id,
                (string) $request->input('sku', ''),
                (string) $request->input('modo', 'atender_disponivel'),
                $dados['nome'],
                trim((string) $request->input('observacao', '')) ?: null,
                $dados['id'],
            );
        } catch (Throwable $e) {
            return back()->with('erro', $e->getMessage());
        }

        $msg = $resultado['mensagem'].' Alerta de compra #'.$resultado['alerta_id'].'.';
        if ($resultado['liberado']) {
            $msg .= ' A entrega dos itens restantes está liberada.';
        }

        return back()->with('ok', $msg);
    }

    public function gerarPedidoReposicao(int $id): RedirectResponse
    {
        $user = PermissoesAlmoxarifado::usuarioAtual();
        if (! PermissoesAlmoxarifado::podeRetirarFaltosos($user)) {
            return back()->with('erro', 'Sem permissão para gerar pedido de reposição a partir de itens não atendidos.');
        }

        $dados = PermissoesAlmoxarifado::dadosUsuarioLogado();

        try {
            $novo = PedidoCarrinho::gerarPedidoReposicao($id, $dados['nome'], $dados['id']);
        } catch (Throwable $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect('/almoxarifado-escolar/pedidos/'.$novo['id'])
            ->with('ok', 'Pedido de reposição #'.$novo['id'].' aberto com '.$novo['itens'].' item(ns) do pedido #'.$id.'. Confira o saldo e feche quando a compra entrar no estoque.');
    }

    public function retirarFaltoso(Request $request, int $id): RedirectResponse
    {
        $user = PermissoesAlmoxarifado::usuarioAtual();
        if (! PermissoesAlmoxarifado::podeRetirarFaltosos($user)) {
            return back()->with('erro', 'Sem permissão para retirar itens faltosos. Libere o processo «Retirar itens faltosos» ou ALMOXARIFADO_RETIRAR_FALTOSOS=true (níveis admin/institucional).');
        }

        $dados = PermissoesAlmoxarifado::dadosUsuarioLogado();
        $sku = (string) $request->input('sku', '');

        try {
            PedidoCarrinho::retirarFaltoso(
                $id,
                $sku,
                $dados['nome'],
                trim((string) $request->input('observacao', '')) ?: null,
                $dados['id'],
            );
        } catch (Throwable $e) {
            return back()->with('erro', $e->getMessage());
        }

        $pedido = PedidoCarrinho::obter($id);
        $ainda = PedidoCarrinho::bloqueiaEntrega($pedido['payload']);
        $msg = $ainda
            ? 'Item marcado como não atendido. Ainda há faltosos — entrega permanece bloqueada.'
            : 'Item marcado como não atendido. Pedido parcial liberado para entrega/conferência.';

        return redirect('/almoxarifado-escolar/pedidos/'.$id)->with('ok', $msg);
    }

    public function adicionarItemPedido(Request $request, int $id): RedirectResponse
    {
        $modo = (string) $request->input('modo', 'catalogo'); // catalogo|fora_catalogo
        $qtd = max(1, (int) $request->input('quantidade', 1));
        $user = PermissoesAlmoxarifado::usuarioAtual();

        try {
            $pedido = PedidoCarrinho::obter($id);
        } catch (Throwable $e) {
            return back()->with('erro', $e->getMessage());
        }

        if ($modo === 'fora_catalogo') {
            if (! PermissoesAlmoxarifado::podePedidoForaCatalogo($user)) {
                return back()->with('erro', 'Sem permissão para pedir item fora do catálogo. Libere «Pedido fora do catálogo» (cadastra) ou ALMOXARIFADO_PEDIDO_FORA_CATALOGO=true.');
            }
            $titulo = trim((string) $request->input('titulo_livre', $request->input('item', '')));
            $skuLivre = strtoupper(trim((string) $request->input('sku_livre', '')));
            if ($titulo === '') {
                return back()->with('erro', 'Descreva o item não cadastrado.');
            }
            // Se o texto bater com um item do catálogo, bloqueia — evita digitação errada.
            foreach ($this->carregarItens() as $i) {
                if (strcasecmp($i['sku'], $titulo) === 0
                    || strcasecmp($i['titulo'], $titulo) === 0
                    || ($skuLivre !== '' && strcasecmp($i['sku'], $skuLivre) === 0)) {
                    return back()->with('erro', 'Este item já existe no catálogo ('.$i['sku'].'). Use a lista de itens disponíveis.');
                }
            }
            $sku = $skuLivre !== '' ? $skuLivre : 'NOVO';
            try {
                $alertaId = AlertaCompra::registrar([
                    'sku' => $sku,
                    'titulo' => $titulo,
                    'quantidade' => $qtd,
                    'motivo' => 'fora_catalogo',
                    'escola' => (string) ($pedido['payload']['escola'] ?? ''),
                    'setor' => $pedido['payload']['setor'] ?? null,
                    'pedido_id' => $id,
                    'solicitante' => $pedido['payload']['solicitante'] ?? null,
                    'solicitante_id' => $pedido['payload']['solicitante_id'] ?? null,
                ]);
                PedidoCarrinho::adicionarItem($id, $sku, $titulo, $qtd, 'fora_catalogo', $alertaId);
            } catch (Throwable $e) {
                return back()->with('erro', $e->getMessage());
            }

            return redirect('/almoxarifado-escolar/pedidos/'.$id)
                ->with('ok', "{$qtd}× {$titulo} incluído (fora do catálogo). Alerta de compra #{$alertaId} gerado.");
        }

        $skuRaw = trim((string) $request->input('item', ''));
        [$sku, $titulo] = $this->resolverItem($skuRaw);
        if ($sku === '' || $titulo === '') {
            return back()->with('erro', 'Selecione um item do catálogo.');
        }

        $catalogo = collect($this->carregarItens())->first(
            static fn (array $i): bool => strcasecmp($i['sku'], $sku) === 0
        );
        if ($catalogo === null) {
            if (! PermissoesAlmoxarifado::podePedidoForaCatalogo($user)) {
                return back()->with('erro', 'Item não cadastrado. Só quem tem permissão especial pode pedir fora do catálogo.');
            }

            return back()->with('erro', 'Item não encontrado no catálogo. Use «Item não cadastrado» com a permissão especial.');
        }

        $saldo = (int) ($catalogo['saldo'] ?? 0);
        if ($saldo <= 0) {
            if (! PermissoesAlmoxarifado::podePedidoForaCatalogo($user)) {
                return back()->with('erro', "Item «{$titulo}» sem saldo disponível. Sem permissão para gerar alerta de compra.");
            }
            try {
                $alertaId = AlertaCompra::registrar([
                    'sku' => $sku,
                    'titulo' => $titulo,
                    'quantidade' => $qtd,
                    'motivo' => 'sem_saldo',
                    'escola' => (string) ($pedido['payload']['escola'] ?? ''),
                    'setor' => $pedido['payload']['setor'] ?? null,
                    'pedido_id' => $id,
                    'solicitante' => $pedido['payload']['solicitante'] ?? null,
                    'solicitante_id' => $pedido['payload']['solicitante_id'] ?? null,
                ]);
                PedidoCarrinho::adicionarItem($id, $sku, $titulo, $qtd, 'sem_saldo', $alertaId);
            } catch (Throwable $e) {
                return back()->with('erro', $e->getMessage());
            }

            return redirect('/almoxarifado-escolar/pedidos/'.$id)
                ->with('ok', "{$qtd}× {$titulo} sem saldo — alerta de compra #{$alertaId} gerado.");
        }

        if ($qtd > $saldo) {
            return back()->with('erro', "Saldo insuficiente de {$titulo}: disponível {$saldo}.");
        }

        try {
            PedidoCarrinho::adicionarItem($id, $sku, $titulo, $qtd, 'catalogo');
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

    public function qrPedido(int $id): View|RedirectResponse
    {
        try {
            $pedido = PedidoCarrinho::obter($id);
        } catch (Throwable $e) {
            return redirect('/almoxarifado-escolar/pedidos')->with('erro', $e->getMessage());
        }

        $p = $pedido['payload'];
        if (! PedidoCarrinho::podeEmitirRemessa($p)) {
            $msg = PedidoCarrinho::finalizado($p)
                ? 'Pedido conferido e finalizado — QR e etiquetas não são mais emitidos. Use a nota apenas para consulta.'
                : 'Feche o pedido antes de gerar o QR Code de recebimento.';

            return redirect('/almoxarifado-escolar/pedidos/'.$id)->with('erro', $msg);
        }

        try {
            $emit = QrCodeRemessa::emitir($id);
        } catch (Throwable $e) {
            return redirect('/almoxarifado-escolar/pedidos/'.$id)->with('erro', $e->getMessage());
        }

        return view('almoxarifado::pedido-qr', [
            'pedido' => $pedido,
            'emit' => $emit,
        ]);
    }

    public function etiquetasPedido(int $id): View|RedirectResponse
    {
        try {
            $pedido = PedidoCarrinho::obter($id);
        } catch (Throwable $e) {
            return redirect('/almoxarifado-escolar/pedidos')->with('erro', $e->getMessage());
        }

        $p = $pedido['payload'];
        if (! PedidoCarrinho::podeEmitirRemessa($p)) {
            $msg = PedidoCarrinho::finalizado($p)
                ? 'Pedido conferido e finalizado — etiquetas não são mais emitidas. Tratativa de não atendidos gera um novo pedido de reposição.'
                : 'Feche o pedido antes de emitir etiquetas.';

            return redirect('/almoxarifado-escolar/pedidos/'.$id)->with('erro', $msg);
        }

        try {
            $emit = QrCodeRemessa::emitir($id);
        } catch (Throwable $e) {
            return redirect('/almoxarifado-escolar/pedidos/'.$id)->with('erro', $e->getMessage());
        }

        return view('almoxarifado::pedido-etiquetas', [
            'pedido' => $pedido,
            'emit' => $emit,
        ]);
    }

    public function confirmarRecebimento(string $token): RedirectResponse
    {
        $pedidoId = QrCodeRemessa::validarToken($token);
        if ($pedidoId) {
            try {
                $pedido = PedidoCarrinho::obter($pedidoId);
                $pp = $pedido['payload'];
                if (PedidoCarrinho::finalizado($pp)) {
                    return redirect('/almoxarifado-escolar/pedidos/'.$pedidoId)
                        ->with('erro', 'Pedido já conferido e finalizado — confirmação de recebimento encerrada.');
                }
                if (PedidoCarrinho::bloqueiaEntrega($pp)) {
                    return redirect('/almoxarifado-escolar/pedidos/'.$pedidoId)
                        ->with('erro', 'Pedido parcial: há itens faltosos. O almoxarifado precisa retirá-los antes da confirmação de recebimento.');
                }
            } catch (Throwable) {
                // segue para validação padrão do token
            }
        }
        $ok = QrCodeRemessa::confirmarRecebimento($token, Auth::id() ? (int) Auth::id() : null);
        if (! $ok) {
            return redirect('/almoxarifado-escolar/pedidos')
                ->with('erro', 'Token inválido, expirado, pedido bloqueado (parcial) ou não encontrado.');
        }

        $destino = $pedidoId
            ? '/almoxarifado-escolar/pedidos/'.$pedidoId.'/nota'
            : '/almoxarifado-escolar/pedidos';

        return redirect($destino)->with('ok', 'Recebimento confirmado via QR Code.');
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
        $bloqueio = PedidoCarrinho::bloqueiaEntrega($p);
        $semCobertura = PedidoCarrinho::itensSemCobertura($p);
        $finalizado = PedidoCarrinho::finalizado($p);
        $etapaBloqueada = $proxima !== null
            && in_array($proxima, ['entrega', 'conferencia'], true)
            && ($bloqueio || $semCobertura !== []);
        $podeAssinar = $proxima !== null
            && ($p['status'] ?? '') !== PedidoCarrinho::STATUS_CARRINHO
            && ! $finalizado
            && ! $etapaBloqueada
            && PermissoesAlmoxarifado::podeAssinar($usuario, $proxima);
        $visual = PedidoCarrinho::visualStatus((string) ($p['status'] ?? ''), $p);

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
            'bloqueioEntrega' => $bloqueio,
            'etapaBloqueada' => $etapaBloqueada,
            'faltosos' => PedidoCarrinho::itensFaltosos($p),
            'semCobertura' => $semCobertura,
            'podeResolverCobertura' => $this->podeResolverCobertura($usuario),
            'podeRetirarFaltosos' => PermissoesAlmoxarifado::podeRetirarFaltosos($usuario),
            'finalizado' => $finalizado,
            'podeEmitirRemessa' => PedidoCarrinho::podeEmitirRemessa($p),
            'visual' => $visual,
        ]);
    }

    public function exportarNotaPedido(int $id): \Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        if (! class_exists(\iEducar\Packages\BuritiExportacaoUnificada\Services\EmissorDocumento::class)) {
            return redirect('/almoxarifado-escolar/pedidos/'.$id.'/nota')
                ->with('erro', 'Módulo de exportação unificada não está instalado.');
        }

        try {
            $pedido = PedidoCarrinho::obter($id);
        } catch (Throwable $e) {
            return redirect('/almoxarifado-escolar/pedidos')->with('erro', $e->getMessage());
        }

        $p = $pedido['payload'];
        $status = (string) ($p['status'] ?? 'fechado');
        $escola = (string) ($p['escola'] ?? '—');
        $cabecalhos = ['#', 'SKU', 'Item', 'Qtd', 'Situação'];
        $linhas = [];
        foreach (array_values($p['itens'] ?? []) as $i => $item) {
            $origem = (string) ($item['origem'] ?? 'catalogo');
            $situacao = in_array($origem, ['sem_saldo', 'fora_catalogo'], true) ? 'Faltoso' : 'Atendido';
            $linhas[] = [
                (string) ($i + 1),
                (string) ($item['sku'] ?? ''),
                (string) ($item['titulo'] ?? ''),
                (string) ($item['quantidade'] ?? 1),
                $situacao,
            ];
        }
        foreach (array_values($p['itens_nao_atendidos'] ?? []) as $i => $item) {
            $linhas[] = [
                'N'.($i + 1),
                (string) ($item['sku'] ?? ''),
                (string) ($item['titulo'] ?? ''),
                (string) ($item['quantidade'] ?? 1),
                'Não atendido',
            ];
        }

        $assinaturas = [];
        foreach (PedidoCarrinho::ETAPAS_ASSINATURA as $etapa) {
            $a = is_array($p['assinaturas'][$etapa] ?? null) ? $p['assinaturas'][$etapa] : null;
            $assinaturas[] = [
                'cargo' => PedidoCarrinho::rotuloEtapa($etapa),
                'nome' => (string) ($a['responsavel'] ?? ''),
                'quando' => isset($a['quando']) ? $this->formatarQuando($a['quando'], false) : null,
            ];
        }

        $subtitulo = 'Pedido #'.$id
            .' · Escola: '.$escola
            .' · Status: '.PedidoCarrinho::rotuloStatus($status, $p)
            .(! empty($p['fechado_em']) ? ' · Fechado em '.$this->formatarQuando($p['fechado_em'], false) : '');

        return \iEducar\Packages\BuritiExportacaoUnificada\Support\ExportacaoModulo::download(
            'Nota de material — Almoxarifado',
            $cabecalhos,
            $linhas,
            'Almoxarifado escolar',
            'pdf',
            $subtitulo,
            $this->nomeUsuario(),
            'almox-nota-'.$id,
            true,
            $assinaturas,
        );
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

    public function alertas(Request $request): View
    {
        $status = trim((string) $request->query('status', 'aberto'));

        return view('almoxarifado::alertas', [
            'alertas' => AlertaCompra::listar(150, $status !== 'todos' ? $status : null),
            'status' => $status,
            'podeGerir' => PermissoesAlmoxarifado::podeAssinar(
                PermissoesAlmoxarifado::usuarioAtual(),
                'separacao'
            ),
        ]);
    }

    public function atualizarAlerta(Request $request, int $id): RedirectResponse
    {
        if (! PermissoesAlmoxarifado::podeAssinar(PermissoesAlmoxarifado::usuarioAtual(), 'separacao')) {
            return back()->with('erro', 'Sem permissão para gerir alertas de compra.');
        }
        $status = (string) $request->input('status', AlertaCompra::STATUS_EM_COMPRA);
        if ($status === AlertaCompra::STATUS_ATENDIDO) {
            return back()->with('erro', 'O alerta não pode ser marcado como atendido daqui. Registre a compra e confira a entrada do item.');
        }
        try {
            AlertaCompra::atualizarStatus($id, $status);
        } catch (Throwable $e) {
            return back()->with('erro', $e->getMessage());
        }

        if ($status === AlertaCompra::STATUS_EM_COMPRA) {
            return redirect('/almoxarifado-escolar/movimentos?alerta='.$id)
                ->with('ok', 'Compra do alerta #'.$id.' registrada. Confira a entrada do item para gerar o pedido.');
        }

        return redirect('/almoxarifado-escolar/alertas')->with('ok', "Alerta #{$id} atualizado para {$status}.");
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

    public function movimentos(Request $request): View|RedirectResponse
    {
        $this->garantirCatalogo();
        $linhas = [];
        foreach (MovimentoEstoque::listar(200) as $r) {
            $p = $r['payload'];
            $sentido = (string) ($p['sentido'] ?? '');
            $sku = strtoupper((string) ($p['sku'] ?? ''));
            $titulo = (string) ($p['titulo'] ?? $r['titulo'] ?? $sku);
            $linhas[] = [
                'titulo' => $titulo.($sku !== '' ? ' · '.$sku : ''),
                'quando' => $this->formatarQuando($p['quando'] ?? $r['created_at'] ?? null),
                'badge' => $sentido === MovimentoEstoque::ENTRADA
                    ? '+'.($p['quantidade'] ?? 0)
                    : '−'.($p['quantidade'] ?? 0),
                'badge_tom' => $sentido === MovimentoEstoque::ENTRADA ? 'ok' : 'warn',
                'saldo_txt' => ($p['saldo_antes'] ?? '—').' → '.($p['saldo_depois'] ?? '—'),
                'responsavel' => (string) ($p['responsavel'] ?? '—'),
                'origem' => (string) ($p['origem'] ?? '—'),
                'pedido_id' => isset($p['pedido_id']) ? (int) $p['pedido_id'] : null,
                'obs' => trim((string) ($p['observacao'] ?? $p['obs'] ?? '')),
            ];
        }

        $alertaEntrada = null;
        $alertaId = (int) $request->query('alerta', 0);
        if ($alertaId > 0) {
            try {
                $alertaEntrada = AlertaCompra::obter($alertaId);
            } catch (Throwable $e) {
                return redirect('/almoxarifado-escolar/alertas')->with('erro', $e->getMessage());
            }
            if (! empty($alertaEntrada['pedido_gerado_id'])) {
                return redirect('/almoxarifado-escolar/pedidos/'.$alertaEntrada['pedido_gerado_id'].'/nota')
                    ->with('ok', 'A entrada deste alerta já gerou o pedido #'.$alertaEntrada['pedido_gerado_id'].'.');
            }
        }

        return view('almoxarifado::movimentos', [
            'linhas' => $linhas,
            'itens' => $this->carregarItens(),
            'usuarios' => $this->usuariosRede(),
            'usuarioLogado' => $this->nomeUsuario(),
            'usuarioLogadoId' => Auth::id(),
            'podeExportar' => class_exists(\iEducar\Packages\BuritiExportacaoUnificada\Services\EmissorDocumento::class),
            'alertaEntrada' => $alertaEntrada,
        ]);
    }

    public function exportarMovimentos(Request $request): \Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        if (! class_exists(\iEducar\Packages\BuritiExportacaoUnificada\Services\EmissorDocumento::class)) {
            return back()->with('erro', 'Módulo de exportação unificada não está instalado.');
        }

        $formato = strtolower(trim((string) $request->input('formato', 'pdf')));
        $cabecalhos = ['Quando', 'Item', 'Qtd', 'Saldo', 'Responsável', 'Origem'];
        $linhas = [];
        foreach (MovimentoEstoque::listar(500) as $r) {
            $p = $r['payload'];
            $sentido = (string) ($p['sentido'] ?? '');
            $qtd = ($sentido === MovimentoEstoque::ENTRADA ? '+' : '−').($p['quantidade'] ?? 0);
            $linhas[] = [
                $this->formatarQuando($p['quando'] ?? $r['created_at'] ?? null, false),
                (string) ($p['titulo'] ?? $r['titulo'] ?? '').' · '.strtoupper((string) ($p['sku'] ?? '')),
                $qtd,
                ($p['saldo_antes'] ?? '—').' → '.($p['saldo_depois'] ?? '—'),
                (string) ($p['responsavel'] ?? ''),
                (string) ($p['origem'] ?? ''),
            ];
        }

        return \iEducar\Packages\BuritiExportacaoUnificada\Support\ExportacaoModulo::download(
            'Entrada e saída de itens — Almoxarifado',
            $cabecalhos,
            $linhas,
            'Almoxarifado',
            $formato,
            'Controle de materiais · exportação unificada',
            $this->nomeUsuario(),
            'almox-movimentos',
        );
    }

    public function salvarMovimento(Request $request): RedirectResponse
    {
        $skuRaw = trim((string) $request->input('item', ''));
        [$sku, $titulo] = $this->resolverItem($skuRaw);
        $sentido = (string) $request->input('sentido', MovimentoEstoque::ENTRADA);
        $sentido = $sentido === MovimentoEstoque::SAIDA ? MovimentoEstoque::SAIDA : MovimentoEstoque::ENTRADA;
        $alertaId = (int) $request->input('alerta_id', 0);
        $alerta = null;
        if ($alertaId > 0) {
            try {
                $alerta = AlertaCompra::obter($alertaId);
            } catch (Throwable $e) {
                return back()->with('erro', $e->getMessage());
            }
            if ($sentido !== MovimentoEstoque::ENTRADA) {
                return back()->with('erro', 'A compra do alerta só se confirma com entrada no depósito.');
            }
            if (! empty($alerta['pedido_gerado_id'])) {
                return redirect('/almoxarifado-escolar/pedidos/'.$alerta['pedido_gerado_id'].'/nota')
                    ->with('ok', 'Esta compra já gerou o pedido #'.$alerta['pedido_gerado_id'].'.');
            }
            $skuAlerta = strtoupper((string) ($alerta['sku'] ?? ''));
            $qtdAlerta = max(1, (int) ($alerta['quantidade'] ?? 1));
            if ($skuAlerta !== '' && strcasecmp($sku, $skuAlerta) !== 0) {
                return back()->with('erro', 'A entrada precisa ser do item do alerta ('.$skuAlerta.').');
            }
            if ((int) $request->input('quantidade', 1) < $qtdAlerta && empty($alerta['entrada_movimento_id'])) {
                return back()->with('erro', 'Confira pelo menos '.$qtdAlerta.' un. — quantidade comprada no alerta #'.$alertaId.'.');
            }
        }

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

        $qtdMov = max(1, (int) $request->input('quantidade', 1));
        $res = null;
        if ($alerta === null || empty($alerta['entrada_movimento_id'])) {
            try {
                $res = MovimentoEstoque::registrar(
                    $sentido,
                    $sku,
                    $titulo,
                    $qtdMov,
                    $alerta !== null ? 'compra_alerta' : 'manual',
                    isset($alerta['pedido_id']) ? (int) $alerta['pedido_id'] : null,
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
        }

        if ($alerta !== null) {
            try {
                if ($res !== null) {
                    $alerta = AlertaCompra::registrarEntradaConferida($alertaId, (int) $res['id']);
                }
                $dados = PermissoesAlmoxarifado::dadosUsuarioLogado();
                $novo = PedidoCarrinho::abrirPedidoDaCompra($alerta, $dados['nome'], $dados['id']);
                AlertaCompra::vincularPedidoGerado($alertaId, (int) $novo['id']);
            } catch (Throwable $e) {
                return redirect('/almoxarifado-escolar/movimentos?alerta='.$alertaId)
                    ->with('erro', $e->getMessage());
            }

            return redirect('/almoxarifado-escolar/pedidos/'.$novo['id'].'/nota')
                ->with('ok', 'Entrada conferida. Pedido #'.$novo['id'].' aberto no fluxo completo (separação → conferência) com o item comprado.');
        }

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

    private function podeResolverCobertura(mixed $user): bool
    {
        return PermissoesAlmoxarifado::podeRetirarFaltosos($user)
            || PermissoesAlmoxarifado::podeAssinar($user, 'entrega');
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
     * Quem pode solicitar itens no carrinho: processo carrinho por setor ou pedidos,
     * com vínculo escola_usuario (filtrado na UI pela escola escolhida).
     *
     * @return list<array{id:int,nome:string,login:string,meta?:string,data:array{escolas:string}}>
     */
    private function usuariosSolicitantes(): array
    {
        $lista = $this->usuariosRede([
            \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos::CARRINHO_SETOR,
            \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos::PEDIDOS,
        ]);

        // Só entram candidatos com ao menos uma escola — a UI filtra pela unidade.
        return array_values(array_filter(
            $lista,
            static fn (array $u): bool => trim((string) ($u['data']['escolas'] ?? '')) !== ''
        ));
    }

    /**
     * Usuários do módulo (pedidos / movimentos / painel), com data-escolas.
     *
     * @param  list<int>|null  $processos
     * @return list<array{id:int,nome:string,login:string,meta?:string,data?:array{escolas:string}}>
     */
    private function usuariosRede(?array $processos = null): array
    {
        $processos ??= [
            \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos::PEDIDOS,
            \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos::MOVIMENTOS,
            \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos::PAINEL,
        ];

        if (class_exists(\iEducar\Packages\BuritiLagunaAmbiente\Support\UsuariosSelecionaveis::class)) {
            return $this->comEscolas(\iEducar\Packages\BuritiLagunaAmbiente\Support\UsuariosSelecionaveis::paraSelect(
                Auth::user(),
                $processos,
            ));
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

        return $this->comEscolas($out);
    }

    /**
     * @param  list<array{id:int,nome:string,login:string,meta?:string}>  $usuarios
     * @return list<array{id:int,nome:string,login:string,meta?:string,data:array{escolas:string}}>
     */
    private function comEscolas(array $usuarios): array
    {
        $ids = array_values(array_unique(array_map(static fn (array $u): int => (int) $u['id'], $usuarios)));
        $porUsuario = [];
        if ($ids !== [] && Schema::hasTable('pmieducar.escola_usuario')) {
            try {
                $rows = DB::table('pmieducar.escola_usuario')
                    ->whereIn('ref_cod_usuario', $ids)
                    ->get(['ref_cod_usuario', 'ref_cod_escola']);
                foreach ($rows as $r) {
                    $porUsuario[(int) $r->ref_cod_usuario][] = (int) $r->ref_cod_escola;
                }
            } catch (Throwable) {
                $porUsuario = [];
            }
        }

        foreach ($usuarios as &$usuario) {
            $escolas = $porUsuario[(int) $usuario['id']] ?? [];
            $usuario['data'] = ['escolas' => implode(',', $escolas)];
        }
        unset($usuario);

        return $usuarios;
    }

    private function usuarioPertenceEscola(int $usuarioId, string $escolaNome): bool
    {
        if ($usuarioId < 1 || $escolaNome === '') {
            return false;
        }
        if (! Schema::hasTable('pmieducar.escola_usuario')
            || ! Schema::hasTable('pmieducar.escola')
            || ! Schema::hasTable('cadastro.juridica')) {
            return true;
        }

        try {
            $cod = DB::table('pmieducar.escola')
                ->join('cadastro.juridica', 'cadastro.juridica.idpes', '=', 'pmieducar.escola.ref_idpes')
                ->where('cadastro.juridica.fantasia', $escolaNome)
                ->value('pmieducar.escola.cod_escola');
            if (! $cod) {
                return false;
            }

            return DB::table('pmieducar.escola_usuario')
                ->where('ref_cod_usuario', $usuarioId)
                ->where('ref_cod_escola', (int) $cod)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /** Admin / institucional pode abrir carrinho sem estar em escola_usuario. */
    private function atorPodeSolicitarSemVinculo(?\App\User $user): bool
    {
        if ($user === null) {
            return false;
        }
        try {
            $nivel = method_exists($user, 'getLevel') ? (int) $user->getLevel() : null;
            if ($nivel === null && isset($user->type)) {
                $nivel = (int) ($user->type->level ?? $user->type->nivel ?? 0) ?: null;
            }
            if ($nivel === null) {
                return false;
            }

            return in_array($nivel, [
                \App\Models\LegacyUserType::LEVEL_ADMIN,
                \App\Models\LegacyUserType::LEVEL_INSTITUTIONAL,
            ], true);
        } catch (Throwable) {
            return false;
        }
    }

    /** Exibição PT-BR (humanizado na UI; absoluto em PDF/listas densas). */
    private function formatarQuando(mixed $valor, bool $humanizado = true): string
    {
        if (class_exists(\iEducar\Packages\BuritiLagunaAmbiente\Support\DataHora::class)) {
            return \iEducar\Packages\BuritiLagunaAmbiente\Support\DataHora::formatar($valor, $humanizado);
        }
        if ($valor === null || $valor === '') {
            return '—';
        }
        try {
            return \Carbon\Carbon::parse((string) $valor)->timezone(config('app.timezone', 'America/Sao_Paulo'))->format('d/m/Y H:i');
        } catch (Throwable) {
            return (string) $valor;
        }
    }
}
