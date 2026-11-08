<?php
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-cache');
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#103e37"><meta name="robots" content="noindex,nofollow">
<title>Moje schůzka · Jiří Janoušek</title>
<meta name="description" content="Od prvního kontaktu k dlouhodobému vztahu.">
<link rel="icon" type="image/svg+xml" href="favicon.svg?v=20261107jj2">
<meta name="application-name" content="Moje schůzka">
<meta name="apple-mobile-web-app-title" content="Moje schůzka">
<link rel="stylesheet" href="style.css?v=20261108-admin1">
</head>
<body>
<svg class="svg-defs" xmlns="http://www.w3.org/2000/svg"><defs>
<symbol id="i-home" viewBox="0 0 24 24"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 21v-9h6v9"/></symbol>
<symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 4a4 4 0 0 1 0 8m1 3a6 6 0 0 1 4 6"/></symbol>
<symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18M7 14h3m4 0h3"/></symbol>
<symbol id="i-file" viewBox="0 0 24 24"><path d="M6 3h9l5 5v13H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M14 3v6h6M8 14h8M8 18h6"/></symbol>
<symbol id="i-heart" viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 1 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8z"/></symbol>
<symbol id="i-phone" viewBox="0 0 24 24"><path d="M7 3H4a2 2 0 0 0-2 2c0 9.4 7.6 17 17 17a2 2 0 0 0 2-2v-3l-6-2-2 3a16 16 0 0 1-7-7l3-2z"/></symbol>
<symbol id="i-coins" viewBox="0 0 24 24"><circle cx="12" cy="6" r="4"/><path d="M2 12c0 2 4 4 10 4s10-2 10-4M2 12v5c0 2 4 4 10 4s10-2 10-4v-5"/></symbol>
<symbol id="i-chart" viewBox="0 0 24 24"><path d="M4 21V4m0 17h17"/><path d="M7 16l4-5 4 3 6-8"/></symbol>
<symbol id="i-mic" viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="13" rx="3"/><path d="M5 10a7 7 0 0 0 14 0M12 17v5m-4 0h8"/></symbol>
<symbol id="i-settings" viewBox="0 0 24 24"><path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="2.5" fill="currentColor"/><circle cx="15" cy="17" r="2.5" fill="currentColor"/></symbol>
<symbol id="i-lock" viewBox="0 0 24 24"><rect x="6" y="10" width="12" height="10" rx="2"/><path d="M9 10V7a3 3 0 0 1 6 0v3"/></symbol>
<symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>
<symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 4v16M4 12h16"/></symbol>
<symbol id="i-arrow" viewBox="0 0 24 24"><path d="M4 12h16m-7-7 7 7-7 7"/></symbol>
<symbol id="i-close" viewBox="0 0 24 24"><path d="M5 5l14 14M19 5 5 19"/></symbol>
<symbol id="i-gift" viewBox="0 0 24 24"><rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 12h18M12 8s-8 0-8-4 8-2 8 4zm0 0s8 0 8-4-8-2-8 4z"/></symbol>
<symbol id="i-branch" viewBox="0 0 24 24"><circle cx="5" cy="5" r="3"/><circle cx="19" cy="5" r="3"/><circle cx="12" cy="19" r="3"/><path d="m7 8 4 8M17 8l-4 8"/></symbol>
<symbol id="i-check" viewBox="0 0 24 24"><path d="m4 12 5 5L20 6"/></symbol>
<symbol id="i-download" viewBox="0 0 24 24"><path d="M12 3v13m-5-5 5 5 5-5M4 18v3h16v-3"/></symbol>
<symbol id="i-upload" viewBox="0 0 24 24"><path d="M12 20V7m-5 5 5-5 5 5M4 6V3h16v3"/></symbol>
<symbol id="i-search" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/></symbol>
<symbol id="i-sparkle" viewBox="0 0 24 24"><path d="m12 2 2.6 7.4L22 12l-7.4 2.6L12 22l-2.6-7.4L2 12l7.4-2.6z"/></symbol>
<symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 2 21 6v6c0 5-3 8-9 10-6-2-9-5-9-10V6zM8 12l3 3 5-6"/></symbol>
</defs></svg>
<div id="boot" class="boot">Připravuji tvůj pracovní prostor…</div>
<section id="auth" class="auth" hidden>
<div class="auth-visual"><span class="brand-badge">jj</span><div class="auth-visual-copy"><span class="tiny-overline">DOBRÝ OBCHOD ZAČÍNÁ VZTAHEM</span><h1>Každá schůzka<br><em>má svůj příběh.</em></h1><p>Všechno na jednom místě. Jednoduše, přehledně a s radostí.</p><div class="auth-pill">📞 Kontakt <span>→</span> 🤝 Schůzka <span>→</span> ✍️ Smlouva <span>→</span> 💚 Péče</div></div></div>
<div class="auth-panel"><div class="auth-panel-inner"><div class="brand-mini"><span class="brand-badge">jj</span><span>moje <b>schůzka</b></span></div><span class="eyebrow">VÍTEJ ZPĚT</span><h2 id="auth-title">Přihlášení</h2><p id="auth-description">Tvůj soukromý obchodní prostor.</p><form id="auth-form"><label>E-mail<input name="email" type="email" autocomplete="username" placeholder="vas@email.cz" required></label><label>Heslo<input name="password" type="password" autocomplete="current-password" placeholder="••••••••••••" required></label><label id="setup-row" hidden>Jednorázový instalační klíč<input name="setup_key" type="password" autocomplete="off" placeholder="Klíč nastavený na WebKitty"></label><button class="button primary wide" type="submit" id="auth-submit">Přihlásit se <svg><use href="#i-arrow"/></svg></button><p class="error" id="auth-error" role="alert"></p></form><button type="button" class="text-link" id="forgot-password" hidden>Zapomenuté heslo?</button>
<form id="reset-request-form" hidden>
  <label>Přihlašovací e-mail<input name="email" type="email" autocomplete="username" maxlength="254" required></label>
  <button class="button primary wide">Poslat odkaz</button>
