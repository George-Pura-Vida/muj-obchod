#!/bin/sh
set -eu
umask 077
source_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
target_dir="${XDG_DATA_HOME:-$HOME/.local/share}/diana-chatgpt-mcp"
mkdir -p "$target_dir"
chmod 700 "$target_dir"
python3 -m venv "$target_dir/venv"
"$target_dir/venv/bin/python" -m pip install -r "$source_dir/requirements.txt"
for name in bridge.py server.py requirements.txt; do
    install -m 600 "$source_dir/$name" "$target_dir/$name"
done
(
    cd "$source_dir"
    "$target_dir/venv/bin/python" -m unittest -v test_bridge test_protocol
)
printf '%s\n' 'DIANA: konektor nainstalován a lokální testy dokončeny.'
printf '%s\n' 'Připojení k ChatGPT a skutečný produkční zápis ještě nejsou ověřené.'
printf 'MCP příkaz: %s/venv/bin/python %s/server.py\n' "$target_dir" "$target_dir"
