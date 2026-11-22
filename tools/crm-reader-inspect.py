#!/usr/bin/env python3
"""Export only sanitized runtime SOURCE and service metadata to the private bridge."""
import ast, base64, json, os, re, shlex, urllib.request
from pathlib import Path
home=Path.home()
env={}
for line in (home/".config/diana-hermes-bridge/bridge.env").read_text().splitlines():
    if "=" in line and not line.lstrip().startswith("#"):
        k,v=line.split("=",1)
        try: env[k.strip()]=shlex.split(v)[0]
        except (ValueError, IndexError): pass
token=env.get("HERMES_GITHUB_TOKEN") or os.environ.get("HERMES_GITHUB_TOKEN")
if not token: raise SystemExit("BLOCKED: bridge credential unavailable")
sources={}
for name in (".local/share/diana-reports/muj_obchod_reader.py", ".local/bin/diana-crm-count.py"):
    path=home/name
    if not path.is_file(): continue
    source=path.read_text()
    tree=ast.parse(source)
    for n in ast.walk(tree):
        if isinstance(n,ast.Constant) and isinstance(n.value,str):
            if re.search(r"(gh[pousr]_[A-Za-z0-9_]+|github_pat_[A-Za-z0-9_]+|[a-fA-F0-9]{64})",n.value):
                n.value="[credential redacted]"
    sources[name]=ast.unparse(tree)
units={}
for path in (home/".config/systemd/user").glob("*crm*.service"):
    units[path.name]="\n".join(l for l in path.read_text().splitlines()
      if l.startswith(("ExecStart=","WorkingDirectory=","EnvironmentFile=")))
files=[]
for directory in (home/".local/share", home/"ai-agenti"):
    if not directory.exists(): continue
    for path in directory.rglob("*.py"):
        if any(part in ("site-packages",".venv","venv","node_modules",".git") for part in path.parts): continue
        if path.stat().st_size > 150000: continue
        try: source=path.read_text()
        except (OSError, UnicodeError): continue
        if "muj-obchod.jirijanousek.cz" in source or "muj-kmen.jirijanousek.cz" in source:
            files.append(str(path.relative_to(home)))
body={"source_code_only":True,"sources":sources,"units":units,"crm_source_files":files}
url="https://api.github.com/repos/George-Pura-Vida/diana-hermes-bridge/contents/results/crm-reader-source-20261122.json"
payload={"message":"diagnostics: sanitized runtime source for CRM repair","content":base64.b64encode(json.dumps(body,ensure_ascii=False).encode()).decode()}
req=urllib.request.Request(url,data=json.dumps(payload).encode(),headers={"Authorization":"Bearer "+token,"Accept":"application/vnd.github+json"},method="PUT")
try:
    with urllib.request.urlopen(req,timeout=30) as response:
        result=json.load(response)
    print("DIAGNOSTIC_SOURCE_UPLOADED", result["content"]["sha"])
except Exception as exc:
    print("UPLOAD_FAILED",type(exc).__name__)