</form>
<form id="reset-redeem-form" hidden>
  <label>Nové heslo<input name="password" type="password" autocomplete="new-password" minlength="12" required></label>
  <label>Nové heslo znovu<input name="confirm" type="password" autocomplete="new-password" minlength="12" required></label>
  <button class="button primary wide">Nastavit heslo</button>
</form>
<button type="button" class="text-link" id="reset-back" hidden>← Zpět k přihlášení</button>
<p id="reset-status" class="settings-status" role="status"></p>
<p id="auth-help" class="muted small">Přístup k pracovnímu prostoru ti založí správce.</p></div></div>
</section>
<div id="app" class="app" hidden>
<div class="shade" id="shade" hidden></div>
<aside id="sidebar" class="sidebar"><div class="nav-brand"><span class="brand-badge">jj</span><div>moje <strong>schůzka</strong><small>Jiří Janoušek</small></div></div>
<span class="menu-label">TVŮJ PRACOVNÍ PROSTOR</span>
<nav id="side-nav" class="side-nav">
<button class="nav-item active" data-view="overview"><svg><use href="#i-home"/></svg> Přehled</button>
<button class="nav-item" data-view="people"><svg><use href="#i-users"/></svg> Lidé <span id="side-count"></span></button>
<button class="nav-item" data-view="day"><svg><use href="#i-calendar"/></svg> Můj den</button>
<button class="nav-item" data-view="care"><svg><use href="#i-heart"/></svg> Péče a doporučení</button>
<button class="nav-item" data-view="results"><svg><use href="#i-chart"/></svg> Výsledky</button>
<button class="nav-item" data-view="coaching"><svg><use href="#i-check"/></svg> Můj trénink</button>
<button class="nav-item" data-view="team"><svg><use href="#i-branch"/></svg> Tým a spolupráce</button>
</nav>
<div class="side-bottom"><div class="motivation">Malé kroky.<br><strong>Velké vztahy.</strong><span>✦</span></div><button class="subtle-action" id="import-action"><svg><use href="#i-upload"/></svg> Import kontaktů</button><button class="subtle-action" id="export-action"><svg><use href="#i-download"/></svg> Export Excel / CSV</button><button class="subtle-action" id="backup-action"><svg><use href="#i-download"/></svg> Záloha zápisů JSON</button><button class="subtle-action" id="user-settings-open" data-view="settings"><svg><use href="#i-settings"/></svg> Nastavení uživatele</button><div class="side-profile"><div class="avatar" id="profile-avatar">JJ</div><div><strong id="profile-name">Jiří Janoušek</strong><small id="profile-role">Osobní účet</small></div><button id="logout" title="Odhlásit se">↪</button></div></div></aside>
<div class="main-wrap"><header class="topbar"><button class="hamburger" id="hamburger" aria-label="Otevřít nabídku" aria-expanded="false"><svg><use href="#i-menu"/></svg></button><div class="top-brand-mobile">moje <b>schůzka</b></div><div class="top-breadcrumb">PRACOVNÍ PROSTOR <span>/</span> <strong id="crumb">Přehled</strong></div><div class="header-right"><span class="date" id="date-now"></span><button class="avatar avatar-small header-settings" id="header-avatar" data-view="settings" type="button" title="Nastavení uživatele" aria-label="Nastavení uživatele">JJ</button></div></header>
<main class="content">
<section class="view" id="overview-view">
<div class="page-heading"><div><span class="eyebrow">OBCHODNÍ PŘEHLED</span><h1>Přehled vztahů</h1><p>Klientská péče, doporučení, spolupráce a nejbližší kroky na jednom místě.</p></div><button class="button primary" data-process="contact" data-action="contact"><svg><use href="#i-plus"/></svg> Nový kontakt</button></div>
<div id="relationship-priorities" class="relationship-priorities"></div>
<div class="section-label"><h2>Obchodní postup</h2><button class="button outline" data-process="contract" data-action="opportunity">+ Další příležitost</button></div>
<div class="journey" id="journey">
<button class="journey-tile" data-process="contact" data-action="contact"><span class="journey-icon green"><svg><use href="#i-phone"/></svg></span><span class="journey-step">01 • ZAČÁTEK</span><strong>Kontakt</strong><small>Oslov někoho nového</small><span class="journey-add">+</span></button>
<button class="journey-tile" data-process="meeting" data-action="activity"><span class="journey-icon blue"><svg><use href="#i-calendar"/></svg></span><span class="journey-step">02 • SETKÁNÍ</span><strong>Schůzka</strong><small>Zapiš, co se povedlo</small><span class="journey-add">+</span></button>
<button class="journey-tile" data-process="contract" data-action="contract"><span class="journey-icon goldish"><svg><use href="#i-file"/></svg></span><span class="journey-step">03 • VÝSLEDEK</span><strong>Smlouva</strong><small>Produkt, provize a termín</small><span class="journey-add">+</span></button>
<button class="journey-tile" data-process="care" data-action="care"><span class="journey-icon pink"><svg><use href="#i-heart"/></svg></span><span class="journey-step">04 • VZTAH</span><strong>Péče</strong><small>Vrať se, poděkuj, doporuč</small><span class="journey-add">+</span></button>
<button class="journey-tile" data-process="referral" data-action="referral"><span class="journey-icon"><svg><use href="#i-gift"/></svg></span><span class="journey-step">05 • PŘEDSTAVENÍ</span><strong>Doporučení</strong><small>Zapiš nové doporučení</small><span class="journey-add">+</span></button>
<button class="journey-tile" data-process="team" data-view="team"><span class="journey-icon"><svg><use href="#i-branch"/></svg></span><span class="journey-step">06 • SPOLUPRÁCE</span><strong>Tým</strong><small>Příležitosti a spolupracovníci</small><span class="journey-add">→</span></button>
</div>
<div class="kpi-grid" id="kpi-grid"></div>
<div class="two-col"><article class="panel"><div class="panel-head"><div><span class="eyebrow">CO JE DNES DŮLEŽITÉ</span><h2><svg class="h-icon"><use href="#i-calendar"/></svg> Můj další krok</h2></div><button class="text-link" data-view="day">Všechny úkoly →</button></div><div id="next-up"></div></article>
<article class="panel warm"><div class="panel-head"><div><span class="eyebrow">TVÉ ÚSPĚCHY</span><h2><svg class="h-icon"><use href="#i-coins"/></svg> Provize a příležitosti</h2></div><button class="text-link" data-view="results">Více →</button></div><div id="money-overview"></div></article></div>
</section>
<section class="view" id="people-view" hidden><div class="page-heading"><div><span class="eyebrow">TVOJE KONTAKTY A KLIENTI</span><h1>Lidé a vztahy</h1><p>Všechno důležité o člověku na jedné kartě.</p></div><button class="button primary" data-action="contact"><svg><use href="#i-plus"/></svg> Nový člověk</button></div><div class="filter-bar"><label class="searchbox"><svg><use href="#i-search"/></svg><input id="people-search" placeholder="Jméno, telefon, e-mail…"></label><select id="people-stage"><option value="">Všechny fáze</option><option value="contact">Kontakt</option><option value="phone">Telefon</option><option value="meeting1">1. schůzka</option><option value="meeting2">Další schůzky</option><option value="contract">Smlouva</option><option value="care">Péče</option></select></div><div id="people-list" class="people-list"></div></section>
<section class="view" id="day-view" hidden><div class="page-heading"><div><span class="eyebrow">PŘEHLED BEZ STRESU</span><h1>Můj den</h1><p>Co mám udělat dnes a co mě čeká dál?</p></div><button class="button primary" data-action="activity"><svg><use href="#i-plus"/></svg> Nový zápis</button></div><div class="two-col"><article class="panel"><div class="panel-head"><h2>📅 Naplánované kroky</h2></div><div id="day-tasks"></div></article><article class="panel"><div class="panel-head"><h2>🎙️ Poslední zápisy a nahrávky</h2></div><div id="day-entries"></div></article></div></section>
<section class="view" id="care-view" hidden>
  <div class="page-heading"><div><span class="eyebrow">NÁSLEDNÁ PÉČE A DOPORUČENÍ</span><h1>Péče a doporučení</h1><p>Naplánované kontakty, úkoly po termínu a získaná doporučení.</p></div><div class="page-actions">
    <button class="button primary" data-process="care" data-action="care"><svg><use href="#i-heart"/></svg> Zapsat péči</button>
    <button class="button outline" data-process="referral" data-action="referral"><svg><use href="#i-gift"/></svg> Nové doporučení</button>
  </div></div>
  <div class="two-col">
    <article class="panel process-panel" data-process="referral"><div class="panel-head"><h2>Péče po termínu</h2></div><div id="care-overdue"></div></article>
    <article class="panel process-panel" data-process="care"><div class="panel-head"><h2>Naplánovaná péče</h2></div><div id="care-upcoming"></div></article>
    <article class="panel process-panel" data-process="referral"><div class="panel-head"><h2>Další kroky k doporučením</h2></div><div id="referral-tasks"></div></article>
    <article class="panel process-panel" data-process="referral"><div class="panel-head"><h2>Získaná doporučení</h2></div><div id="referral-register"></div></article>
  </div>
