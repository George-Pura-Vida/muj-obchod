# DIANA: diktovaný pokyn z ChatGPT → Můj obchod

Tento konektor přijímá strukturovaný pokyn z ChatGPT a zapisuje **poznámku** do
existující karty v Mém obchodu. Přepis a porozumění hlasu zajišťuje ChatGPT.
Konektor sám nenahrává mikrofon, nenaplánuje schůzku v kalendáři a nevytváří klienty.
Hlasové rozhraní musí v konkrétní aplikaci ChatGPT umožňovat použití připojeného
konektoru; alternativou je hlasový diktát odeslaný jako běžná zpráva. Živý hlasový
režim bez skutečného ověření podpory nelze označit za funkční.

**Stav:** implementováno, testováno na oddělených fiktivních datech a přes skutečný
MCP stdio protokol. Zatím není nainstalováno na uživatelově Linuxu, propojeno s
účtem ChatGPT ani ověřeno skutečným pokynem z ChatGPT do produkčního CRM.

## Co nástroje dělají

| Nástroj | Výsledek |
| --- | --- |
| `get_context` | Datum a čas Europe/Prague pro výrazy jako „dnes“ |
| `find_client` | Přesné jméno → ověřená karta a reference platná 10 minut |
| `save_note` | Zápis s trvalým ID a ověření jeho serverové účtenky |
| `operation_status` | Čtení místního stavu bez dalšího zápisu do CRM |
| `retry_note` | Bezpečné opakování původního požadavku se stejným ID |

Pokud se jméno shoduje s více kartami, nástroj vrátí kandidátní ID a poslední tři
číslice telefonu. Uživatel musí vybrat kartu; ChatGPT nesmí ID odhadovat. Před
odesláním se znovu ověří ID i původní kontaktní údaje karty. Smazaná či změněná
karta zápis zastaví.

Každý hlasový pokyn dostane jeden `operation_id`. Lokální fronta jej uloží před
síťovým požadavkem. Ztráta odpovědi se hlásí jako nejistý stav, nikoli jako úspěch
nebo jisté selhání. Opakování se stejným ID použije zmrazené původní tělo.
Stejný klient, datum a přesně stejný text se v rámci tohoto konektoru uloží jednou
i při jiném ID pokynu. Odlišné skutečné události proto potřebují odlišný popis,
například s časem. Sémanticky podobné, ale různě přepsané věty nejsou automaticky
považovány za stejnou událost.

Potvrzení úspěchu vyžaduje úspěšný zápis **a druhou serverovou odpověď se stejným
ID, `duplicate=true` a totožným obsahem požadavku**. Používá se existující
transakční ochrana `activity_request_keys` v API. Není to načtení celého řádku
aktivity nezávislým čtecím API. Přenos do Mého kmene zajišťuje samostatná
synchronizace; tento konektor její výsledek nepotvrzuje.

## Instalace na stroji s existujícím CRM tokenem

Požadavky: Linux, Python 3.11+, `venv`, přístup k PyPI a HTTPS k Mému obchodu.

```sh
sh tools/diana_chatgpt/install.sh
```

Instalátor vytváří soukromé prostředí, instaluje připnutou verzi oficiálního MCP
SDK a spouští testy s fiktivními daty. Nezapíná veřejnou síťovou službu a nemění CRM.

Konektor čte existující token ze souboru `~/.config/diana-crm/obchod.token`.
Soubor musí patřit běžícímu uživateli, být běžným souborem s právy 600 a obsahovat
64 malých hexadecimálních znaků. Token se nesmí vkládat do chatu, GitHubu,
argumentů příkazů, instalátoru ani veřejného webového adresáře.
Soukromý stav je v `~/.local/state/diana-chatgpt-mcp` (700, databáze 600).
Obsahuje klientské údaje i texty čekajících a potvrzených poznámek; nezveřejňovat.

## Připojení k ChatGPT

Konektor mluví MCP přes **stdio**. Záměrně neposlouchá na veřejném HTTP portu.
Pro ChatGPT použij soukromý Secure MCP Tunnel, který spouští konektor na témže
Linuxu a autentizuje přístup v účtu OpenAI. Samotný běžící stdio proces není
automaticky dostupný této konverzaci.

1. V Platform tunnel settings připrav tunel svázaný se správným účtem/ChatGPT
   prostorem. Potřebuješ příslušná oprávnění a runtime klíč. Nové propojení
   přístupu a případné udělení oprávnění musí uživatel potvrdit.
2. Z oficiálního `openai/tunnel-client` nainstaluj aktuální podporovaný klient a
   postupuj podle jeho aktuálního `help quickstart`. Tajný runtime klíč zadej
   soukromě; nikdy jej nevkládej do tohoto repozitáře.
3. Pro stdio profil použij příkaz, který vypíše instalátor. Příklad konfigurace
   z oficiálního návodu (ID tunelu se doplní skutečnou hodnotou):

```sh
tunnel-client init \
  --sample sample_mcp_stdio_local \
  --profile diana-obchod \
  --tunnel-id SKUTECNE_ID_TUNELU \
  --mcp-command "$HOME/.local/share/diana-chatgpt-mcp/venv/bin/python $HOME/.local/share/diana-chatgpt-mcp/server.py"
tunnel-client doctor --profile diana-obchod --explain
tunnel-client run --profile diana-obchod
```

Pokud XDG_DATA_HOME obsahuje mezery, použij samostatný spouštěcí wrapper s přesně
citovanými cestami; nepoužívej výše uvedený příklad s cestou obsahující mezery.
Runtime klíč `tunnel-client` musí být dodán podle jeho aktuálního bezpečného
nastavení prostředí/úložiště. Příklad výše jej neobsahuje.

4. Na webu ChatGPT otevři Plugins → Add custom MCP server → Connection: Tunnel.
   Vyber tento tunel a dokonči schválení propojení. Nevystavuj CRM jako veřejný
   neautorizovaný MCP server. Dostupnost závisí na oprávněních konkrétního účtu.
5. V konverzaci zvol DIANA konektor a ověř `get_context` a `find_client`.
   Až potom s výslovným pokynem uživatele zapiš poznámku k jasně označenému
   technickému klientovi. Ověř kartu v Mém obchodu a stejný pokyn zopakuj se stejným
   ID; musí zůstat jediný zápis. Teprve tím je doložena celá cesta.

## Ověření implementace

```sh
python -m pip install -r tools/diana_chatgpt/requirements.txt
cd tools/diana_chatgpt
python -m unittest -v test_bridge test_protocol
```

Testy pokrývají ztracenou odpověď po commitu, restart fronty, souběžné požadavky,
znovupoužití ID s jiným obsahem, přesné opakování s novým ID, nejednoznačné a
změněné karty, volbu konkrétní karty, expiraci, neplatná data, soukromé soubory,
časové pásmo a skutečnou MCP inicializaci/výpis nástrojů/volání přes subprocess.
Testy nemají produkční token a neposílají klientské zápisy na produkční API.

Použité oficiální podklady:

- https://developers.openai.com/api/docs/guides/secure-mcp-tunnels
- https://developers.openai.com/api/docs/guides/custom-mcp-server
- https://github.com/modelcontextprotocol/python-sdk
