@extends('almoxarifado::layout')
@section('titulo', 'Itens')
@section('migalha', 'Itens')
@section('icone-tela', 'fa-cubes')
@section('titulo-tela', 'Itens')
@section('lead')
    Catálogo do depósito — cadastre a foto para facilitar a identificação no pedido.
@endsection
@section('acoes-topo')
    <a class="botao claro icone" href="{{ url('/almoxarifado-escolar') }}" title="Painel" aria-label="Painel"><i aria-hidden="true" class="fa fa-dashboard"></i></a>
    <a class="botao claro icone" href="{{ url('/almoxarifado-escolar/saldos') }}" title="Saldos" aria-label="Saldos"><i aria-hidden="true" class="fa fa-bar-chart"></i></a>
@endsection

@section('conteudo')
<style>
    .catalogo-grade {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 16px;
    }
    .item-card {
        --tom-card: var(--cor-tela);
        display: flex;
        flex-direction: column;
        padding: 0;
        overflow: hidden;
        border-radius: 16px;
        border: 1px solid color-mix(in srgb, var(--linha) 85%, var(--tom-card));
        border-top: 1px solid color-mix(in srgb, var(--linha) 85%, var(--tom-card));
        background: var(--superficie, #fff);
        box-shadow: 0 10px 28px rgba(21, 34, 43, .05);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .item-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 16px 36px rgba(21, 34, 43, .09);
        border-color: color-mix(in srgb, var(--tom-card) 35%, var(--linha));
    }
    .item-card.is-critico { --tom-card: var(--erro); }
    .item-card.is-ok { --tom-card: var(--ok); }

    .item-card__media {
        position: relative;
        aspect-ratio: 4 / 3;
        background:
            linear-gradient(145deg, color-mix(in srgb, var(--clara) 70%, #fff), #f4f7f9);
        overflow: hidden;
    }
    .item-card__media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        cursor: zoom-in;
        transition: transform .35s ease;
    }
    .item-card:hover .item-card__media img { transform: scale(1.03); }
    .item-card__media-vazio {
        width: 100%;
        height: 100%;
        display: grid;
        place-items: center;
        gap: 6px;
        color: color-mix(in srgb, var(--suave) 75%, #fff);
        font-size: .78rem;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .item-card__media-vazio i {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        font-size: 1.25rem;
        background: color-mix(in srgb, var(--clara) 55%, #fff);
        border: 1px dashed var(--linha);
        color: var(--suave);
    }
    .item-card__chip {
        position: absolute;
        top: 10px;
        left: 10px;
        z-index: 1;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: .68rem;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
        background: color-mix(in srgb, #fff 88%, transparent);
        color: var(--tinta);
        border: 1px solid color-mix(in srgb, var(--linha) 70%, transparent);
        backdrop-filter: blur(6px);
        box-shadow: 0 4px 12px rgba(21, 34, 43, .08);
    }
    .item-card__status {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 1;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 700;
        backdrop-filter: blur(6px);
        box-shadow: 0 4px 12px rgba(21, 34, 43, .08);
    }
    .item-card__status.ok {
        background: color-mix(in srgb, var(--ok-fundo) 92%, transparent);
        color: var(--ok);
        border: 1px solid color-mix(in srgb, var(--ok) 25%, transparent);
    }
    .item-card__status.fail {
        background: color-mix(in srgb, var(--erro-fundo) 92%, transparent);
        color: var(--erro);
        border: 1px solid color-mix(in srgb, var(--erro) 25%, transparent);
    }

    .item-card__corpo {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 14px 14px 12px;
        flex: 1;
    }
    .item-card__sku {
        margin: 0;
        font-size: .68rem;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--suave);
    }
    .item-card__titulo {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.3;
        letter-spacing: -.01em;
        color: var(--tinta);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 2.6em;
    }
    .item-card__metricas {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-top: auto;
    }
    .item-card__metrica {
        padding: 8px 10px;
        border-radius: 10px;
        background: color-mix(in srgb, var(--clara) 55%, #fff);
        border: 1px solid color-mix(in srgb, var(--linha) 80%, transparent);
    }
    .item-card__metrica span {
        display: block;
        font-size: .65rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--suave);
        margin: 0 0 2px;
    }
    .item-card__metrica strong {
        font-size: 1.05rem;
        font-weight: 800;
        letter-spacing: -.02em;
        color: var(--tinta);
    }
    .item-card.is-critico .item-card__metrica--saldo {
        background: color-mix(in srgb, var(--erro-fundo) 70%, #fff);
        border-color: color-mix(in srgb, var(--erro) 22%, var(--linha));
    }
    .item-card.is-critico .item-card__metrica--saldo strong { color: var(--erro); }

    .item-card__acoes {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
        padding: 10px 12px 12px;
        border-top: 1px solid color-mix(in srgb, var(--linha) 85%, transparent);
        background: linear-gradient(180deg, color-mix(in srgb, var(--clara) 35%, #fff), #fff);
    }
    .item-card__acoes .botao {
        padding: 6px 10px;
        border-radius: 8px;
        font-size: .78rem;
    }
    .item-card__acoes form { margin: 0; display: inline-flex; align-items: center; gap: 6px; }
    .item-card__file {
        position: relative;
        overflow: hidden;
        display: inline-flex;
    }
    .item-card__file input[type=file] {
        position: absolute;
        inset: 0;
        opacity: 0;
        cursor: pointer;
        width: 100%;
        height: 100%;
    }
    .item-card__file-label {
        pointer-events: none;
    }
    .item-card__file-nome {
        font-size: .7rem;
        color: var(--suave);
        max-width: 110px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>

    @if(!empty($podeOperarDeposito))
    <div class="bloco">
        <h2><i class="fa fa-plus"></i> Novo item</h2>
        <form class="form-grid" method="post" action="{{ url('/almoxarifado-escolar/itens') }}" enctype="multipart/form-data">
            @csrf
            <label>SKU <input name="sku" required placeholder="Ex.: CAD-A4"></label>
            <label class="col-wide">Título <input name="titulo" required placeholder="Ex.: Caderno A4 96 folhas"></label>
            <label>Saldo <input type="number" name="saldo" min="0" required value="0"></label>
            <label>Mínimo operacional <input type="number" name="minimo" min="0" required value="0"></label>
            <label class="col-wide">Foto do item
                <input type="file" name="foto" accept="image/jpeg,image/png,image/webp,image/gif">
            </label>
            <div class="acoes"><button class="botao" type="submit"><i class="fa fa-save"></i> Cadastrar</button></div>
        </form>
        <p style="margin:8px 0 0;color:var(--suave);font-size:.8rem">JPG, PNG, WEBP ou GIF · até 3 MB.</p>
    </div>
    @endif

    <div class="bloco">
        <h2><i class="fa fa-cubes"></i> Catálogo ({{ count($linhas ?? []) }})</h2>
        @if(!empty($linhas))
            <div class="catalogo-grade">
                @foreach($linhas as $i => $l)
                    @php
                        $critico = ($l['badge_tom'] ?? '') === 'fail';
                        $temFoto = !empty($l['foto_url']);
                        $sku = (string) ($l['sku'] ?? '');
                        $titulo = (string) ($l['titulo'] ?? ('Item '.($i + 1)));
                        $legendaFoto = trim($sku.' — '.$titulo, ' —');
                    @endphp
                    <article class="card item-card {{ $critico ? 'is-critico' : 'is-ok' }}"
                             style="animation-delay: {{ $i * 0.04 }}s">
                        <div class="item-card__media">
                            @if($sku !== '')
                                <span class="item-card__chip">{{ $sku }}</span>
                            @endif
                            <span class="item-card__status {{ $critico ? 'fail' : 'ok' }}">
                                {{ $critico ? 'Abaixo do mínimo' : 'Em estoque' }}
                            </span>
                            @if($temFoto)
                                <img src="{{ $l['foto_url'] }}"
                                     alt="{{ $titulo }}"
                                     data-almox-foto="{{ $l['foto_url'] }}"
                                     data-almox-titulo="{{ $legendaFoto }}"
                                     title="Abrir foto">
                            @else
                                <div class="item-card__media-vazio" aria-hidden="true">
                                    <i class="fa fa-camera"></i>
                                    Sem foto
                                </div>
                            @endif
                        </div>

                        <div class="item-card__corpo">
                            <p class="item-card__sku">{{ $sku !== '' ? 'SKU '.$sku : 'Catálogo' }}</p>
                            <h3 class="item-card__titulo" title="{{ $titulo }}">{{ $titulo }}</h3>
                            <div class="item-card__metricas">
                                <div class="item-card__metrica item-card__metrica--saldo">
                                    <span>Saldo</span>
                                    <strong>{{ (int) ($l['saldo'] ?? 0) }}</strong>
                                </div>
                                <div class="item-card__metrica">
                                    <span>Mínimo</span>
                                    <strong>{{ (int) ($l['minimo'] ?? 0) }}</strong>
                                </div>
                            </div>
                        </div>

                        @if(!empty($l['id']))
                            <div class="item-card__acoes">
                                @if($temFoto)
                                    <button type="button" class="botao claro"
                                            data-almox-foto="{{ $l['foto_url'] }}"
                                            data-almox-titulo="{{ $legendaFoto }}"
                                            title="Ampliar foto">
                                        <i class="fa fa-search-plus"></i> Ver
                                    </button>
                                    @if(!empty($podeOperarDeposito))
                                    <form method="post" action="{{ url('/almoxarifado-escolar/itens/'.$l['id'].'/foto/remover') }}"
                                          onsubmit="return confirm('Remover a foto deste item?');">
                                        @csrf
                                        <button class="botao claro" type="submit" title="Remover foto">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                    @endif
                                @endif
                                @if(!empty($podeOperarDeposito))
                                <form method="post" action="{{ url('/almoxarifado-escolar/itens/'.$l['id'].'/foto') }}" enctype="multipart/form-data">
                                    @csrf
                                    <label class="botao {{ $temFoto ? 'claro' : '' }} item-card__file" title="{{ $temFoto ? 'Trocar foto' : 'Enviar foto' }}">
                                        <span class="item-card__file-label">
                                            <i class="fa fa-camera"></i>
                                            {{ $temFoto ? 'Trocar' : 'Enviar foto' }}
                                        </span>
                                        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp,image/gif" required
                                               onchange="this.form.requestSubmit()">
                                    </label>
                                </form>
                                @endif
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @else
            <p style="color:var(--suave);margin:0">Sem registros nesta PoC — os dados de demonstração aparecem após o povoamento do módulo.</p>
        @endif
    </div>

    @include('almoxarifado::partials.foto-lightbox')
@endsection