</section>
<section class="view" id="results-view" hidden><div class="page-heading"><div><span class="eyebrow">KAŽDÝ KROK MÁ SMYSL</span><h1>Moje výsledky</h1><p>Čísla, která dávají smysl. Bez složitého CRM.</p></div><button class="button outline" id="export-results"><svg><use href="#i-download"/></svg> Exportovat data</button></div><div class="insight-heading"><div><h2>Rozvoj vztahů</h2><p class="muted">Období platí pro zapsané aktivity a doporučení. Pokrytí péčí a složení týmu ukazují dnešní stav.</p></div>
<label>Období <select id="relationship-period"><option value="30">Posledních 30 dní</option><option value="90" selected>Posledních 90 dní</option><option value="180">Posledních 180 dní</option></select></label></div>
<article class="panel process-panel" data-process="meeting"><div class="panel-head"><h2>Péče, doporučení a tým v čase</h2></div><div class="insight-body" id="relationship-trend"></div></article>
<div class="insight-grid">
  <article class="panel process-panel" data-process="care"><div class="panel-head"><h2>Pokrytí klientů péčí</h2></div><div class="insight-body" id="relationship-coverage"></div></article>
  <article class="panel process-panel" data-process="referral"><div class="panel-head"><h2>Oslovení u doporučení</h2></div><div class="insight-body" id="referral-metrics"></div></article>
  <article class="panel process-panel" data-process="team"><div class="panel-head"><h2>Fáze spolupráce</h2></div><div class="insight-body" id="team-metrics"></div></article>
