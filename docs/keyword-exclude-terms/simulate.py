import json, re, sys
# Run from the repo root: python3 docs/keyword-exclude-terms/simulate.py
# Reads storage/app/kw/{dump,proposals,overrides}.json, writes simulation.json.
base = 'storage/app/kw/'
pages = json.load(open(base + 'dump.json'))
props = json.load(open(base + 'proposals.json'))
overrides = json.load(open(base + 'overrides.json'))
for slug, ov in overrides.items():
    if slug in props:
        props[slug]['exclude_terms'] = [t for t in props[slug]['exclude_terms'] if t not in ov.get('drop', [])] + ov.get('add', [])
BLOCK = {'akcija','akcijos','nuolaida','nuolaidos','iki','maxima','lidl','rimi','norfa'}

def split(label):
    m = re.match(r'^(.*) \[([^\]]*)\]$', label)
    return (m.group(1), m.group(2)) if m else (label, '')

def hits(label, term):
    name, brand = split(label)
    return term in name.lower() or term in brand.lower()

out = []
for p in pages:
    pr = props.get(p['slug'])
    if not pr or p['shown'] == 0:
        continue
    names = p['names']
    off = {i for i in pr['off_intent'] if 0 <= i < len(names)}
    good = [n for i, n in enumerate(names) if i not in off]
    current = [t.lower() for t in (p['exclude_terms'] or [])]
    accepted, rejected = [], []
    for t in pr['exclude_terms']:
        if len(t) < 3 or t in BLOCK or t in current:
            rejected.append({'term': t, 'reason': 'blocked/too short/already present'}); continue
        collateral = [n for n in good if hits(n, t)]
        if collateral:
            rejected.append({'term': t, 'reason': 'would drop on-intent', 'examples': collateral[:3]}); continue
        if not any(hits(n, t) for n in names):
            rejected.append({'term': t, 'reason': 'matches nothing shown'}); continue
        accepted.append(t)
    removed = [n for n in names if any(hits(n, t) for t in accepted)]
    missed = [names[i] for i in sorted(off) if names[i] not in removed]
    out.append({
        'slug': p['slug'], 'title': p['title'], 'h1': p['h1'],
        'before': len(names), 'after': len(names) - len(removed),
        'current_exclude': current, 'add_exclude': accepted, 'rejected': rejected,
        'removed': removed, 'flagged_not_removed': missed, 'notes': pr['notes'],
        'kept_sample': [n for n in names if n not in removed][:8],
    })
json.dump(out, open(base + 'simulation.json', 'w'), ensure_ascii=False, indent=1)
ch = [o for o in out if o['add_exclude']]
print(f"{len(out)} pages simulated; {len(ch)} pages get new excludes; removed {sum(len(o['removed']) for o in out)} of {sum(o['before'] for o in out)} products")
print("pages going to 0:", [o['slug'] for o in out if o['after'] == 0])
print("rejected terms:", sum(len(o['rejected']) for o in out), "; flagged but not removed:", sum(len(o['flagged_not_removed']) for o in out))
