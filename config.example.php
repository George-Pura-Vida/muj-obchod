<?php
// Zkopíruj na WebKitty jako config.local.php a nahraď hodnotu silným náhodným klíčem
// (minimálně 32 znaků). Záměrně neplatný výchozí klíč blokuje aktivaci.
// Soubor config.local.php NIKDY nenahrávej na veřejný GitHub.
return [
    'setup_key' => 'CHANGE_ME',
];
