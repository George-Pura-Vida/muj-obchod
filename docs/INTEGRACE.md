# Můj obchod → OpenAI API: nasazení modulu AI kouč

**Připraveno 7. 11. 2026.** Cíl: `George-Pura-Vida/muj-obchod`, návrhová větev `feature/ai-kouc-openai-api`, základ `main`, PHP 8.2+, SQLite, WebKitty. Tento balíček je **hotový návrh souborů pro instalaci**. Tento kód je připravený jako návrh v samostatné GitHub větvi a Pull Requestu; bez sloučení do `main` a bez nasazení na WebKitty. Živé API a přehrávání na hostingu nebylo bez přístupu a klíče možné ověřit.

## 1. Součásti

| Soubor | Umístění v repozitáři | Úloha |
| --- | --- | --- |
| `ai/index.php` | `ai/index.php` | Stránka AI kouče dostupná jen přihlášenému uživateli |
| `ai/client.js` | `ai/client.js` | Výběr schůzky, přehrávání, přepis, analýza, ukládání |
| `ai/api.php` | `ai/api.php` | Vlastnická kontrola, CSRF, měsíční kvóty, databáze, OpenAI volání |
| `ai/lib.php` | `ai/lib.php` | Opakovaně použitelný zabezpečený backend pro AI funkce |
| `ai/.htaccess` | `ai/.htaccess` | Zákaz přímého čtení pomocných souborů na Apache |
| `docs/ai-secrets.example.php` | jen šablona | Konfigurace bez tajného klíče |
| `tools/patch-menu.py` | lokální nástroj | Vloží odkaz do stávajícího menu `index.php` |

Backend zatím **sdílí infrastrukturu uvnitř Můj obchod**. Pro více samostatných aplikací doporučuji později vybudovat centrální AI gateway se samostatnou autentizací mezi službami a oddělenou identitou tenantů. Nepřipojuj další aplikace přímým sdílením CRM SQLite databáze.

## 2. Bezpečné nastavení OpenAI

1. Otevři https://platform.openai.com/api-keys, vytvoř samostatný projekt pro `muj-obchod` a servisní účet s potřebnými oprávněními. Na účtu musí být aktivované API účtování. Před prvním ostrým použitím ověř v dashboardu dostupnost modelů `gpt-transcribe` a `gpt-6.1-terra`.
2. V nastavení projektu nastav **hard spend limit** a upozornění na útratu (např. pro pilot 10 USD/měsíc – je to návrh, ne výchozí fakt OpenAI).
3. Na **WebKitty mimo `public_html`/webový kořen** otevři adresář `../moje-schuzka-data/`, kde je už `database.sqlite` a složka `recordings/`.
4. Zkopíruj pouze vzor `docs/ai-secrets.example.php` na server jako `../moje-schuzka-data/ai-secrets.php`. Doplň svůj skutečný API klíč výhradně **na serveru**. Alternativně nastav serverovou environmentální proměnnou `OPENAI_API_KEY`. Konfigurace v privátní složce má mít oprávnění dostupné pouze PHP procesu (typicky 0600).
5. **Nikdy** nevkládej klíč do `config.example.php`, klientského JavaScriptu, HTML, veřejného repozitáře ani sem do chatu. Pokud klíč někdy unikne, rotuj ho.

### Příklad konfigurace na serveru (bez skutečného klíče)

```php
<?php
return [
  'openai_api_key' => 'VLOZ_POUZE_NA_SERVER',
  'analysis_model' => 'gpt-6.1-terra',
  'monthly_transcriptions' => 20,
  'monthly_analyses' => 40,
  'daily_transcriptions' => 5,
  'daily_analyses' => 10,
  'max_audio_bytes' => 25 * 1024 * 1024,
];
```

## 3. Nasazení do GitHubu a WebKitty

1. Na GitHubu vytvoř pracovní větev např. `feature/ai-meeting-coach` z `main`.
2. Přidej celý adresář `ai/` z balíčku do kořene repozitáře. **Neměň existující** `api.php`, `app.js`, SQLite databázi ani nahrávky.
3. Do `index.php` mezi navigační tlačítka vlož odkaz:

```html
<a class="nav-item" style="text-decoration:none" href="ai/index.php">✨ AI kouč schůzek</a>
```

   Pokud máš repo lokálně a Python, můžeš místo ruční úpravy spustit `python tools/patch-menu.py index.php`. Skript vyžaduje přesnou původní řádku a při odlišné verzi raději skončí bez úprav.
4. Ulož změny, vytvoř Pull Request a po kontrole sjednoť do `main`. Na WebKitty nasaď novou větev nebo po sloučení aktuální `main`.
5. Otevři `https://muj-obchod.jirijanousek.cz/ai/index.php` po **přihlášení do hlavní aplikace**.
6. Zkontroluj PHP rozšíření `pdo_sqlite`, `curl`, `fileinfo`, HTTPS a práva privátního adresáře. Pro zpracování delších souborů ověř `max_execution_time` (ideálně alespoň 180 s), příslušné časové limity hostingu a reverzního proxy. Pro nahrávky musí být nastaveno přiměřené `post_max_size` a `upload_max_filesize`; vlastní AI limit je 25 MB. V této verzi je AI požadavek synchronní, takže hosting ho může předčasně ukončit. Pro komerční provoz doporučuji frontu úloh a background worker.
7. Otestuj na **syntetické nebo anonymizované testovací nahrávce**, ne na skutečných klientských datech.

