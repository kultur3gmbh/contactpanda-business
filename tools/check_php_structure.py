#!/usr/bin/env python3
import pathlib
import re
import sys

failed = False
for base in sys.argv[1:]:
    for path in pathlib.Path(base).rglob("*.php"):
        text = path.read_text(encoding="utf-8")
        if not text.lstrip().startswith("<?php"):
            print(f"{path}: missing PHP opening tag", file=sys.stderr)
            failed = True
        if "<?= " in text or "<?=" in text:
            print(f"{path}: short echo tag is not permitted", file=sys.stderr)
            failed = True
        if re.search(r"\b(eval|shell_exec|exec|passthru)\s*\(", text):
            print(f"{path}: unsafe execution primitive", file=sys.stderr)
            failed = True
if failed:
    raise SystemExit(1)
print("PHP structure check passed")
