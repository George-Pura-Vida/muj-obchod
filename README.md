# Moje schůzka · obchodní deník

Lehká, responzivní česká aplikace pro telefonování, schůzky, smlouvy, provize, péči, doporučení a křížový prodej.

**Technologie:** čisté HTML, CSS, JavaScript, PHP 8.2+ a SQLite (PDO). Bez buildu, bez Lovable, vhodné pro WebKitty s PHP. Základní rozhraní používá malé barevné karty a čtyři kroky: Kontakt → Schůzka → Smlouva → Péče.

## Nasazení na WebKitty
1. Před aktualizací zazálohuj privátní složku `../moje-schuzka-data/` včetně SQLite a nahrávek. Kvůli konzistentní kopii SQLite během zálohy zastav zápisy nebo použij nástroj pro online zálohu SQLite.
2. Nahraj soubory aplikace z větve `main` do webového kořene. Ponech vlastní `config.local.php` a stávající `../moje-schuzka-data/` na stejném místě. Datová složka musí zůstat zapisovatelná pro PHP a mimo veřejný web.
3. Server potřebuje PHP 8.2+, PDO SQLite, funkční PHP sessions a pro příjem audia rozšíření Fileinfo. API vyžaduje HTTPS a PHP musí dostat příznak `HTTPS=on` (při ukončení TLS před webserverem ho nastav v důvěryhodné konfiguraci serveru). Ověř, že webserver blokuje privátní konfiguraci podle `.htaccess`.
4. U existující instalace se tabulky doplní při prvním požadavku. Původní vlastník získá roli Admin a po aktualizaci se znovu přihlásí svým heslem. ID účtů, záznamy, pořadí menu a soubory nahrávek zůstávají na původním místě.
5. Jen pro prázdnou databázi nastav náhodný instalační klíč alespoň 32 znaků v `config.local.php` podle `config.example.php`. Přes HTTPS vytvoř prvního správce a klíč pak odstraň. Další účty se zakládají v administraci. Soubor s privátní konfigurací nepatří do Gitu.
6. Pro odkaz z e-mailu vyplň `public_url` (kanonická HTTPS adresa této instalace včetně případného podadresáře) a `reset_from` (povolená adresa odesílatele). Ověř funkční PHP `mail()` a doručení zprávy do skutečné schránky. Bez těchto údajů může správce dál vytvořit jednorázový odkaz ručně.
7. Po aktualizaci ověř přihlášení původního účtu, existující záznam a nahrávku, přihlášení účtu Pouze náhled a obnovu hesla, pokud je zapnutá.

## Účty, obrazovky a oprávnění

V **Nastavení uživatele** má každý uživatel vlastní zobrazované jméno, pořadí menu a změnu hesla. Správce zde má navíc vzhled, správu účtů a audit. Barvy, velikost textu a názvy položek menu jsou po uložení společné pro instalaci. Na pozadí, v menu a na hlavních tlačítkách lze upravit samostatné barvy.

- **Admin** má všechna práva včetně správy účtů, vzhledu a auditu.
- **Editor** může používat všechny obrazovky a obchodní akce. Úplnou zálohu lze povolit individuálně.
- **Uživatel** může běžně přidávat kontakty, aktivity, péči, spolupráci, příležitosti a doporučení. Úpravy kontaktů, smlouvy, import a export vyžadují individuální povolení.
- **Pouze náhled** může číst povolené agendy a podle oprávnění přehrát nahrávky. Změnit může vlastní heslo a osobní nastavení.

Pravidla **Podle role / Povolit / Zakázat** se nastavují jednotlivým účtům. Povolení akce zároveň vyžaduje příslušnou obrazovku. Přehledy využívají základní souhrnné údaje a agendy, které pracují s klientem, jeho jméno. Úplná karta kontaktu vyžaduje Lidé, přepis vyžaduje Můj trénink, pravidelná péče a doporučení vyžadují Péči, týmové poznámky vyžadují Tým a finance vyžadují Výsledky. CSV vyžaduje přístup k Lidem a právo exportu. Úplná záloha vyžaduje všechny obrazovky a přístup k nahrávkám. Obchodní záznamy zůstávají oddělené podle vlastníka i pro Admina.

Při založení účtu vystav ve správě jednorázový odkaz k nastavení hesla a předej ho pouze vlastníkovi účtu. Odkaz platí 30 minut. Nový odkaz nahrazuje předchozí. Změna hesla, obnova hesla, deaktivace nebo změna přístupu zneplatní staré relace. Audit eviduje události přihlášení, hesel, účtů, vzhledu a exportu. Časy jsou v UTC.

## Hlas a AI
- Nahrávání schůzek používá mikrofon v prohlížeči přes MediaRecorder, tedy vyžaduje HTTPS, souhlas s mikrofonem a výslovné oprávnění k pořízení záznamu.
- **Nahrávání telefonního hovoru přímo z mobilního prohlížeče není spolehlivě podporováno.** Nahrávku lze do zápisu přiložit ručně.
- Nahrávky se ukládají mimo veřejnou složku a přehrávají přes ověřený PHP endpoint.
- Textový přepis a vyhodnocení se v této verzi zapisují ručně. Tlačítko *Připravit pro AI kouče* vytvoří prompt z tvého přepisu, který můžeš dobrovolně zkopírovat do ChatGPT (bez automatického přenosu dat). **Žádná automatická transkripce ani AI API zatím nejsou připojené.**
- Před nahráváním a odesláním do externí AI řeš informování/souhlas účastníků, právní titul, zabezpečení, dobu uchování a citlivé údaje.

## Export/import
Kontakty: CSV export a import (Excel otevře a uloží CSV UTF-8). V prohlížeči je také podporován XLSX import, pokud se načte volitelná knihovna SheetJS z CDN. Zápisy se zálohují do JSON exportu. Nahrávky nejsou součástí JSON exportu. Neimportuj bez kontroly cizí makra ani škodlivé soubory.

## Ochrana
Hesla se ukládají jako PHP hashe a v databázi je pouze hash jednorázového tokenu. Zápisové požadavky ověřují CSRF. Přihlášení a obnova mají limity pokusů. Relace používají Secure, HttpOnly a SameSite cookies a končí po 30 minutách nečinnosti nebo 12 hodinách od přihlášení. Audit neukládá hesla ani odkazy k obnově.

© 2026 Jiří Janoušek.

## Dobrovolná podpora projektu

Veřejná stránka `support.html` zobrazuje QR platbu s volitelným jednorázovým potvrzením e-mailem a anonymní možností. V přihlašovací obrazovce i postranním panelu je odkaz „Podpořit projekt“. E-mailové požadavky se přes CORS posílají na `https://muj-dip.jirijanousek.cz/donation-intent-cross.php`, kde se ukládá požadavek s unikátním VS. Teprve po bankovním spárování v centrálním Fio cron skriptu odchází jednorázové poděkování dárci a oznámení správci. Pro fungování musí být nejprve nasazena nová verze `George-Pura-Vida/muj-dip` a běžet jeho cron. Žádné tokeny Fio ani soukromá data zde neskladovat. Živou platbu ověřit po nasazení na WebKitty.
