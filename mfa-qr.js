'use strict';
async function renderMfaQr() {
    const el = document.getElementById('mfa-qr');
    if (!el) return;
    try {
        const { qrcodegen } = await import('./vendor/qrcodegen.js');
        const uri = el.dataset.uri;
        if (!uri || !uri.startsWith('otpauth://totp/')) throw new Error('Invalid setup URI');
        const qr = qrcodegen.QrCode.encodeText(uri, qrcodegen.QrCode.Ecc.MEDIUM);
        const ns = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(ns, 'svg');
        const size = qr.size + 8;
        svg.setAttribute('viewBox', '0 0 ' + size + ' ' + size);
        svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', 'QR kód pro Google Authenticator');
        svg.setAttribute('shape-rendering', 'crispEdges');
        const bg = document.createElementNS(ns, 'rect');
        bg.setAttribute('width', String(size));
        bg.setAttribute('height', String(size));
        bg.setAttribute('fill', 'white');
        svg.append(bg);
        let d = '';
        for (let y = 0; y < qr.size; y++) {
            for (let x = 0; x < qr.size; x++) {
                if (qr.getModule(x, y)) d += 'M' + (x + 4) + ',' + (y + 4) + 'h1v1h-1z';
            }
        }
        const path = document.createElementNS(ns, 'path');
        path.setAttribute('d', d);
        path.setAttribute('fill', 'black');
        svg.append(path);
        el.replaceChildren(svg);
    } catch {
        el.textContent = 'QR kód se nepodařilo zobrazit. Použijte ruční zadání klíče níže.';
        el.setAttribute('role', 'status');
    }
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderMfaQr, { once: true });
} else {
    renderMfaQr();
}