</div>
<div class="insight-heading"><h2>Obchod a provize</h2></div>
<div id="results-kpis" class="kpi-grid"></div><div class="two-col"><article class="panel"><div class="panel-head"><h2>Jak se daří obchodu</h2></div><div id="funnel"></div></article><article class="panel"><div class="panel-head"><h2>Provize podle stavu</h2></div><div id="commissions"></div></article></div><div class="two-col"><article class="panel"><div class="panel-head"><h2>📈 Vývoj schůzek a smluv</h2></div><div id="trend-chart"></div></article><article class="panel"><div class="panel-head"><h2>🌱 Křížový prodej</h2><button class="text-link" data-action="opportunity">Přidat →</button></div><div id="crosssell-list"></div></article></div><article class="panel" style="margin-top:18px"><div class="panel-head"><h2>💰 Kdy přijde provize</h2></div><div id="commission-list"></div></article></section>
<section class="view" id="coaching-view" hidden>
  <div class="page-heading"><div><span class="eyebrow">ROZVOJ OBCHODNÍCH DOVEDNOSTÍ</span><h1>Můj trénink</h1><p>Zhodnoť rozhovor, vyber jeden krok ke zlepšení a vyzkoušej ho příště.</p></div></div>
  <div class="two-col">
    <article class="panel process-panel" data-process="training">
      <div class="panel-head"><div><span class="eyebrow">PRAKTICKÝ NÁCVIK</span><h2>Simulace rozhovoru</h2></div></div>
      <form id="practice-form" class="insight-body practice-form">
        <label>Scénář <select id="practice-scenario"></select></label>
        <div class="scenario-situation" id="practice-situation"></div>
        <label>Tvoje odpověď <textarea id="practice-response" required maxlength="2000" rows="4" placeholder="Napiš, co bys řekl vlastními slovy."></textarea></label>
        <div id="practice-feedback" class="practice-feedback" hidden></div>
        <p class="error" id="practice-error" role="alert"></p>
        <div class="page-actions"><button class="button outline" id="practice-reveal" type="button">Porovnat s příkladem</button><button class="button primary" id="practice-save" type="submit" disabled>Uložit nácvik</button></div>
      </form>
    </article>
    <article class="panel process-panel" data-process="training"><div class="panel-head"><h2>Historie nácviku</h2></div><div class="insight-body" id="practice-history"></div></article>
  </div>
  <div class="two-col">
    <article class="panel"><div class="panel-head"><h2>📈 Pokrok podle dovednosti</h2></div><div class="coaching-body" id="coaching-progress"></div></article>
    <article class="panel warm">
      <div class="panel-head"><h2>🎯 Co si nacvičit</h2></div>
      <div class="coaching-body">
        <div class="practice-picker" id="coaching-focus-picker" role="group" aria-label="Fáze k nácviku"></div>
        <div class="practice-card" id="coaching-focus" aria-live="polite"></div>
      </div>
    </article>
  </div>
  <article class="panel coaching-history"><div class="panel-head"><h2>Poslední schůzky a telefonáty</h2></div><div id="coaching-activities"></div></article>
