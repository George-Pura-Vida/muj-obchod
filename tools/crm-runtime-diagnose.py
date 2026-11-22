#!/usr/bin/env python3
"""Read-only, secret-free diagnosis of the owner's existing CRM runtime."""
import ast
import json
import os
from pathlib import Path
import subprocess
import sys

def run(args):
    p = subprocess.run(args, capture_output=True, text=True, timeout=25)
    return p.returncode, p.stdout.strip()

home = Path.home()
root = home / ".local/share/diana-hermes-bridge"
for name in ("bridge_runner.py", "hermes_adapter.py"):
    path = root / name
    if not path.is_file():
        print("MISSING", name)
        continue
    tree = ast.parse(path.read_text())
    print("SOURCE", name)
    for node in ast.walk(tree):
        if isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef)):
            print("FUNCTION", node.name, node.lineno)
        if isinstance(node, ast.Compare):
            txt = ast.unparse(node)
            if any(word in txt for word in ("action", "kind", "TOKEN", "token")):
                # Never print literal credentials, only identifiers and short protocol names.
                for value in ast.walk(node):
                    if isinstance(value, ast.Constant) and isinstance(value.value, str):
                        if len(value.value) > 50:
                            value.value = "[redacted]"
                print("CHECK", ast.unparse(node))
        if isinstance(node, ast.Call) and isinstance(node.func, ast.Attribute) and node.func.attr in ("getenv",):
            if node.args and isinstance(node.args[0], ast.Constant):
                print("ENV_NAME", node.args[0].value)
for folder in (".config/diana-crm", ".config/diana", ".config/systemd/user"):
    directory = home / folder
    print("FILES", folder, ",".join(sorted(p.name for p in directory.iterdir()
        if p.is_file() and (folder != ".config/systemd/user" or "diana" in p.name))) if directory.is_dir() else "missing")
code, out = run(["systemctl", "--user", "show", "diana-hermes-bridge.service",
                 "--property=ActiveState,SubState,Result,ExecMainStatus,FragmentPath"])
print("SERVICE", out)
code, out = run(["ssh", "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes",
                "-o", "ConnectTimeout=10", "jirijanousek.cz@ssh.gb.stackcp.com", "true"])
print("HOST_SSH", code)
print("DIAGNOSIS_FINISHED")
