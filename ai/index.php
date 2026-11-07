<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
[$db, $cfg, $private] = ai_bootstrap();
ai_user_id();
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>AI kouč schůzek · Můj obchod</title>
<style>
:root{color-scheme:light;font-family:system-ui,-apple-system,Segoe UI,sans-serif;--green:#123e36;--cream:#f8f5ec;--gold:#d0ae62;--line:#e3ded0;--ink:#203932}
*{box-sizing:border-box}body{margin:0;background:var(--cream);color:var(--ink)}
header{background:var(--green);color:white;padding:20px max(20px,calc((100vw - 1200px)/2));display:flex;gap:20px;justify-content:space-between;align-items:center;flex-wrap:wrap}
header a{color:#ffe0a1;text-decoration:none}main{max-width:1200px;margin:24px auto;padding:0 16px}
.hint{color:#64756c}.layout{display:grid;grid-template-columns: minmax(260px,350px) 1fr;gap:18px}
.card{background:white;border:1px solid var(--line);border-radius:16px;padding:20px;box-shadow:0 4px 18px #182f230b}
button{font:inherit;border:0;border-radius:10px;padding:10px 13px;cursor:pointer;background:var(--green);color:#fff;min-height:42px}
button.alt{background:var(--gold);color:#142e27}button:disabled{opacity:.5;cursor:not-allowed}
textarea{width:100%;min-height:180px;padding:14px;border:1px solid #b8c5be;border-radius:10px;font:inherit;resize:vertical;line-height:1.5}
#review{min-height:260px;background:#f5f7f3}label{display:block;margin:14px 0 8px;font-weight:650}
.controls{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}.row{border-bottom:1px solid var(--line);padding:9px 0}
.item{display:block;width:100%;text-align:left;background:#f7f7f1;color:#203932;border:1px solid #e5e5dc;margin:0 0 9px}
.item.selected{background:#ebf3e9;border-color:#49806d}.item small{display:block;color:#5a6f65;margin-top:3px}
.check{display:flex;align-items:flex-start;gap:10px;font-weight:normal;padding:12px;background:#f5f3e9;border:1px solid var(--line);border-radius:10px}
.check input{margin-top:5px;flex:none}audio{max-width:100%;width:100%}#status{padding:10px 0;min-height:32px;font-weight:600}
#status.error{color:#a4312d}#status.ok{color:#187343}.hidden{display:none}.stats{font-size:.9rem}
@media(max-width:760px){.layout{grid-template-columns:1fr}header{padding:18px}main{margin-top:14px}}
</style>
<script src="client.js" defer></script>
</head>
<body>
<header>
<div><strong style="font-size:1.35rem">🎙️ AI kouč schůzek</strong><div style="opacity:.78">Můj obchod · Jiří Janoušek</div></div>
<a href="../index.php">← Zpět do Můj obchod</a>
</header>
<main>
<p class="hint">Přepis nahrávky, shrnutí potřeb klienta, obchodní zpětná vazba a návrh dalšího kroku. AI pracuje pouze s vybraným zápisem.</p>
<div class="layout">
<aside class="card">
<h2 style="margin-top:0">Moje schůzky</h2>
<p class="hint stats" id="usage">Načítání...</p>
<div id="meetings"></div>
</aside>
<section class="card" id="detail">
<h2 id="meeting-title" style="margin-top:0">Vyber schůzku</h2>
<p class="hint" id="meeting-meta">Po kliknutí uvidíš nahrávku a přepis.</p>
<div id="detail-content" class="hidden">
<div id="audio-box" class="hidden">
<label>Přehrát nahrávku</label>
<audio id="player" controls preload="metadata"></audio>
<p class="hint">Přepis podporuje MP3, M4A, MP4, WebM a WAV, maximálně 25 MB.</p>
</div>
<label for="transcript">Přepis schůzky</label>
<textarea id="transcript" maxlength="120000" placeholder="Sem lze také vložit vlastní přepis."></textarea>
<div class="controls">
<button id="save">💾 Uložit přepis</button>
<button id="transcribe" class="alt">🎙️ AI přepis nahrávky</button>
</div>
<label class="check"><input id="legal-confirm" type="checkbox"> <span>Potvrzuji, že mám oprávnění nahrávku a přepis zpracovat, účastníci byli odpovídajícím způsobem informováni a znám právní titul pro předání do externí AI služby.</span></label>
<div class="controls"><button id="analyze" class="alt">✨ AI vyhodnocení schůzky</button></div>
<label for="review">AI zpětná vazba</label>
<textarea id="review" readonly placeholder="Výsledek analýzy se zobrazí zde."></textarea>
<p class="hint">Výstup AI je orientační, vyžaduje lidskou kontrolu a není investičním doporučením.</p>
</div>
<div role="status" aria-live="polite" id="status"></div>
</section>
</div>
</main>
</body>
</html>
