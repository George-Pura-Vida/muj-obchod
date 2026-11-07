# Moje schůzka · obchodní deník

Lehká, responzivní česká aplikace pro telefonování, schůzky, smlouvy, provize, péči, doporučení a křížový prodej.

**Technologie:** čisté HTML, CSS, JavaScript, PHP 8.2+ a SQLite (PDO). Bez buildu, bez Lovable, vhodné pro WebKitty s PHP. Základní rozhraní používá malé barevné karty a čtyři kroky: Kontakt → Schůzka → Smlouva → Péče.

## Nasazení na WebKitty
1. Nasadit obsah tohoto repozitáře z větve `main` do webového kořene subdomény. WebKitty musí spouštět PHP a podporovat PDO SQLite a běžné PHP session.
2. Vedle webového kořene vytvořit zapisovatelnou privátní složku `../moje-schuzka-data/` (program se pokusí ji sám vytvořit). Složka **nesmí být veřejně přístupná**. Ukládá se sem databáze a nahrávky.
3. Pro jednorázovou aktivaci prvního účtu vložit na server soubor `config.local.php` podle `config.example.php` a nastavit dlouhý náhodný instalační klíč. Tento soubor **nikdy necommitovat**! Je blokovaný přes .htaccess. Po první registraci instalační klíč odstranit.
4. Otevřít stránku přes HTTPS, vytvořit účet v záložce *První aktivace*, přihlásit se. Další veřejné registrace jsou záměrně zakázané.
5. Vyzkoušet přidání testovacího kontaktu, schůzky, smlouvy a nahrávky. Do produkce vložit údaje klientů až po ověření oprávnění, zabezpečení a procesu souhlasů.

## Hlas a AI
- Nahrávání schůzek používá mikrofon v prohlížeči přes MediaRecorder, tedy vyžaduje HTTPS, souhlas s mikrofonem a výslovné oprávnění k pořízení záznamu.
- **Nahrávání telefonního hovoru přímo z mobilního prohlížeče není spolehlivě podporováno.** Nahrávku lze do zápisu přiložit ručně.
- Nahrávky se ukládají mimo veřejnou složku a přehrávají přes ověřený PHP endpoint.
- Textový přepis a vyhodnocení se v této verzi zapisují ručně. Tlačítko *Připravit pro AI kouče* vytvoří prompt z tvého přepisu, který můžeš dobrovolně zkopírovat do ChatGPT (bez automatického přenosu dat). **Žádná automatická transkripce ani AI API zatím nejsou připojené.**
- Před nahráváním a odesláním do externí AI řeš informování/souhlas účastníků, právní titul, zabezpečení, dobu uchování a citlivé údaje.

## Export/import
Kontakty: CSV export a import (Excel otevře a uloží CSV UTF-8). V prohlížeči je také podporován XLSX import, pokud se načte volitelná knihovna SheetJS z CDN. Zápisy se zálohují do JSON exportu. Nahrávky nejsou součástí JSON exportu. Neimportuj bez kontroly cizí makra ani škodlivé soubory.

## Ochrana
Hesla se ukládají hashovaná, mutace vyžadují CSRF, přihlášení má limit neúspěšných pokusů, relace používají HttpOnly/SameSite cookies. Aplikace je pro jediného vlastníka; nové registrace po prvním účtu zakázané. Webový kořen nesmí veřejně vydávat `config.local.php` ani `../moje-schuzka-data`. Zálohy databáze a zvukových souborů řeš samostatně.

© 2026 Jiří Janoušek.