</section>
<section class="view" id="team-view" hidden>
  <div class="page-heading"><div><span class="eyebrow">LIDÉ A SPOLEČNÁ PRÁCE</span><h1>Tým a spolupráce</h1><p>Od prvního rozhovoru přes zaškolení až po průběžnou práci s týmem.</p></div><button class="button primary" data-action="contact" data-team-intake="1">+ Kontakt pro spolupráci</button></div>
  <div class="team-columns" id="team-people"></div>
  <article class="panel coaching-history"><div class="panel-head"><h2>📅 Další kroky v týmu a spolupráci</h2></div><div id="team-tasks"></div></article>
</section>
<section class="view settings-shell" id="settings-view" aria-label="Nastavení uživatele" hidden>
  <aside class="settings-sidebar">
    <div class="settings-brand"><svg aria-hidden="true"><use href="#i-settings"/></svg><strong>Nastavení uživatele</strong></div>
    <div class="settings-profile">
      <div class="avatar" id="settings-avatar" aria-hidden="true">JJ</div>
      <strong id="settings-account-name">Jiří Janoušek</strong>
      <small id="settings-role">Osobní účet</small>
    </div>
    <nav aria-label="Sekce nastavení">
      <span class="settings-nav-label">OSOBNÍ ÚČET</span>
      <button type="button" data-settings-tab="profile"><svg aria-hidden="true"><use href="#i-users"/></svg> Základní údaje</button>
      <button type="button" data-settings-tab="password">Změna hesla</button>
      <span class="settings-nav-label">PRACOVNÍ PROSTŘEDÍ</span>
      <button type="button" data-settings-tab="menu" aria-current="page"><svg aria-hidden="true"><use href="#i-menu"/></svg> Nastavení menu</button>
      <span class="settings-nav-label" data-admin-only hidden>ADMINISTRACE</span>
      <button type="button" data-settings-tab="appearance" data-admin-only hidden>Vzhled a názvy menu</button>
      <button type="button" data-settings-tab="users" data-admin-only hidden>Uživatelé a oprávnění</button>
      <button type="button" data-settings-tab="audit" data-admin-only hidden>Audit</button>
    </nav>
  </aside>
  <div class="settings-main">
    <header class="settings-top"><button type="button" class="settings-back" id="settings-back">← Zpět do Moje schůzka</button><button type="button" class="settings-back" id="settings-logout">Odhlásit se</button></header>
    <section class="settings-panel" id="settings-menu-panel">
      <h1 tabindex="-1">Nastavení menu</h1>
      <p>Přetáhni agendy nebo je přesuň šipkami. Přehled zůstává první.</p>
      <form id="settings-menu-form">
        <ol id="settings-menu-list" aria-label="Pořadí agend"></ol>
        <div class="settings-actions">
          <button class="button primary" type="submit" id="settings-menu-save" disabled>Uložit pořadí</button>
          <button class="button outline" type="button" id="settings-menu-reset">Obnovit výchozí</button>
        </div>
        <p class="settings-status" id="settings-menu-status" role="status"></p>
      </form>
    </section>
    <section class="settings-panel" id="settings-profile-panel" hidden>
      <h1 tabindex="-1">Základní údaje</h1>
      <p>Zobrazované jméno se používá v aplikaci.</p>
      <form id="settings-profile-form" class="settings-profile-form">
        <label>Zobrazované jméno<input name="display_name" id="settings-display-name" autocomplete="name" maxlength="80" required></label>
        <label>Přihlašovací e-mail<input id="settings-email" type="email" readonly></label>
        <dl id="settings-account-details" class="account-details"></dl>
        <button class="button primary" type="submit" id="settings-profile-save">Uložit jméno</button>
        <p class="settings-status" id="settings-profile-status" role="status"></p>
      </form>
    </section>
    <section class="settings-panel" id="settings-password-panel" hidden>
      <h1 tabindex="-1">Změna hesla</h1>
      <p>Po změně hesla se ostatní relace odhlásí.</p>
      <form id="settings-password-form" class="settings-profile-form">
        <label>Současné heslo<input name="current_password" type="password" autocomplete="current-password" required></label>
        <label>Nové heslo<input name="password" type="password" autocomplete="new-password" minlength="12" required></label>
        <label>Nové heslo znovu<input name="confirm" type="password" autocomplete="new-password" minlength="12" required></label>
        <button class="button primary">Změnit heslo</button>
        <p class="settings-status" id="settings-password-status" role="status"></p>
      </form>
    </section>
    <section class="settings-panel" id="settings-appearance-panel" hidden>
      <h1 tabindex="-1">Vzhled a názvy menu</h1>
      <p>Změny se v náhledu projeví hned. Po uložení platí pro všechny účty.</p>
      <div class="appearance-preview" id="appearance-preview" aria-label="Živý náhled">
        <aside id="appearance-preview-menu"></aside>
        <div><strong>Ukázka pracovního prostoru</strong><p>Takto bude vypadat text v aplikaci.</p><span>Ukázkové tlačítko</span></div>
      </div>
      <form id="settings-appearance-form" class="admin-form">
        <fieldset><legend>Barvy</legend><div class="admin-grid" id="appearance-colors"></div></fieldset>
        <label>Velikost písma <output id="appearance-font-output">100 %</output>
          <input type="range" name="font_size" min="85" max="130" value="100">
        </label>
        <fieldset><legend>Názvy položek menu</legend><div class="admin-grid" id="appearance-labels"></div></fieldset>
        <div class="settings-actions"><button class="button primary">Uložit vzhled</button>
          <button type="button" class="button outline" id="appearance-defaults">Obnovit výchozí</button></div>
        <p class="settings-status" id="settings-appearance-status" role="status"></p>
      </form>
    </section>
    <section class="settings-panel" id="settings-users-panel" hidden>
      <h1 tabindex="-1">Uživatelé a oprávnění</h1>
      <p>Vyber účet k úpravě nebo přidej nový. Heslo se nastavuje jednorázovým odkazem.</p>
      <div class="settings-actions">
        <label>Účet <select id="admin-account-select"></select></label>
        <button type="button" class="button outline" id="admin-account-new">Nový účet</button>
      </div>
      <form id="admin-account-form" class="admin-form" hidden>
        <div class="admin-grid" id="admin-profile-fields"></div>
        <div class="admin-grid">
          <label>Role <select name="role" required></select></label>
          <label class="check-field"><input name="active" type="checkbox"> Aktivní účet</label>
        </div>
        <p class="muted" id="admin-role-description"></p>
        <div id="admin-permissions">
          <fieldset><legend>Přístup k obrazovkám</legend><div id="admin-screens" class="admin-grid"></div></fieldset>
          <fieldset><legend>Oprávnění k akcím</legend><div id="admin-rights" class="admin-grid"></div></fieldset>
        </div>
        <div class="settings-actions">
          <button class="button primary">Uložit účet</button>
          <button type="button" class="button outline" id="admin-issue-reset" hidden>Vytvořit odkaz k nastavení hesla</button>
        </div>
        <p class="settings-status" id="settings-users-status" role="status"></p>
        <label id="admin-reset-result" hidden>Jednorázový odkaz platný 30 minut. Předej ho vlastníkovi účtu bezpečnou cestou.
          <input id="admin-reset-link" readonly autocomplete="off">
        </label>
      </form>
      <p class="settings-status" id="admin-load-status" role="status"></p>
    </section>
    <section class="settings-panel" id="settings-audit-panel" hidden>
      <h1 tabindex="-1">Audit</h1>
      <p>Posledních 150 událostí, čas v UTC.</p>
      <p class="settings-status" id="settings-audit-status" role="status"></p>
      <div class="admin-audit-table"><table><thead><tr><th>Čas</th><th>Událost</th><th>Provedl</th><th>Účet</th><th>Podrobnosti</th></tr></thead>
        <tbody id="admin-audit-rows"></tbody></table></div>
    </section>
  </div>
