#!/usr/bin/env python3
import ast
import re
import struct
import sys
from pathlib import Path


def parse_po(path):
    entries, entry, field = [], {}, None
    for raw in Path(path).read_text(encoding="utf-8").splitlines() + [""]:
        line = raw.strip()
        if not line:
            if "msgid" in entry:
                entries.append((entry["msgid"], entry.get("msgstr", "")))
            entry, field = {}, None
            continue
        if line.startswith("#"):
            continue
        match = re.match(r'(msgid|msgstr)\s+(".*")$', line)
        if match:
            field = match.group(1)
            entry[field] = ast.literal_eval(match.group(2))
        elif line.startswith('"') and field:
            entry[field] += ast.literal_eval(line)
    return dict(entries)


def compile_catalog(po_path, mo_path):
    messages = parse_po(po_path)
    keys = sorted(messages)
    ids, values, offsets = b"", b"", []
    for key in keys:
        a, b = key.encode(), messages[key].encode()
        offsets.append((len(a), len(ids), len(b), len(values)))
        ids += a + b"\0"; values += b + b"\0"
    count = len(keys); key_start = 28 + 16 * count; value_start = key_start + len(ids)
    output = struct.pack("<7I", 0x950412DE, 0, count, 28, 28 + 8 * count, 0, 0)
    output += b"".join(struct.pack("<2I", length, key_start + offset) for length, offset, _, _ in offsets)
    output += b"".join(struct.pack("<2I", length, value_start + offset) for _, _, length, offset in offsets)
    Path(mo_path).write_bytes(output + ids + values)


if __name__ == "__main__":
    compile_catalog(sys.argv[1], sys.argv[2])
