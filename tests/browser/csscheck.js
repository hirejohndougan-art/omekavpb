const csstree = require('css-tree'); const fs = require('fs'); const path = require('path');
const files = process.argv.slice(2); let problems = 0;
for (const f of files) {
  const src = fs.readFileSync(f, 'utf8');
  csstree.parse(src, { positions: true, onParseError: e => { problems++; console.log(path.basename(f) + ':' + (e.line || '?') + ' ' + e.message); } });
  // Declarations whose value doesn't match the CSS spec (ignoring custom properties and color-mix, which css-tree may not know).
  const ast = csstree.parse(src, { positions: true });
  csstree.walk(ast, { visit: 'Declaration', enter(node) {
    if (node.property.startsWith('--')) return;
    const r = csstree.lexer.matchDeclaration(node);
    if (r.error && !/var\(|color-mix|env\(|min\(|max\(|clamp\(|calc\(/.test(csstree.generate(node.value))) { problems++; console.log(path.basename(f) + ':' + node.loc.start.line + ' ' + node.property + ': ' + csstree.generate(node.value) + ' -> ' + r.error.message.split('\n')[0]); }
  }});
}
console.log(problems ? problems + ' problem(s)' : 'CSS OK');
