<?php
declare(strict_types=1);

// Samostatná cesta pro zvukové soubory. Zachovává autentizaci, ověření
// vlastníka nahrávky, MIME a podporu HTTP Range ze stávajícího api.php.
// Není zde žádný veřejný ani neautentizovaný přístup.
$_GET['action'] = 'media';
require __DIR__ . '/api.php';
