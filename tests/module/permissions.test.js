'use strict';
// Who may open and save the editor.
const H = require('../lib/harness');

(async () => {
  const t = H.suite('permissions');
  const pageId = await H.createPage('perm', [{ 'o:layout': 'html', 'o:data': { html: '<p>x</p>' } }]);
  const url = H.editorUrl(pageId);
  const code = async (u, opts = {}) => (await fetch(u, Object.assign({ redirect: 'manual' }, opts))).status;

  const anon = await fetch(url, { redirect: 'manual' });
  t.ok(anon.status === 302 && /login/.test(anon.headers.get('location') || ''), 'logged-out user sent to login');
  const cookie = H.readCookie();
  t.ok(await code(url, { headers: { Cookie: cookie } }) === 200, 'admin can open the editor');
  t.ok(await code(url.replace('/' + H.SITE + '/', '/no-such-site/'), { headers: { Cookie: cookie } }) === 404, 'wrong site slug is 404');
  t.ok(await code(H.editorUrl(99999999), { headers: { Cookie: cookie } }) === 404, 'missing page is 404');
  t.ok(await code(url + '/lookup?type=items', { headers: { Cookie: cookie } }) === 200, 'admin can use item lookup');
  t.ok(await code(url + '/save', { headers: { Cookie: cookie } }) === 405, 'save needs POST');
  const anonPreview = await fetch(url + '/preview', { method: 'POST', redirect: 'manual', body: '{}' });
  t.ok(anonPreview.status === 302, 'logged-out preview sent to login');

  const rc = await H.login('researcher@example.com', 'Test-password-123');
  if (await code(H.BASE + '/admin', { headers: { Cookie: rc } }) !== 200) { t.ok(false, 'researcher login failed; run test-env/set-researcher-password.sh'); return t.done(); }
  t.ok(await code(url, { headers: { Cookie: rc } }) === 403, 'researcher refused on edit');
  t.ok(await code(url + '/lookup?type=items', { headers: { Cookie: rc } }) === 403, 'researcher refused on lookup');
  t.ok(await code(url + '/save', { method: 'POST', headers: { Cookie: rc, 'Content-Type': 'application/json' }, body: '{}' }) === 403, 'researcher refused on save');
  t.ok(await code(url + '/preview', { method: 'POST', headers: { Cookie: rc, 'Content-Type': 'application/json' }, body: '{}' }) === 403, 'researcher refused on preview');
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
