{{-- Gera QR no canvas; se falhar, mostra imagem de fallback (api.qrserver). --}}
<script>
(function () {
    if (window.__almoxQrReady) return;
    window.__almoxQrReady = true;

    function pintarTodos() {
        var boxes = document.querySelectorAll('.almox-qr-box[data-qr-url]');
        if (!boxes.length) return;

        function fallback(box) {
            var canvas = box.querySelector('.almox-qr-canvas');
            var img = box.querySelector('.almox-qr-fallback');
            if (canvas) canvas.style.display = 'none';
            if (img) img.style.display = 'block';
        }

        function desenhar(QRCodeLib) {
            boxes.forEach(function (box) {
                var url = box.getAttribute('data-qr-url');
                var size = parseInt(box.getAttribute('data-qr-size') || '240', 10);
                var canvas = box.querySelector('.almox-qr-canvas');
                if (!url || !canvas) return;
                try {
                    QRCodeLib.toCanvas(canvas, url, {
                        width: size,
                        margin: 2,
                        errorCorrectionLevel: 'M',
                        color: { dark: '#000000', light: '#ffffff' }
                    }, function (err) {
                        if (err) fallback(box);
                        else {
                            var img = box.querySelector('.almox-qr-fallback');
                            if (img) img.style.display = 'none';
                            canvas.style.display = 'block';
                        }
                    });
                } catch (e) {
                    fallback(box);
                }
            });
        }

        if (window.QRCode && typeof window.QRCode.toCanvas === 'function') {
            desenhar(window.QRCode);
            return;
        }

        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js';
        s.async = true;
        s.onload = function () {
            if (window.QRCode && typeof window.QRCode.toCanvas === 'function') {
                desenhar(window.QRCode);
            } else {
                boxes.forEach(fallback);
            }
        };
        s.onerror = function () { boxes.forEach(fallback); };
        document.head.appendChild(s);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', pintarTodos);
    } else {
        pintarTodos();
    }
    window.addEventListener('beforeprint', pintarTodos);
})();
</script>
