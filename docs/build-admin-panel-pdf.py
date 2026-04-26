"""Build admin-panel-guide.pdf from admin-panel-guide.md (Edge headless, Windows)."""
import subprocess
import sys
from pathlib import Path

import markdown

ROOT = Path(__file__).resolve().parent
MD = ROOT / "admin-panel-guide.md"
HTML = ROOT / "admin-panel-guide-temp.html"
PDF = ROOT / "admin-panel-guide.pdf"
EDGE = Path(r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe")

CSS = (
    "body{font-family:Segoe UI,system-ui,sans-serif;max-width:800px;margin:1.5rem auto;"
    "padding:0 1.2rem;line-height:1.5;color:#1a1a1a;font-size:11pt}"
    "h1{font-size:22pt;border-bottom:1px solid #999;padding-bottom:.25em}"
    "h2{font-size:15pt;margin-top:1.4em;border-bottom:1px solid #ddd}"
    "h3{font-size:12pt;margin-top:1em}code{background:#f2f2f2;padding:.05em .25em}"
    "pre{background:#f2f2f2;padding:.75rem;overflow:auto;font-size:10pt}"
    "table{border-collapse:collapse;width:100%;margin:.8em 0;font-size:10pt}"
    "th,td{border:1px solid #ccc;padding:.35em .5em}th{background:#eee}"
    "ul,ol{padding-left:1.4em}"
)

def main() -> int:
    if not MD.is_file():
        print("Missing", MD, file=sys.stderr)
        return 1
    if not EDGE.is_file():
        print("Edge not found at", EDGE, file=sys.stderr)
        return 1

    body = markdown.markdown(MD.read_text(encoding="utf-8"), extensions=["tables", "fenced_code", "nl2br"])
    HTML.write_text(
        f"<!DOCTYPE html><html><head><meta charset=utf-8><title>Guide</title>"
        f"<style>{CSS}</style></head><body>{body}</body></html>",
        encoding="utf-8",
    )
    try:
        subprocess.run(
            [str(EDGE), "--headless", "--disable-gpu", f"--print-to-pdf={PDF}", HTML.as_uri()],
            check=True,
        )
    finally:
        if HTML.is_file():
            HTML.unlink()
    print("Wrote", PDF)
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
