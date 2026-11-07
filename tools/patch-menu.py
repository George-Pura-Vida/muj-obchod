#!/usr/bin/env python3
"""Doplní odkaz AI kouč do existujícího menu bez změny obchodní logiky."""
import pathlib
import sys

if len(sys.argv) != 2:
    print("Použití: python tools/patch-menu.py /cesta/k/muj-obchod/index.php")
    sys.exit(2)
p = pathlib.Path(sys.argv[1])
s = p.read_text(encoding="utf-8")
line = '<button class="nav-item" data-view="results"><svg><use href="#i-chart"/></svg> Výsledky</button>'
addition = '\n<a class="nav-item" style="text-decoration:none" href="ai/index.php">✨ AI kouč schůzek</a>'
if 'href="ai/index.php"' in s:
    print("AI položka už v menu je. Nic neměním.")
elif line not in s:
    print("Vstupní index.php se liší od ověřené verze. Odkaz přidej ručně.", file=sys.stderr)
    sys.exit(1)
else:
    p.write_text(s.replace(line, line + addition, 1), encoding="utf-8")
    print("Odkaz do menu doplněn:", p)
