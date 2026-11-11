// اجرای مرجع JS روی fixtureها و مقایسه با expected
const path = require('path'), fs = require('fs');
const WL = require(path.resolve(__dirname, '../public/assets/js/modules/workload.js'));
const dir = path.resolve(__dirname, '../tests/workload'); let fail = 0;
for (const f of fs.readdirSync(dir).filter((x) => x.endsWith('.input.json')).sort()) {
  const inp = JSON.parse(fs.readFileSync(path.join(dir, f)));
  const exp = JSON.parse(fs.readFileSync(path.join(dir, f.replace('.input', '.expected'))));
  const loads = WL.computeLoads(inp).map((r) => ({ member_id: r.member.id, name: r.member.name, capacity: r.capacity, od: r.od, wk: r.wk, nd: r.nd, load: r.load, pct: r.pct, level: r.level, open: r.open }));
  const res = WL.suggestRebalance(inp);
  const got = { loads, suggestions: res.suggestions, unresolved: res.unresolved };
  const ok = JSON.stringify(got) === JSON.stringify(exp);
  if (!ok) fail++;
  console.log((ok ? 'PASS ' : 'FAIL ') + f);
}
process.exit(fail ? 1 : 0);