</section>
<footer class="site-footer"><strong>moje schůzka</strong> · Jiří Janoušek <span>Soukromá evidence. Záznamy pořizujte jen oprávněně.</span><a href="https://jirijanousek.cz/" target="_blank" rel="noopener">jirijanousek.cz ↗</a></footer>
</main>
</div></div>
<dialog id="form-dialog" class="modal" aria-labelledby="form-title"><div class="modal-top"><div><span class="eyebrow" id="form-context">OBCHODNÍ ZÁPIS</span><h2 id="form-title">Nový zápis</h2></div><button class="x-button" data-close="form-dialog" aria-label="Zavřít"><svg><use href="#i-close"/></svg></button></div><form id="dynamic-form"><div id="form-fields" class="modal-body"></div><div class="modal-footer form-footer"><p class="error" id="form-error" role="alert"></p><button type="button" class="button outline" data-close="form-dialog">Zrušit</button><button type="submit" class="button primary">Uložit <svg><use href="#i-check"/></svg></button></div></form></dialog>
<dialog id="person-dialog" class="modal large"><div class="modal-top"><div><span class="eyebrow">KARTA KLIENTA</span><h2 id="person-title"></h2></div><button class="x-button" data-close="person-dialog" aria-label="Zavřít"><svg><use href="#i-close"/></svg></button></div><div id="person-body" class="modal-body person-body"></div></dialog>
<dialog id="audio-dialog" class="modal"><div class="modal-top"><div><span class="eyebrow">HLASOVÝ ZÁZNAM</span><h2>Nahrát schůzku 🎙️</h2></div><button class="x-button" data-close="audio-dialog" aria-label="Zavřít"><svg><use href="#i-close"/></svg></button></div><div class="modal-body"><p class="muted">Nahrávání funguje přes mikrofon a pouze se souhlasem všech účastníků. Telefonní hovor případně přilož jako soubor.</p><div class="recorder-circle" id="recorder-circle"><svg><use href="#i-mic"/></svg><small id="record-time">00:00</small></div><div class="record-buttons"><button class="button danger" id="start-record">● Spustit záznam</button><button class="button outline" id="stop-record" disabled>■ Zastavit</button></div><audio id="audio-preview" controls hidden></audio><label class="file-label">Nebo přiložit audio (WebM, M4A, MP3, WAV, OGG; do 50 MB)<input id="audio-file" type="file" accept="audio/*,.webm,.m4a,.mp3,.ogg,.wav,.mp4"></label><button class="button primary wide" id="save-audio" disabled>Bezpečně uložit ke schůzce</button><p class="error" id="audio-error"></p></div></dialog>
<dialog id="playback-dialog" class="modal" aria-labelledby="playback-title">
<div class="modal-top"><div><span class="eyebrow">ULOŽENÁ NAHRÁVKA</span><h2 id="playback-title">Přehrát schůzku ▶</h2></div><button class="x-button" data-close="playback-dialog" aria-label="Zavřít"><svg><use href="#i-close"/></svg></button></div>
<div class="modal-body">
<p class="muted">Poslech záznamu je dostupný jen po přihlášení. Pokud formát tvůj prohlížeč nepodporuje, můžeš si nahrávku uložit a otevřít v jiném přehrávači.</p>
<p id="playback-status" class="notice" role="status">Připravuji nahrávku…</p>
<audio id="playback-player" controls preload="metadata" style="display:block;width:100%;margin:17px 0" hidden></audio>
<div class="record-buttons">
<button class="button outline" id="playback-download" disabled>↓ Stáhnout nahrávku</button>
<button class="button primary" id="playback-coach" disabled>✨ Zápis a AI trénink</button>
</div>
<p class="tiny-warning">AI zde zatím nepřepisuje zvuk automaticky. V sekci AI trénink můžeš vložit přepis, zaznamenat zpětnou vazbu a připravit anonymizovaný text k vyhodnocení v ChatGPT.</p>
<p class="error" id="playback-error" role="alert"></p>
</div>
</dialog>
<dialog id="coach-dialog" class="modal"><div class="modal-top"><div><span class="eyebrow">UČÍM SE Z KAŽDÉ SCHŮZKY</span><h2>Trénink a vyhodnocení ✨</h2></div><button class="x-button" data-close="coach-dialog" aria-label="Zavřít"><svg><use href="#i-close"/></svg></button></div><div class="modal-body"><p class="muted">Z nahrávky se zatím automaticky netvoří přepis ani AI analýza. Vlož sem anonymizovaný přepis nebo nejdůležitější věty ze schůzky. Pak klikni na Připravit pro AI kouče a vlož zkopírovaný text do ChatGPT.</p><label>Přepis / důležité věty<textarea id="coach-transcript" rows="7" placeholder="Co řekl klient, jak jsem reagoval, co jsme dohodli…"></textarea></label><label>Moje vlastní hodnocení<textarea id="coach-review" rows="3" placeholder="Co se povedlo a co příště zlepším?"></textarea></label><div class="record-buttons"><button class="button outline" id="coach-scorecard" type="button">Ohodnotit dovednosti</button><button class="button outline" id="coach-save">Uložit poznámky</button><button class="button primary" id="coach-copy">✨ Připravit pro AI kouče</button><button class="button outline" id="coach-open-chatgpt" type="button">Otevřít ChatGPT ↗</button></div><label id="coach-generated" hidden>Text pro ChatGPT – zkontroluj anonymizaci a zkopíruj jej<textarea id="coach-ready-prompt" rows="8" readonly></textarea></label><p class="tiny-warning">Před kopírováním do externí AI odstraň jména, kontakty, citlivé a důvěrné informace; dodrž účel zpracování a oprávnění účastníků.</p><p class="error" id="coach-error"></p></div></dialog>
<dialog id="scorecard-dialog" class="modal" aria-labelledby="scorecard-title">
  <div class="modal-top"><div><span class="eyebrow">SEBEHODNOCENÍ ROZHOVORU</span><h2 id="scorecard-title">Hodnocení schůzky</h2></div><button class="x-button" data-close="scorecard-dialog" aria-label="Zavřít"><svg><use href="#i-close"/></svg></button></div>
  <form id="scorecard-form">
    <div class="modal-body">
      <p class="muted" id="scorecard-context"></p>
      <p class="muted">1 = chybělo, 3 = částečně, 5 = zvládnuto dobře. Co nemůžeš posoudit, ponech bez skóre.</p>
      <div id="scorecard-skills" class="scorecard-skills"></div>
      <label>Co si vyzkouším v příštím rozhovoru?<textarea name="practice" rows="2" maxlength="500" placeholder="Např. na konci společně potvrdím termín a kdo udělá další krok."></textarea></label>
      <p class="error" id="scorecard-error" role="alert"></p>
    </div>
    <div class="modal-footer"><button class="button outline" type="button" data-close="scorecard-dialog">Zrušit</button><button class="button primary" id="scorecard-save" type="submit">Uložit hodnocení</button></div>
  </form>
</dialog>
<input id="import-file" type="file" accept=".csv,.xlsx,.xls" hidden>
<div id="toast" class="toast" role="status" aria-live="polite"></div>
<script src="xlsx.full.min.js?v=1" defer></script>
<script src="conversation-guide.js?v=20261107-settings1" defer></script>
<script src="workspace.js?v=20261107-settings1" defer></script>
<script src="user-settings.js?v=20261108-admin1" defer></script>
<script src="account-admin.js?v=20261108-admin1" defer></script>
<script src="app.js?v=20261108-admin1" defer></script>
</body></html>
