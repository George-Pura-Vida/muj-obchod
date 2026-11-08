<?php
// Zkopíruj na WebKitty jako config.local.php a nahraď hodnotu silným náhodným klíčem
// (minimálně 32 znaků). Záměrně neplatný výchozí klíč blokuje aktivaci.
// Soubor config.local.php NIKDY nenahrávej na veřejný GitHub.
return [
    'setup_key' => 'CHANGE_ME',
    // Kanonická HTTPS adresa aplikace (včetně podadresáře, pokud existuje).
    // Používá se pro odkazy k nastavení hesla.
    'public_url' => '',
    // Ověřená odesílací adresa na hostingu. Prázdná hodnota vypne obnovu e-mailem.
    'reset_from' => '',
];
