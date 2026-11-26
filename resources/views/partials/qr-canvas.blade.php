{{--
  QR escaneável: canvas (biblioteca qrcode) + imagem de fallback.
  Variáveis: $qrUrl (string), $qrId (string único no DOM), $qrSize (int, default 240)
--}}
@php
    $qrId = $qrId ?? ('qr-'.substr(md5($qrUrl ?? uniqid('', true)), 0, 8));
    $qrSize = (int) ($qrSize ?? 240);
    $qrFallback = 'https://api.qrserver.com/v1/create-qr-code/?size='.$qrSize.'x'.$qrSize.'&ecc=M&margin=8&data='.rawurlencode((string) $qrUrl);
@endphp
<div class="almox-qr-box" data-qr-url="{{ $qrUrl }}" data-qr-size="{{ $qrSize }}">
    <canvas id="{{ $qrId }}" class="almox-qr-canvas" width="{{ $qrSize }}" height="{{ $qrSize }}"
            style="display:block;margin:0 auto;background:#fff;image-rendering:pixelated"></canvas>
    <img class="almox-qr-fallback" src="{{ $qrFallback }}" alt="QR Code"
         width="{{ $qrSize }}" height="{{ $qrSize }}"
         style="display:none;margin:0 auto;background:#fff;image-rendering:pixelated"
         loading="eager" referrerpolicy="no-referrer">
</div>
