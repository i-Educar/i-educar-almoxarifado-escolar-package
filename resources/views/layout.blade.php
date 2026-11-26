<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Almoxarifado')</title>
    <link rel="stylesheet" href="{{ url('/intranet/styles/font-awesome.css') }}">
    <style>
        :root {
            --cor: {{ $identidade['cor'] ?? '#4a5a6a' }};
            --destaque: {{ $identidade['destaque'] ?? '#6b7c8f' }};
            --clara: {{ $identidade['clara'] ?? '#eef1f5' }};
            --nav: #15222b; --nav-texto: #e8eef3; --fundo: #e8edf0;
            --tinta: #1a242b; --suave: #5b6b76; --linha: #cfd8df;
            --ok: #17663f; --ok-fundo: #e5f6ec; --erro: #9b2c2c; --erro-fundo: #fdeceb;
            --ambar: #b86e00; --cor-tela: var(--cor);
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", sans-serif; font-size: 14px; color: var(--tinta);
            background: radial-gradient(900px 360px at 8% -8%, color-mix(in srgb, var(--clara) 80%, transparent), transparent 55%), linear-gradient(180deg, #f3f6f8, var(--fundo)); }
        .app { display: flex; min-height: 100vh; }
        .lateral { width: 248px; flex: none; background: linear-gradient(180deg, color-mix(in srgb, var(--cor) 35%, #15222b), var(--nav)); color: var(--nav-texto); }
        .marca { display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; padding: 14px; border-bottom: 1px solid rgba(255,255,255,.08); }
        .marca .marca-texto { display: none; }
        .marca .marca-titulo { display: block; font-size: .82rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; line-height: 1.15; }
        .marca .marca-desc { display: block; color: #8aa0ad; font-weight: 400; font-size: .72rem; line-height: 1.25; }
                .icone { width: 40px; height: 40px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center;
            background: linear-gradient(145deg, var(--destaque), var(--cor)); color: #fff; box-shadow: 0 8px 18px color-mix(in srgb, var(--cor) 35%, transparent); }
        .modulo { padding: 8px 10px; }
        .modulo-nome { width: 100%; display: flex; justify-content: space-between; margin: 0; padding: 8px; background: 0; border: 0; color: #8aa0ad; font: inherit; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; font-weight: 700; }
        .modulo a { display: flex; align-items: center; gap: 10px; color: var(--nav-texto); text-decoration: none; border-radius: 10px; padding: 8px 10px; }
        .modulo a i { width: 18px; text-align: center; }
        .modulo a:hover { background: color-mix(in srgb, var(--cor) 42%, rgba(255,255,255,.08)); color: #fff; }
        .modulo a.ativo { background: color-mix(in srgb, var(--cor) 68%, #060b10); color: #fff; font-weight: 700; box-shadow: inset 3px 0 0 var(--destaque, var(--cor)); }
        .miolo { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .topo { position: sticky; top: 0; z-index: 10; min-height: 56px; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 18px; background: color-mix(in srgb,#fff 90%, transparent); backdrop-filter: blur(8px); border-bottom: 1px solid var(--linha); }
        .migalha { margin: 0; color: var(--suave); font-size: 13px; } .migalha a { color: var(--cor); font-weight: 700; text-decoration: none; }
        main { padding: 18px 20px 28px; flex: 1; }
        .cabeca-tela { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin: -18px -20px 16px; padding: 14px 16px 14px 20px;
            background: linear-gradient(120deg, color-mix(in srgb, var(--clara) 70%, var(--mix-branco, #fff)), var(--superficie, #fff) 55%); border-bottom: 1px solid var(--linha); border-left: 4px solid var(--cor-tela); }
        .identidade-tela { display: flex; align-items: center; gap: 12px; }
        .marca-tela { width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center; background: linear-gradient(145deg, var(--destaque), var(--cor-tela)); color: #fff; font-size: 18px; box-shadow: 0 10px 20px color-mix(in srgb, var(--cor-tela) 28%, transparent); }
        .cabeca-tela h1 { margin: 0; font-size: 1.25rem; }

        .titulo-modulo { margin: 0 0 2px; font-size: .7rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: var(--cor-tela); }
        .cabeca-tela .acoes-tela { margin-left: auto; }
        .cabeca-tela .lead { margin: 2px 0 0; color: var(--suave); }
        .acoes-tela { display: flex; flex-wrap: wrap; gap: 8px; }
        .botao { display: inline-flex; align-items: center; gap: 8px; background: var(--cor-tela); color: #fff; border: 0; border-radius: 10px; padding: 8px 12px; font: inherit; text-decoration: none; cursor: pointer; }
        .botao.claro { background: var(--superficie, #fff); color: var(--cor-tela); border: 1px solid color-mix(in srgb, var(--cor-tela) 35%, var(--linha)); }
        .flash { border-radius: 12px; padding: 12px 14px; margin: 0 0 16px; } .flash.ok { background: var(--ok-fundo); color: var(--ok); } .flash.erro { background: var(--erro-fundo); color: var(--erro); }
        .bloco { background: var(--superficie, #fff); border: 1px solid var(--linha); border-radius: 14px; padding: 14px 16px; margin: 0 0 12px; border-top: 3px solid var(--cor-tela); box-shadow: 0 10px 24px rgba(21,34,43,.04); }
        .bloco h2 { margin: 0 0 10px; display: flex; align-items: center; gap: 8px; font-size: 1.05rem; }
        .numeros { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; margin: 0 0 16px; }
        .numeros article { --tom: var(--cor-tela); background: var(--superficie, #fff); border: 1px solid color-mix(in srgb, var(--tom) 30%, var(--linha)); border-top: 3px solid var(--tom); border-radius: 14px; padding: 14px; box-shadow: 0 8px 18px rgba(21,34,43,.05); animation: sobe .4s ease both; }
        .numeros .kpi-ico { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; margin: 0 0 8px; background: color-mix(in srgb, var(--tom) 12%, var(--superficie, #fff)); color: var(--tom); border: 1px solid color-mix(in srgb, var(--tom) 22%, var(--superficie, #fff)); }
        .numeros span { display: block; color: var(--suave); font-size: .75rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .numeros strong { font-size: 1.4rem; letter-spacing: -.03em; }
        .atalhos { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
        .atalhos a { display: grid; grid-template-columns: 42px 1fr; gap: 10px; align-items: center; min-height: 64px; padding: 10px 12px; border: 1px solid var(--linha); border-radius: 14px; text-decoration: none; color: var(--tinta); background: linear-gradient(180deg, color-mix(in srgb, var(--clara) 55%, var(--mix-branco, #fff)), var(--superficie, #fff)); font-weight: 700; }
        .atalhos a i { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; color: #fff; background: var(--destaque); }
        .atalhos a small { display: block; font-weight: 500; color: var(--suave); margin-top: 2px; }
        .atalhos a:hover { transform: translateY(-2px); border-color: var(--destaque); }
        .card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
        .card { background: var(--superficie, #fff); border: 1px solid var(--linha); border-radius: 14px; padding: 14px; border-top: 3px solid var(--tom, var(--cor)); box-shadow: 0 8px 18px rgba(21,34,43,.04); animation: sobe .4s ease both; }
        .card header { display: flex; gap: 10px; align-items: center; margin: 0 0 8px; }
        .card .ico { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; color: #fff; background: linear-gradient(145deg, var(--destaque), var(--tom, var(--cor))); }
        .card h3 { margin: 0; font-size: .95rem; } .card .meta { margin: 0; color: var(--suave); font-size: .78rem; }
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 999px; font-size: .75rem; font-weight: 700; }
        .badge.ok { background: var(--ok-fundo); color: var(--ok); } .badge.fail { background: var(--erro-fundo); color: var(--erro); } .badge.warn { background: #fff6e8; color: var(--ambar); }
        /* Status do pedido — identidade visual */
        .badge.st-carrinho { background:#eef1f4; color:#5b6b76; }
        .badge.st-fechado { background:#e8eef5; color:#3d5a80; }
        .badge.st-separado { background:#e4f1f8; color:#2a6f97; }
        .badge.st-aceito { background:#e5f6ec; color:#1a7a4c; }
        .badge.st-entregue { background:#e0f4f4; color:#0d7377; }
        .badge.st-conferido { background:#d8f0e4; color:#17663f; box-shadow:inset 0 0 0 1px color-mix(in srgb,#17663f 25%,transparent); }
        .badge.st-bloqueado { background:#fdeceb; color:#c0392b; }
        .badge.st-outro { background:#eef1f4; color:#5b6b76; }
        .card.pedido-st { --tom: var(--st-cor, var(--cor)); }
        .card.pedido-st.st-conferido { opacity:.96; }
        .card.pedido-st.st-bloqueado { border-color:color-mix(in srgb,#c0392b 35%,var(--linha)); }
        .status-pill {
            display:inline-flex; align-items:center; gap:8px; padding:6px 12px; border-radius:999px;
            font-size:.85rem; font-weight:700; background:color-mix(in srgb,var(--st-cor,#5b6b76) 12%,#fff);
            color:var(--st-cor,#5b6b76); border:1px solid color-mix(in srgb,var(--st-cor,#5b6b76) 28%,#fff);
        }
        .status-pill i { font-size:1rem; }
        .trilho-status { display:flex; flex-wrap:wrap; gap:6px; margin:0 0 14px; padding:0; list-style:none; }
        .trilho-status li {
            flex:1; min-width:88px; text-align:center; padding:8px 6px; border-radius:10px;
            font-size:.72rem; font-weight:700; color:var(--suave); background:#f3f6f8; border:1px solid var(--linha);
        }
        .trilho-status li i { display:block; font-size:1rem; margin:0 0 4px; }
        .trilho-status li.feito { background:color-mix(in srgb,var(--ok) 10%,#fff); color:var(--ok); border-color:color-mix(in srgb,var(--ok) 30%,#fff); }
        .trilho-status li.atual { background:color-mix(in srgb,var(--st-cor,var(--cor)) 14%,#fff); color:var(--st-cor,var(--cor)); border-color:color-mix(in srgb,var(--st-cor,var(--cor)) 40%,#fff); box-shadow:0 0 0 2px color-mix(in srgb,var(--st-cor,var(--cor)) 18%,transparent); }
        .trilho-status li.bloqueado { background:#fdeceb; color:#c0392b; border-color:#f5c6cb; }
        table { width: 100%; border-collapse: collapse; } th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--linha); } th { background: var(--clara); color: var(--cor); }
        .grade { display: grid; grid-template-columns: 70px repeat(5, 1fr); gap: 4px; }
        .grade .h, .grade .c { padding: 8px; border-radius: 8px; background: var(--superficie, #fff); border: 1px solid var(--linha); font-size: .78rem; text-align: center; }
        .grade .h { background: var(--clara); font-weight: 700; color: var(--cor); }
        .grade .slot { background: color-mix(in srgb, var(--cor) 12%, var(--superficie, #fff)); border-color: color-mix(in srgb, var(--cor) 30%, var(--linha)); font-weight: 600; }
        .grade .choque { background: var(--erro-fundo); border-color: color-mix(in srgb, var(--erro) 40%, var(--superficie, #fff)); color: var(--erro); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; align-items: end; }
        .form-grid label { display: grid; gap: 4px; font-size: .8rem; font-weight: 700; color: var(--suave); }
        .form-grid input, .form-grid select, .form-grid textarea { min-height: 36px; padding: 6px 8px; border: 1px solid var(--linha); border-radius: 8px; font: inherit; background: var(--superficie, #fff); }
        .form-grid textarea { min-height: 70px; resize: vertical; }
        .form-grid .col-wide { grid-column: 1 / -1; }
        .form-grid .acoes { grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: 8px; }
        .select-usuario { align-items: stretch; }
        .select-usuario-busca { margin-bottom: 4px; }
        .select-usuario-lista { min-height: 140px; width: 100%; }
        .select-usuario-lista option:checked { background: var(--cor); color: #fff; }
        .select-usuario-aviso { font-weight: 500; color: var(--suave); font-size: .75rem; }
        .campo-fixado {
            display: grid; gap: 4px; font-size: .8rem; font-weight: 700; color: var(--suave);
            padding: 10px 12px; border: 1px dashed color-mix(in srgb, var(--cor) 28%, var(--linha));
            border-radius: 10px; background: color-mix(in srgb, var(--clara) 55%, var(--mix-branco, #fff));
        }
        .campo-fixado strong { color: var(--tinta); font-size: .95rem; }
        .campo-fixado small { font-weight: 500; }
        footer.rodape { background: var(--nav); color: var(--nav-texto); border-top: 3px solid var(--destaque); padding: 12px 18px; font-size: .82rem; }
        @keyframes sobe { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        @media (max-width: 860px) { .lateral { display: none; } }

    </style>
    @include('buriti-ui::estilos')
</head>
<body>
@php $grupos = $menuGrupos ?? []; @endphp
<div class="app">
    <aside class="lateral">
        <a class="marca" href="{{ url('/almoxarifado-escolar') }}" title="{{ $identidade['nome'] ?? '' }}">
            <span class="icone"><i class="fa {{ $identidade['icone'] ?? 'fa-cubes' }}"></i></span>
        </a>
        @foreach($grupos as $grupo)
            <nav class="modulo">
                <div class="modulo-nome">{{ $grupo['nome'] }}</div>
                @foreach($grupo['itens'] as $item)
                    <a href="{{ url($item['url']) }}" class="@if(request()->is(ltrim($item['url'],'/'))) ativo @endif">
                        <i class="fa {{ $item['icone'] }}"></i> {{ $item['rotulo'] }}
                    </a>
                @endforeach
            </nav>
        @endforeach
    </aside>
    <div class="miolo">
        @php
            $homeModulo = url('/almoxarifado-escolar');
            $nomeModulo = $identidade['nome'] ?? 'Almoxarifado escolar';
            $descricaoModulo = $identidade['descricao'] ?? null;
            $menuGrupos = $menuGrupos ?? ($grupos ?? []);
        @endphp
        @include('buriti-ui::topo')
        <main>
            @if(session('ok')) <p class="flash ok">{{ session('ok') }}</p> @endif
            @if(session('erro')) <p class="flash erro">{{ session('erro') }}</p> @endif

            <header class="cabeca-tela">
                <div class="identidade-tela">
                    <span class="marca-tela"><i class="fa @yield('icone-tela', $identidade['icone'] ?? 'fa-cubes')" aria-hidden="true"></i></span>
                    <div>
                        <h1>@hasSection('titulo-tela')@yield('titulo-tela')@else@yield('migalha', 'Painel')@endif</h1>
                        @hasSection('lead')
                            <p class="lead">@yield('lead')</p>
                        @endif
                    </div>
                </div>
                <div class="acoes-tela">
                    @yield('acoes-topo')
                    @includeWhen(View::exists('buriti-ui::informes-modulo'), 'buriti-ui::informes-modulo', ['moduloId' => 'almoxarifado'])
                </div>
            </header>

            @hasSection('foco')
                @yield('foco')
            @endif
            @hasSection('trilho')
                <nav class="buriti-trilho" aria-label="Ações da tela">@yield('trilho')</nav>
            @endif

            @yield('conteudo')
        </main>
        @include('buriti-ui::rodape', ['moduloRodape' => 'Almoxarifado'])
    </div>
</div>
<script>
(function () {
    if (window.__buritiSelectBuscaAlmox) return;
    window.__buritiSelectBuscaAlmox = true;
    document.addEventListener('input', function (e) {
        var el = e.target;
        if (!el || !el.classList) return;
        if (!el.classList.contains('select-usuario-busca') && !el.classList.contains('buriti-select-busca')) return;
        var sel = document.getElementById(el.getAttribute('data-for') || '');
        if (!sel) return;
        var q = (el.value || '').trim().toLowerCase();
        Array.prototype.forEach.call(sel.options, function (opt, idx) {
            if (idx === 0 && opt.value === '') { opt.hidden = false; opt.disabled = false; return; }
            // Respeita filtro por escola / unidade
            if (opt.getAttribute('data-fora-escola') === '1') {
                opt.hidden = true;
                opt.disabled = true;
                return;
            }
            var hay = (opt.getAttribute('data-busca') || opt.textContent || '').toLowerCase();
            opt.hidden = q !== '' && hay.indexOf(q) === -1;
        });
    });
})();
</script>
</body>
</html>
