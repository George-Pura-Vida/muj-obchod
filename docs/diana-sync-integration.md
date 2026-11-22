# DIANA SYNC – bezpečné zápisy aktivit (návrh k ověření)

## Rozhraní Můj obchod
`POST /api.php?action=activity` vyžaduje přihlášenou session, hlavičku `X-CSRF-Token`, příslušné oprávnění a existující `contact_id` patřící uživateli. Nový volitelný parametr `request_key` je neprázdný unikátní řetězec o délce 1–80 znaků, pouze ASCII písmena, číslice, `_` a `-`.

Ukázka těla (výhradně testovací klient):
```json
{
  "contact_id": 123,
  "kind": "note",
  "notes": "Testovací zápis DIANA",
  "happened_at": "2026-11-22",
  "request_key": "diana-test-unique-001"
}
```

První úspěšné volání vrací `{"ok":true,"id":...}`. Opakování identického těla se stejným klíčem vrátí `{"ok":true,"id":...,"duplicate":true}` a nevytvoří novou aktivitu. Stejný klíč s odlišným tělem vrací HTTP 409. Pro původní UI bez klíče zůstává chování beze změny. Klíč je jedinečný v rámci přihlášeného uživatele.

## Můj kmen (existující API)
`POST /api.php?action=entry` používá JSON `id`, `clientId`, `body`, `kind`, `date`, `revision` a přihlášenou session + CSRF. Stejné `id` je rozpoznáno jako duplicita. Pro novou položku je nutné použít **skutečné ID klienta a aktuální revizi**; při konfliktu revize vrací HTTP 409.

## Nezbytné podmínky před nasazením
1. Zálohovat produkční SQLite a ověřit možnost obnovy.
2. Spustit `php -l api.php` a integrační test na oddělené kopii aplikace/dat.
3. Ověřit nový zápis, identické opakování, konflikt obsahu, neplatný klíč, neoprávněný kontakt a expirovaný CSRF.
4. Udržovat mapování `muj_obchod_contact_id ↔ muj_kmen_client_id` s explicitním potvrzením správnosti. **Nikdy nepropojovat jen podle jména.**
5. Nezapínat automatické opakování neúspěšných síťových požadavků bez kontroly stavu na serveru.
6. Nedávat přihlašovací cookies, CSRF, klientské zápisy ani osobní data do GitHubu.
7. Ověřit nasazení obou aplikací odděleně a zpětným čtením potvrdit každý zápis.

## Aktuální omezení
Tento PR nepřidává automatické přihlášení, přenos z hlasové konverzace ChatGPT, mapování klientů, konektor pro Můj kmen ani produkční nasazení. Lokální DIANA SYNC 1.0 zůstává samostatná fronta a **nesmí být přepnuta do produkčního režimu jen na základě tohoto PR**.
