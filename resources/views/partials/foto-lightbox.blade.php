{{-- Lightbox simples para fotos do catálogo / carrinho --}}
<div id="almox-foto-lightbox" class="almox-foto-lb" hidden>
    <button type="button" class="almox-foto-lb-fechar" aria-label="Fechar">&times;</button>
    <img src="" alt="Foto do item">
    <p class="almox-foto-lb-legenda"></p>
</div>
<style>
    .almox-foto-thumb {
        width: 52px; height: 52px; border-radius: 10px; object-fit: cover;
        border: 1px solid var(--linha); background: #eef2f5; cursor: zoom-in;
        display: block; flex: none;
    }
    .almox-foto-thumb.lg { width: 72px; height: 72px; border-radius: 12px; }
    .almox-foto-placeholder {
        width: 52px; height: 52px; border-radius: 10px; flex: none;
        display: grid; place-items: center; background: #eef2f5; color: var(--suave);
        border: 1px dashed var(--linha); font-size: 1.1rem;
    }
    .almox-foto-placeholder.lg { width: 72px; height: 72px; border-radius: 12px; }
    .almox-foto-lb {
        position: fixed; inset: 0; z-index: 80;
        background: rgba(15, 24, 32, .72); backdrop-filter: blur(4px);
        display: grid; place-items: center; padding: 24px;
    }
    .almox-foto-lb[hidden] { display: none !important; }
    .almox-foto-lb img {
        max-width: min(920px, 92vw); max-height: min(78vh, 820px);
        border-radius: 14px; box-shadow: 0 18px 48px rgba(0,0,0,.35);
        background: #fff;
    }
    .almox-foto-lb-legenda {
        margin: 10px 0 0; color: #fff; font-weight: 700; text-align: center;
        text-shadow: 0 1px 4px rgba(0,0,0,.4);
    }
    .almox-foto-lb-fechar {
        position: absolute; top: 16px; right: 18px; width: 40px; height: 40px;
        border: 0; border-radius: 12px; background: rgba(255,255,255,.16); color: #fff;
        font-size: 1.6rem; line-height: 1; cursor: pointer;
    }
</style>
<script>
(function () {
    if (window.__almoxFotoLb) return;
    window.__almoxFotoLb = true;
    var box = document.getElementById('almox-foto-lightbox');
    if (!box) return;
    var img = box.querySelector('img');
    var legenda = box.querySelector('.almox-foto-lb-legenda');
    var fechar = box.querySelector('.almox-foto-lb-fechar');
    function close() { box.hidden = true; img.removeAttribute('src'); }
    function open(src, titulo) {
        if (!src) return;
        img.src = src;
        img.alt = titulo || 'Foto do item';
        legenda.textContent = titulo || '';
        box.hidden = false;
    }
    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-almox-foto]');
        if (!t) return;
        e.preventDefault();
        open(t.getAttribute('data-almox-foto'), t.getAttribute('data-almox-titulo') || '');
    });
    fechar.addEventListener('click', close);
    box.addEventListener('click', function (e) { if (e.target === box) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !box.hidden) close(); });
})();
</script>