## 4. Testovací scénář

- [ ] Po odhlášení `ai/index.php` ani `ai/api.php` nevrací soukromá data.
- [ ] Při platném přihlášení se zobrazí dříve uložené schůzky a vlastní nahrávky.
- [ ] Přehrávání audio přes původní `api.php?action=media&id=...` funguje na desktopu i na telefonu.
- [ ] MP3/WebM do 25 MB se přepíše, český přepis se uloží do pole `activities.transcript`.
- [ ] AI vyhodnocení se uloží do `activities.review` a zobrazí silné stránky, námitky, potřeby a follow-up.
- [ ] Bez právního potvrzení a platného CSRF tokenu AI volání neproběhne.
- [ ] Při neplatném nebo chybějícím API klíči se zobrazí srozumitelná chyba bez vyzrazení klíče.
- [ ] Uživatel nevidí data jiného uživatele ani při ruční změně ID schůzky/nahrávky.
- [ ] Limit 20 přepisů / 40 analýz za měsíc a denní limity blokují další volání. Do limitů se počítají **pokusy**, včetně neúspěšných, což brání nekonečným opakováním.
- [ ] `ai-secrets.php` ani SQLite databáze nejsou přes HTTP dostupné.
- [ ] Původní funkce Můj obchod zůstaly beze změny.

### Co nyní hotové není

- Reálný test proti OpenAI s účtovaným klíčem a test aktuální verze WebKitty.
- Spolehlivé rozlišení jednotlivých mluvčích. Přepis v tomto modulu nemá speaker diarization; hodnocení proto nepředstírá jistotu, kdo co řekl.
- Automatické mazání dat/retence a šifrování databáze, podrobná práva nebo audity.
- Samoobslužná registrace zákazníků, fakturace a organizace/tenanti. Současný Můj obchod má **jen jeden účet**. Databázové kvóty na uživatele jsou připravené na pozdější rozšíření, ale **neznamenají připravený SaaS**.
- Přesné měření dolarových nákladů za transkripci v lokální databázi. Model účtuje podle délky zvuku, která se z této API odpovědi spolehlivě nevrací. Finální účtování sleduj v OpenAI Usage. Textová analýza se odhaduje z použitých tokenů.

## 5. Orientační náklady – model 7. 11. 2026

Zdroj https://developers.openai.com/api/docs/pricing . Standardní sazby:

- `gpt-transcribe`: **0,0045 USD / minutu zvuku**.
- `gpt-6.1-terra`: **1 USD / milion vstupních tokenů** a **5 USD / milion výstupních tokenů**.

Příklad **30minutové schůzky** s analýzou 12 000 vstupních a 1 500 výstupních tokenů:

- Přepis: 30 × 0,0045 = **0,135 USD**.
- Analýza: 12 000 / 1 000 000 × 1 + 1 500 / 1 000 000 × 5 = **0,0195 USD**.
- **Celkem cca 0,1545 USD na schůzku**; 100 schůzek cca **15,45 USD**. Jde o scénář, ne garantovaný účet. Skutečná délka, počet tokenů, ceny, model a další režijní náklady se liší.

### Návrh budoucích cenových plánů (bez DPH)

| Plán | Měsíční cena | Počet přepisů | Počet analýz |
| --- | ---: | ---: | ---: |
| Osobní | 590 Kč | 10 | 20 |
| Profesionál | 1 190 Kč | 30 | 60 |
| Tým | 2 490 Kč | 70 celkem | 140 celkem |

**Pozor:** stávající implementace omezuje *uživatele*, nikoliv organizaci. Aby šly výše uvedené plány skutečně prodávat, je nutné doplnit multi-tenant databázi, ověřené přihlášení, správu týmů, celkové týmové limity, platební bránu a zpracovatelské smlouvy.

## 6. Soukromí a bezpečnost

- Hlas a přepis může obsahovat osobní i citlivé informace. Oprávnění k pořízení nahrávky, informování účastníků a právní titul ke zpracování je nutné ověřit **před** odesláním. Checkbox není sám o sobě náhradou zákonného postupu.
- Před komerčním provozem nastav retenční politiku pro nahrávky a přepisy, jejich bezpečné mazání, export, zálohy, šifrování, přístupové role a incident response.
- Responses API posílá `store=false`, ale to samo o sobě není Zero Data Retention. Standardní abuse-monitoring logy OpenAI mohou být uchovávány až 30 dní, pokud neplatí jiný schválený režim. Viz https://developers.openai.com/api/docs/guides/your-data .
- Data poslaná přes OpenAI API nejsou standardně využívána k trénování modelů bez opt-in. Před platícím SaaS vyhodnoť subdodavatele, smluvní vztahy a pravidla ochrany osobních údajů.
- Nepovol veřejnou registraci před zavedením izolace tenantů; samotný stávající `users` a relace nestačí pro komerční multi-user provoz.

## 7. Další krok po ověření MVP

Zachovat jednotnou vrstvu OpenAI dotazů, ale vyčlenit ji do vlastního backendu s API autentizací mezi aplikacemi, `tenant_id`, auditními logy, frontou asynchronních úloh, per-tenant účetními kvótami a idempotentními joby. Teprve potom připojovat Můj kmen, Můj DIP, Moje renta a Moje krypto.
