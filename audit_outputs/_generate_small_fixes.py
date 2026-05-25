#!/usr/bin/env python3
import json, datetime, sys
p = '/tmp/heratio_open_issues.json'
with open(p) as f:
    arr = json.load(f)
items = []
for it in arr:
    num = it.get('number')
    title = it.get('title') or ''
    body = it.get('body') or ''
    labels = [l.get('name') for l in it.get('labels',[])]
    created = it.get('createdAt')
    text = (title + '\n' + body).strip()
    length = len(text)
    tlow = title.lower()
    if any(x in tlow for x in ('typo','readme','docs','documentation','spell')):
        note = 'Very small — documentation/typo likely.'
    elif length < 200:
        note = 'Small — likely trivial change (docs, config, short fix).'
    elif length < 500:
        note = 'Moderate — small code or test change likely.'
    else:
        note = 'Larger — more effort or investigation.'
    items.append({'number': num, 'title': title, 'labels': labels, 'createdAt': created, 'length': length, 'note': note})

items.sort(key=lambda x: x['length'])
top = items[:10]
md_lines = ['# Top 10 smallest open issues (by title+body length)\n']
for it in top:
    num = it['number']
    md_lines.append(f"- #{num} — {it['title']}")
    md_lines.append(f"  - Labels: {', '.join(it['labels']) if it['labels'] else 'none'}")
    md_lines.append(f"  - Created: {it['createdAt']}")
    md_lines.append(f"  - Length: {it['length']} chars")
    md_lines.append(f"  - Quick judgment: {it['note']}\n")

md = '\n'.join(md_lines)
open('audit_outputs/small_fixes_top10.md','w').write(md)
json_out = {'generatedAt': datetime.datetime.utcnow().isoformat()+'Z', 'top': top}
open('audit_outputs/small_fixes_top10.json','w').write(json.dumps(json_out, indent=2))
print('WROTE audit_outputs/small_fixes_top10.md and .json')
for it in top:
    print(f"#{it['number']} — {it['title']} | labels: {', '.join(it['labels']) or 'none'} | created: {it['createdAt']} | length: {it['length']} | note: {it['note']}")
