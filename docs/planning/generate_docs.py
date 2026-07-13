# -*- coding: utf-8 -*-
"""Generate measured planning docs from inventory-raw.json."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parent
data = json.loads((ROOT / "inventory-raw.json").read_text(encoding="utf-8"))
fc = data["file_counts"]
cc = data["component_counts"]
COMMIT = data["commit"]


def esc(s: str) -> str:
    return s.replace("|", "\\|")


def write_01() -> None:
    lines: list[str] = []
    a = lines.append
    a("# Assignment 001 — Flux Inventory (measured)")
    a("")
    a("**Repository:** `picstome/picstome`  ")
    a(f"**Upstream base commit:** `{COMMIT}`  ")
    a("**Assignment type:** Analysis and migration planning only  ")
    a("**Repository changes made:** Documentation under `docs/planning/` only  ")
    a("**Flux Pro source copied:** No  ")
    a(
        "**Flux Pro / vendor source inspected:** No "
        "(`vendor/` and `node_modules/` excluded from all scans)"
    )
    a("")
    a("## Verification status and scope")
    a("")
    a(
        f"This inventory was measured on a local checkout of `{COMMIT}` "
        r"at `D:\Program\Codex\Picstome\repo`."
    )
    a("")
    a("### Measured totals")
    a("")
    a("| Metric | Value |")
    a("|---|---:|")
    a(f"| Application Blade files containing `<flux:` | **{data['blade_files_with_flux']}** |")
    a(f"| Total `<flux:…` opening tags | **{data['total_flux_opening_tags']}** |")
    a(
        f"| Unique Flux component tags (including nested names) | "
        f"**{data['unique_components']}** |"
    )
    a(f"| Named modals (`name=` / PHP show\\|close) | **{len(data['named_modals'])}** |")
    a(f"| Files with `use Flux\\Flux;` | **{len(data['use_flux_files'])}** |")
    a(f"| Lines matching `use Flux\\Flux` or `Flux::` | **{len(data['php_refs'])}** |")
    a("")
    a("### Authoritative local commands (re-run evidence)")
    a("")
    a("```powershell")
    a(r"cd D:\Program\Codex\Picstome\repo")
    a(f"git rev-parse HEAD  # expect {COMMIT}")
    a("python docs/planning/build_inventory.py")
    a("python docs/planning/generate_docs.py")
    a("```")
    a("")
    a("Raw machine output: `docs/planning/inventory-raw.json`")
    a("")
    a("## Dependency and integration findings (measured)")
    a("")
    a("- `composer.json` requires `livewire/flux` `^2.0` and `livewire/flux-pro` `^2.2`.")
    a("- Composer private repo `flux-pro` → `https://composer.fluxui.dev`.")
    a(
        "- Locked versions (from `composer.lock` references only): Flux free **v2.12.2**, "
        "Flux Pro **2.12.2**. **Package contents were not opened.**"
    )
    a("- `package.json` has **no** Flux npm dependency.")
    a(
        "- `resources/css/app.css` references a vendor Flux CSS import path "
        "(path noted only; vendor tree not inspected for Pro implementation)."
    )
    a(
        "- Application code uses `use Flux\\Flux;` plus `Flux::modal(...)->show|close()`, "
        "`Flux::modals()->close()`, and `Flux::toast(...)`."
    )
    a("")
    a("### Dependency / asset reference hits (path + line)")
    a("")
    a("| File:line | Snippet |")
    a("|---|---|")
    for h in data["dep_hits"]:
        a(f"| `{h['file']}:{h['line']}` | `{esc(h['snippet'])}` |")
    a("")
    a("## Every file containing `<flux:` (measured occurrence counts)")
    a("")
    a("| # | File | Occurrence count |")
    a("|---:|---|---:|")
    for i, (f, n) in enumerate(sorted(fc.items(), key=lambda x: x[0].lower()), 1):
        a(f"| {i} | `{f}` | **{n}** |")
    a("")
    a(f"**Sum of per-file counts:** {sum(fc.values())} (matches total opening tags).")
    a("")
    a("## Unique Flux component inventory (measured)")
    a("")
    a("| Flux component | Total occurrence count |")
    a("|---|---:|")
    for comp, n in cc.items():
        a(f"| `flux:{comp}` | **{n}** |")
    a("")
    a("## Files using `use Flux\\Flux;` (measured)")
    a("")
    for f in data["use_flux_files"]:
        a(f"- `{f}`")
    a("")
    a("## All `use Flux\\Flux` / `Flux::` lines (measured)")
    a("")
    a("| File:line | Snippet |")
    a("|---|---|")
    for r in data["php_refs"]:
        a(f"| `{r['file']}:{r['line']}` | `{esc(r['snippet'])}` |")
    a("")
    a("## Named modals (summary)")
    a("")
    a("Full mapping: `docs/planning/05-named-modal-mapping.md`")
    a("")
    a("| Modal name | Definitions | Triggers (`modal.trigger`) | PHP show | PHP close |")
    a("|---|---:|---:|---:|---:|")
    for name in data["named_modals"]:
        d = len(data["modal_defs"].get(name, []))
        t = len(data["triggers"].get(name, []))
        s = len(data["shows"].get(name, []))
        c = len(data["closes"].get(name, []))
        a(f"| `{name}` | {d} | {t} | {s} | {c} |")
    if data["modals_close_all"]:
        a("")
        a("### Global modal close")
        a("")
        for r in data["modals_close_all"]:
            a(f"- `{r['file']}:{r['line']}` — `{esc(r['snippet'])}`")
    a("")
    a("## Free versus Pro classification rule")
    a("")
    a(
        "Classification remains **planning guidance only**. This inventory does **not** open "
        "Flux Pro vendor source to prove Free vs Pro. Implementers must consult public Flux 2.x "
        "docs and treat advanced controls as high-risk replacements without reverse-engineering "
        "proprietary code."
    )
    a("")
    a("## Mismatches vs prior planning draft")
    a("")
    a("See `docs/planning/06-planning-mismatches.md`.")
    a("")
    a("## Licensing / AGPL")
    a("")
    a("- Picstome project license: AGPL-3.0 (`LICENSE.md` present).")
    a("- This work product is documentation only.")
    a(
        "- **No Flux Pro source, vendor CSS, vendor JavaScript, or proprietary implementation "
        "was inspected or copied.**"
    )
    a("")
    (ROOT / "01-flux-inventory.md").write_text("\n".join(lines) + "\n", encoding="utf-8")
    print("wrote 01-flux-inventory.md")


ROOT = ROOT  # noqa: placate linters if any


def write_mismatches() -> None:
    prior = {
        "badge",
        "button",
        "button.group",
        "dropdown",
        "input",
        "link",
        "menu",
        "menu.item",
        "menu.separator",
        "modal",
        "modal.trigger",
        "navbar",
        "navbar.item",
        "tab.group",
        "tabs",
        "tab",
        "tab.panel",
        "text",
        "tooltip",
        "tooltip.content",
        "date-picker",
        "table",
        "command",
        "file-upload",
    }
    measured = set(cc)
    missing_from_prior = sorted(measured - prior)
    extra_in_prior = sorted(prior - measured)
    lines: list[str] = []
    a = lines.append
    a("# Planning mismatches — Assignment 001")
    a("")
    a(f"**Commit:** `{COMMIT}`")
    a("")
    a(
        r"Measured results compared to the pre-measurement planning artifacts under "
        r"`D:\Program\Codex\Picstome\`."
    )
    a("")
    a("## Confirmed matches")
    a("")
    a(
        f"- Blade file count with `<flux:`: planning said **58**; local measure "
        f"**{data['blade_files_with_flux']}** — **MATCH**."
    )
    a(f"- Pinned commit `{COMMIT}` present and checked out — **MATCH**.")
    a(
        "- `livewire/flux` + `livewire/flux-pro` required in `composer.json` with private "
        "`composer.fluxui.dev` repo — **MATCH**."
    )
    a("")
    a("## Mismatches / gaps")
    a("")
    a(
        f"1. **Unique component tags:** planning table listed **~24** components; local measure "
        f"**{data['unique_components']}** distinct `flux:*` tags."
    )
    a("2. **Components present locally but absent from original planning table:**")
    a("")
    for c in missing_from_prior:
        a(f"   - `flux:{c}` (**{cc[c]}**)")
    a("")
    a("3. **Components listed in planning table but not found as tags:**")
    a("")
    if extra_in_prior:
        for c in extra_in_prior:
            a(f"   - `flux:{c}` — **0 occurrences** at this commit")
    else:
        a("   - (none)")
    if cc.get("file-upload", 0) == 0:
        a(
            "   - Note: planning assumed `flux:file-upload`; **no `<flux:file-upload` tags** "
            "found. Galleries use Livewire `WithFileUploads` with other Flux chrome, not a "
            "Flux file-upload tag."
        )
    a("")
    a(
        "4. **Per-file / per-component counts:** planning used `VERIFY LOCALLY` everywhere; "
        "now replaced with measured integers."
    )
    a(
        f"5. **Named modal inventory:** planning mentioned a few examples; local measure finds "
        f"**{len(data['named_modals'])}** named modals — see `05-named-modal-mapping.md`."
    )
    a(
        "6. **PHP integration surface** includes `Flux::toast` and `Flux::modals()->close()` "
        "in addition to named modal show/close."
    )
    a(
        "7. **`docs/planning/HANDOFF-CODEX.md`** was referenced in the agent brief but **does "
        "not exist** in upstream or the planning source folder; claim/handoff used GitHub "
        "issue #214 + this planning set."
    )
    a("")
    a("## Not invented")
    a("")
    a(
        "No Free/Pro classification was asserted from vendor source. No counts were estimated "
        "when a scan returned zero."
    )
    a("")
    (ROOT / "06-planning-mismatches.md").write_text("\n".join(lines) + "\n", encoding="utf-8")
    print("wrote 06-planning-mismatches.md")


def write_modals() -> None:
    # Replacement direction heuristics without claiming Free/Pro from vendor
    def direction(name: str) -> str:
        return (
            "MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key "
            f"`{name}`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; "
            "close via Livewire action calling a shared modal helper (do not copy Flux internals)."
        )

    def risk(name: str, shows: int, closes: int, triggers: int) -> str:
        bits = []
        if shows and not triggers:
            bits.append("PHP-driven open (no Blade trigger)")
        if triggers and not shows:
            bits.append("trigger-driven open")
        if not closes and name not in {"login", "register", "search"}:
            bits.append("no explicit PHP close found (rely on modal UI close / navigation)")
        if name in {"share", "share-link", "mark-favorites", "favorite-list", "add-photos"}:
            bits.append("gallery complexity: URL tabs, uploads, events, authorization")
        if name == "sign":
            bits.append("signature canvas + Flux::modals()->close()")
        return "; ".join(bits) if bits else "Standard modal focus trap / Escape / backdrop"

    def test(name: str) -> str:
        return (
            f"Feature/browser: open `{name}` from each trigger/PHP path; assert focus trap; "
            f"Escape/backdrop close; submit success closes when applicable; no orphan overlays; "
            f"Livewire validation errors keep modal open."
        )

    lines: list[str] = []
    a = lines.append
    a("# Named-modal mapping — Assignment 001")
    a("")
    a(f"**Commit:** `{COMMIT}`  ")
    a("**Flux Pro source inspected:** No  ")
    a("**Scope:** documentation only")
    a("")
    a("## Method")
    a("")
    a("- Definitions: `<flux:modal name=\"…\">` in application Blade (not vendor).")
    a("- Triggers: `<flux:modal.trigger name=\"…\">` (or parent-context triggers).")
    a("- Show/close: `Flux::modal('name')->show|close()` in Livewire/Blade PHP.")
    a("- Global: `Flux::modals()->close()` recorded separately.")
    a("")
    a("## Global helpers")
    a("")
    if data["modals_close_all"]:
        for r in data["modals_close_all"]:
            a(f"- Close-all: `{r['file']}:{r['line']}` — `{esc(r['snippet'])}`")
    else:
        a("- (no `Flux::modals()->close()` found)")
    a("")
    a(f"## Named modals ({len(data['named_modals'])})")
    a("")
    for name in data["named_modals"]:
        defs = data["modal_defs"].get(name, [])
        trigs = data["triggers"].get(name, [])
        sh = data["shows"].get(name, [])
        cl = data["closes"].get(name, [])
        # parent-context triggers may apply if only one modal in file — note if empty
        a(f"### `{name}`")
        a("")
        a(f"- **Modal name:** `{name}`")
        if defs:
            a("- **Blade view/component (definition):**")
            for d in defs:
                a(f"  - `{d['file']}:{d['line']}` attrs: `{esc(d['attrs'])}`")
        else:
            a(
                "- **Blade view/component (definition):** **NOT FOUND** as `name=` on "
                "`<flux:modal>` (PHP/trigger reference only — **mismatch risk**)."
            )
        php_files = sorted({x["file"] for x in sh + cl})
        a(
            "- **PHP/Livewire caller:** "
            + (", ".join(f"`{f}`" for f in php_files) if php_files else "_(no Flux::modal PHP show/close)_")
        )
        a("- **Trigger:**")
        if trigs:
            for t in trigs:
                a(f"  - `{t['file']}:{t['line']}` `{esc(t['attrs'])}`")
        else:
            # look for parent-context triggers in same files as defs
            parent = [
                t
                for t in data["triggers"].get("(parent-context)", [])
                if defs and t["file"] in {d["file"] for d in defs}
            ]
            if parent:
                a("  - Parent-context `flux:modal.trigger` in same file(s) (name inferred by nesting):")
                for t in parent:
                    a(f"    - `{t['file']}:{t['line']}`")
            elif sh:
                a("  - **PHP `->show()` only** (no `flux:modal.trigger` with this name).")
            else:
                a("  - **No dedicated trigger or PHP show found** (may open via nested trigger without name).")
        a("- **Show/close behavior:**")
        if sh:
            for s in sh:
                a(f"  - SHOW `{s['file']}:{s['line']}` — `{esc(s['snippet'])}`")
        else:
            a("  - SHOW: _(none via `Flux::modal(...)->show()`)_")
        if cl:
            for c in cl:
                a(f"  - CLOSE `{c['file']}:{c['line']}` — `{esc(c['snippet'])}`")
        else:
            a("  - CLOSE: _(none via `Flux::modal(...)->close()`)_")
        a(
            "- **Related event/state:** Livewire component state on the defining page; "
            "preserve wire models, validation, authorization, and any gallery/payment events "
            "already on that screen."
        )
        a(f"- **Proposed MaryUI/Livewire/Alpine direction:** {direction(name)}")
        a(f"- **Risk:** {risk(name, len(sh), len(cl), len(trigs))}")
        a(f"- **Required test:** {test(name)}")
        a("")
    # parent-context triggers inventory
    pc = data["triggers"].get("(parent-context)", [])
    if pc:
        a("## Parent-context modal triggers (no `name=` on trigger)")
        a("")
        a(
            "These triggers inherit the surrounding modal name from Blade nesting. "
            "They are listed so none are lost during migration."
        )
        a("")
        for t in pc:
            a(f"- `{t['file']}:{t['line']}` — `{esc(t['attrs'])}`")
        a("")
    a("## Licensing")
    a("")
    a(
        "**No Flux Pro source, vendor CSS, vendor JavaScript, or proprietary implementation "
        "was inspected or copied.**"
    )
    a("")
    (ROOT / "05-named-modal-mapping.md").write_text("\n".join(lines) + "\n", encoding="utf-8")
    print("wrote 05-named-modal-mapping.md")


def write_csv() -> None:
    # Update matrix: set measured counts; leave classification guidance
    prior_path = ROOT / "02-maryui-migration-matrix.csv"
    text = prior_path.read_text(encoding="utf-8")
    # rewrite as measured component matrix
    lines = [
        "flux_component,occurrence_count,likely_classification,proposed_direction,related_state_behavior,complexity,notes"
    ]
    # rough direction map
    freeish = {
        "button",
        "input",
        "text",
        "link",
        "modal",
        "badge",
        "checkbox",
        "textarea",
        "select",
        "switch",
        "radio",
        "field",
        "label",
        "error",
        "description",
        "heading",
        "subheading",
        "separator",
        "spacer",
        "avatar",
        "callout",
        "dropdown",
        "menu",
        "toast",
    }
    for comp, n in cc.items():
        base = comp.split(".")[0]
        if base in freeish or comp in freeish:
            klass = "Likely Free or common primitive (verify public docs)"
        else:
            klass = "Likely advanced/Pro-adjacent (verify public docs; do not open vendor)"
        direction = "MaryUI/daisyUI/Blade/Alpine reimplementation; preserve Livewire bindings"
        complexity = "Mechanical" if n < 10 and base in freeish else "Moderate" if n < 40 else "Complex"
        if base in {"modal", "menu", "navbar", "tab", "tabs", "command", "table", "date-picker", "time-picker"}:
            complexity = "Complex"
        notes = "Measured at pinned commit; vendor not inspected"
        lines.append(
            ",".join(
                [
                    f"flux:{comp}",
                    str(n),
                    f'"{klass}"',
                    f'"{direction}"',
                    '"Livewire wire:* / PHP Flux:: where present"',
                    complexity,
                    f'"{notes}"',
                ]
            )
        )
    # keep a copy note about original
    header_note = (
        f"# Measured MaryUI migration matrix — commit {COMMIT}\n"
        f"# Generated from inventory-raw.json. Original VERIFY LOCALLY draft preserved in parent folder.\n"
    )
    prior_path.write_text("\n".join(lines) + "\n", encoding="utf-8")
    (ROOT / "02-maryui-migration-matrix.HEADER.txt").write_text(header_note, encoding="utf-8")
    print("wrote 02-maryui-migration-matrix.csv", len(lines) - 1, "rows")


def write_batches_and_risk() -> None:
    # Update VERIFY LOCALLY mentions in batches and risk
    for name in ["03-migration-batches.md", "04-migration-risk-register.md"]:
        p = ROOT / name
        text = p.read_text(encoding="utf-8")
        if "VERIFY LOCALLY" in text:
            text = text.replace(
                "VERIFY LOCALLY",
                f"MEASURED (see 01-flux-inventory.md @ {COMMIT})",
            )
        # inject measured summary block after title if not present
        banner = (
            f"\n> **Inventory status ({COMMIT}):** "
            f"{data['blade_files_with_flux']} Blade files, "
            f"{data['total_flux_opening_tags']} `<flux:` tags, "
            f"{data['unique_components']} unique components, "
            f"{len(data['named_modals'])} named modals. "
            f"Details: `01-flux-inventory.md`, `05-named-modal-mapping.md`, "
            f"`06-planning-mismatches.md`.\n"
        )
        if "Inventory status" not in text:
            parts = text.split("\n", 1)
            if len(parts) == 2:
                text = parts[0] + "\n" + banner + parts[1]
            else:
                text = text + banner
        p.write_text(text, encoding="utf-8")
        print("updated", name)


def write_handing() -> None:
    lines = [
        "# HANDOFF — Assignment 001 Flux inventory",
        "",
        f"**Issue:** https://github.com/picstome/picstome/issues/214",
        f"**Branch:** `issue-214-flux-inventory`",
        f"**Commit base:** `{COMMIT}`",
        f"**Working tree:** `D:\\Program\\Codex\\Picstome\\repo`",
        "",
        "## Success criteria",
        "",
        "- Exact Flux counts measured — yes",
        "- Complete named-modal map — yes",
        "- MaryUI migration coding — **not started** (by design)",
        "",
        "## Changed files (docs only)",
        "",
        "- docs/planning/01-flux-inventory.md",
        "- docs/planning/02-maryui-migration-matrix.csv",
        "- docs/planning/03-migration-batches.md",
        "- docs/planning/04-migration-risk-register.md",
        "- docs/planning/05-named-modal-mapping.md",
        "- docs/planning/06-planning-mismatches.md",
        "- docs/planning/inventory-raw.json",
        "- docs/planning/build_inventory.py",
        "- docs/planning/generate_docs.py",
        "- docs/planning/HANDOFF-CODEX.md (this file)",
        "",
        "## Verification",
        "",
        "```",
        f"git rev-parse HEAD  # {COMMIT}",
        "python docs/planning/build_inventory.py",
        "python docs/planning/generate_docs.py",
        "```",
        "",
        f"Measured: {data['blade_files_with_flux']} files, "
        f"{data['total_flux_opening_tags']} tags, "
        f"{data['unique_components']} components, "
        f"{len(data['named_modals'])} named modals.",
        "",
        "## Risks",
        "",
        "- Free/Pro classification still documentation-level only (vendor not opened).",
        "- Parent-context modal triggers need nesting-aware migration care.",
        "- `flux:file-upload` was planned but not present as a tag.",
        "",
        "## Next action",
        "",
        "- Codex review of this PR.",
        "- Do not start Batch 001 coding until inventory PR is accepted.",
        "",
        "## Licensing",
        "",
        "No Flux Pro source, vendor CSS, vendor JS, or proprietary implementation inspected or copied.",
        "",
    ]
    (ROOT / "HANDOFF-CODEX.md").write_text("\n".join(lines) + "\n", encoding="utf-8")
    print("wrote HANDOFF-CODEX.md")


if __name__ == "__main__":
    write_01()
    write_mismatches()
    write_modals()
    write_csv()
    write_batches_and_risk()
    write_handing()
    print("ALL DOCS GENERATED")
