# -*- coding: utf-8 -*-
"""Local Flux inventory builder. Scans application sources only — never vendor/node_modules."""
from __future__ import annotations

import json
import re
from collections import Counter, defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
EXCLUDE = {".git", "vendor", "node_modules", "storage"}
COMMIT = "6b6c5e7e14e979fd999fd6390b56254a5c2a07de"


def skip(p: Path) -> bool:
    return any(part in EXCLUDE for part in p.parts)


def main() -> None:
    flux_tag_re = re.compile(r"<flux:([A-Za-z0-9_.:-]+)")
    name_attr_re = re.compile(r"""\bname\s*=\s*['"]([^'"]+)['"]""")
    show_re = re.compile(r"Flux::modal\(\s*['\"]([^'\"]+)['\"]\s*\)\s*->\s*show\s*\(")
    close_re = re.compile(r"Flux::modal\(\s*['\"]([^'\"]+)['\"]\s*\)\s*->\s*close\s*\(")
    php_re = re.compile(r"use\s+Flux\\Flux|Flux::")

    file_counts: Counter[str] = Counter()
    component_counts: Counter[str] = Counter()
    php_refs: list[tuple[str, int, str]] = []
    seen_php: set[tuple[str, int]] = set()
    modal_defs: dict[str, list[tuple[str, int, str]]] = defaultdict(list)
    triggers: dict[str, list[tuple[str, int, str]]] = defaultdict(list)
    shows: dict[str, list[tuple[str, int, str]]] = defaultdict(list)
    closes: dict[str, list[tuple[str, int, str]]] = defaultdict(list)
    modals_close_all: list[tuple[str, int, str]] = []
    toasts: list[tuple[str, int, str]] = []
    dep_hits: list[tuple[str, int, str]] = []

    for p in sorted(ROOT.rglob("*.blade.php")):
        if skip(p):
            continue
        text = p.read_text(encoding="utf-8", errors="replace")
        rel = str(p.relative_to(ROOT)).replace("\\", "/")
        for line in text.splitlines():
            for m in flux_tag_re.finditer(line):
                component_counts[m.group(1)] += 1
                file_counts[rel] += 1

        for m in re.finditer(r"<flux:modal\b([\s\S]*?)>", text):
            attrs = m.group(1)
            nm = name_attr_re.search(attrs)
            name = nm.group(1) if nm else "(unnamed)"
            line_no = text[: m.start()].count("\n") + 1
            key = (rel, line_no)
            if key not in {(d[0], d[1]) for d in modal_defs[name]}:
                modal_defs[name].append(
                    (rel, line_no, re.sub(r"\s+", " ", attrs).strip()[:180])
                )

        for m in re.finditer(r"<flux:modal\.trigger\b([\s\S]*?)>", text):
            attrs = m.group(1)
            nm = name_attr_re.search(attrs)
            name = nm.group(1) if nm else "(parent-context)"
            line_no = text[: m.start()].count("\n") + 1
            key = (rel, line_no)
            if key not in {(t[0], t[1]) for t in triggers[name]}:
                triggers[name].append(
                    (rel, line_no, re.sub(r"\s+", " ", attrs).strip()[:180])
                )

    # Unique PHP files: *.php does not include *.blade.php on pathlib, so add both.
    php_files = {p for p in ROOT.rglob("*.php") if not skip(p)}
    php_files |= {p for p in ROOT.rglob("*.blade.php") if not skip(p)}
    for p in sorted(php_files):
        text = p.read_text(encoding="utf-8", errors="replace")
        rel = str(p.relative_to(ROOT)).replace("\\", "/")
        for i, line in enumerate(text.splitlines(), 1):
            if not php_re.search(line):
                continue
            key = (rel, i)
            if key in seen_php:
                continue
            seen_php.add(key)
            sn = line.strip()[:220]
            php_refs.append((rel, i, sn))
            for m in show_re.finditer(line):
                shows[m.group(1)].append((rel, i, sn))
            for m in close_re.finditer(line):
                closes[m.group(1)].append((rel, i, sn))
            if "Flux::modals()" in line and "close" in line:
                modals_close_all.append((rel, i, sn))
            if "Flux::toast" in line:
                toasts.append((rel, i, sn))

    for rel in [
        "composer.json",
        "composer.lock",
        "package.json",
        "README.md",
        "AGENTS.md",
        "resources/css/app.css",
        "resources/js/app.js",
        "resources/views/layouts/app.blade.php",
        "resources/views/layouts/guest.blade.php",
    ]:
        p = ROOT / rel
        if not p.exists():
            continue
        text = p.read_text(encoding="utf-8", errors="replace")
        for i, line in enumerate(text.splitlines(), 1):
            if re.search(
                r"livewire/flux|flux-pro|composer\.fluxui\.dev|@flux|fluxScripts|fluxStyles|flux\.js|flux\.css|FluxServiceProvider|@import.*flux",
                line,
            ):
                dep_hits.append((rel, i, line.strip()[:220]))

    use_flux_files = sorted(
        {r for r, _i, s in php_refs if re.search(r"use\s+Flux\\Flux", s)}
    )
    named = sorted(
        set(modal_defs)
        | set(shows)
        | set(closes)
        | {k for k in triggers if k not in {"(parent-context)", "(unnamed)"}}
    )

    out = {
        "commit": COMMIT,
        "flux_pro_source_inspected": False,
        "vendor_node_modules_excluded": True,
        "agpl_notice": "Picstome is AGPL-3.0; inventory is documentation-only and does not copy Flux Pro source.",
        "blade_files_with_flux": len(file_counts),
        "total_flux_opening_tags": int(sum(file_counts.values())),
        "unique_components": len(component_counts),
        "file_counts": dict(file_counts.most_common()),
        "component_counts": dict(component_counts.most_common()),
        "php_refs": [{"file": f, "line": i, "snippet": s} for f, i, s in php_refs],
        "use_flux_files": use_flux_files,
        "modal_defs": {
            k: [{"file": f, "line": i, "attrs": a} for f, i, a in v]
            for k, v in sorted(modal_defs.items())
        },
        "triggers": {
            k: [{"file": f, "line": i, "attrs": a} for f, i, a in v]
            for k, v in sorted(triggers.items())
        },
        "shows": {
            k: [{"file": f, "line": i, "snippet": s} for f, i, s in v]
            for k, v in sorted(shows.items())
        },
        "closes": {
            k: [{"file": f, "line": i, "snippet": s} for f, i, s in v]
            for k, v in sorted(closes.items())
        },
        "modals_close_all": [
            {"file": f, "line": i, "snippet": s} for f, i, s in modals_close_all
        ],
        "toasts": [{"file": f, "line": i, "snippet": s} for f, i, s in toasts],
        "dep_hits": [{"file": f, "line": i, "snippet": s} for f, i, s in dep_hits],
        "named_modals": named,
    }
    out_path = Path(__file__).with_name("inventory-raw.json")
    out_path.write_text(json.dumps(out, indent=2), encoding="utf-8")
    print(
        f"files={out['blade_files_with_flux']} tags={out['total_flux_opening_tags']} "
        f"components={out['unique_components']} named_modals={len(named)}"
    )
    print("WROTE", out_path)


if __name__ == "__main__":
    main()
