# Oprava synchronizace obou CRM

## Rozsah

CLI nástroj `tools/crm-sync.php` běží pod stejným účtem hostingu jako obě aplikace. Nepřidává veřejné API ani nové přístupové údaje. Webové spuštění vrací 404.

Přenáší nové klienty, změny základních kontaktních údajů a nové zápisy oběma směry. Existující karty páruje pouze podle jednoznačného e-mailu nebo telefonu. Shodné jméno bez jednoznačné identity vede ke konfliktu, nikoli automatickému sloučení. Existující změněné zápisy zachová a zaznamená konflikt k ručnímu posouzení. Smazané klienty neobnovuje. Produkty, investiční hodnoty, pojistné smlouvy, provize a nahrávky nejsou společnými poli těchto aplikací a nepřepisují se.

## Nasazení

Nástroj je samostatný a nemusí přepsat žádný soubor aplikace. Ve společném kořeni hostingu potřebuje existující soubory `moje-schuzka-data/database.sqlite` a `muj-kmen-data/muj-kmen.sqlite`. Vytvoří vlastní neveřejný adresář `diana-crm-sync-private` (0700), databázi mapování, souhrnný status a denní zálohy SQLite (0600). Před prvním zápisem musí uspět záloha obou databází.

Použijte PHP CLI 8.2+ s PDO SQLite. Výchozí vlastník je stávající správce; jiný účet se předává explicitně přes `--owner`. `--root` nastavuje společný kořen. `--dry-run` simuluje přenos v transakci a vrací ji zpět. `--fixture-only` omezuje přenos na výslovně fiktivního klienta Test Testík.

Po ověření produkčního pilotu spouštějte běh plánovačem hostingu každou minutu. Souborový zámek brání souběžným běhům. Opakování používá existující mapování a nepřenáší záznamy zpět v kruhu. Do konzole se vypisují pouze agregované počty. Konflikty zůstávají v neveřejné databázi.

## Ověření

`tools/crm-sync-isolated-test.py` stahuje přesně určenou verzi nástroje a oficiální balíčky PHP pro Ubuntu do dočasného adresáře; neinstaluje systémové balíčky. Testy vytvářejí pouze fiktivní oddělené databáze a ověřují:

- návrat zkušebních změn zpět;
- klienta i úplný obsah schůzky z Obchodu do Kmene;
- opakování bez duplicit;
- opačný směr a absenci zpětného kopírování;
- změny kontaktů v obou směrech;
- zachování současně změněných údajů;
- zákaz obnovy smazaného klienta;
- jednoznačné párování bez duplikace a blokaci pouhé shody jmen;
- atomický návrat celého přenosu při chybě a úspěšné opakování.

Izolované testy byly úspěšně provedeny na Linuxu 22. 11. 2026. Produkční zkušební běh s `--fixture-only --dry-run` potvrdil očekávané vytvoření jedné karty a přenos jednoho zápisu; produkční pilot a plánovač jsou samostatně ověřované kroky.
