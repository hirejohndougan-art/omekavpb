'use strict';
// v0.7.0: the thirteen newer Special Blocks, saved through the API and shown
// on the public site, plus a quick check in the visual editor.
const H = require('../lib/harness');

(async () => {
  const t = H.suite('special blocks: v0.7.0 blocks');
  const fx = await H.specialFixtures();
  const core = await H.fixtures();
  const oral = (await H.api('GET', 'items?fulltext_search=Oral%20history%20interview'))[0]['o:id'];
  const today = new Date(), iso = d => d.toISOString().slice(0, 10);
  const plus = n => iso(new Date(today.getTime() + n * 864e5));
  const blocks = [
    ['specialAnnouncement', { text: 'Closed Monday for the holiday.', link_text: 'Hours', link_url: '/s/dhs/page/welcome', style: 'warning', start_date: plus(-1), end_date: plus(1) }],
    ['specialAnnouncement', { text: 'Future notice', start_date: plus(10) }],
    ['specialSlideshow', { seconds: 5, slides: [{ title: 'The depot', item_id: core.items[0], alt: 'The depot in 1911', button_text: 'Visit', url: '/s/dhs/page/welcome' }, { title: 'The banner', asset_id: core.asset }, { title: 'No image' }] }],
    ['specialGallery', { mode: 'chosen', items: [{ item_id: core.items[0], caption: 'First' }, { item_id: core.items[1] }] }],
    ['specialGallery', { mode: 'source', site_scope: 'selected', site_ids: [fx.legacy], count: 10, heading: 'From the Legacy Center' }],
    ['specialTopicTiles', { tiles: [{ title: 'Railroad', item_id: core.items[0], url: '/s/dhs/item?fulltext_search=railroad' }, { title: 'Schools', asset_id: core.asset, text: 'Classrooms and teachers' }, { title: '' }] }],
    ['specialBrowseByDecade', { site_scope: 'all', from: 1900, to: 1960 }],
    ['specialSubjectsList', { site_scope: 'all', style: 'cloud' }],
    ['specialOralHistory', { item_id: oral }],
    ['specialDocumentTranscription', { item_id: oral, transcript_property: 'bibo:content' }],
    ['specialPeople', { people: [{ name: 'Pat Doe', role: 'Board president', asset_id: core.asset, bio: 'Loves trains.', email: 'pat@example.org' }, { name: 'No Email', email: 'not-an-email' }] }],
    ['specialPartners', { partners: [{ name: 'City of Dickinson', asset_id: core.asset, url: 'https://example.org' }, { name: 'Text Only' }], grayscale: '1' }],
    ['specialFundraising', { title: 'Save the depot roof', goal: 20000, raised: 12400, button_url: 'https://example.org/donate' }],
    ['specialNewsletter', { action: 'https://example.us1.list-manage.com/subscribe/post?u=abc&id=def', ask_name: '1' }],
    ['specialNewsletter', { heading: 'Insecure', action: 'http://example.org/subscribe' }],
    ['specialCitation', { organization: 'Dickinson Historical Society', reuse: '<p>Ask before reuse.</p>', contact_url: 'mailto:info@example.org' }]
  ].map(([l, d]) => ({ 'o:layout': l, 'o:data': d }));
  const pageId = await H.createPage('Newer blocks', blocks);
  const page = await H.api('GET', 'site_pages/' + pageId);
  const res = await fetch(`${H.BASE}/s/${H.SITE}/page/${page['o:slug']}`);
  const html = await res.text();
  t.ok(res.status === 200 && !/Fatal error|Warning:|Notice:/.test(html), 'page renders without PHP errors');
  const part = cls => { const i = html.indexOf('special-blocks ' + cls); if (i < 0) return ''; const j = html.indexOf('class="special-blocks ', i + 20); return html.slice(i, j < 0 ? undefined : j); };

  const ann = part('special-blocks-announcement');
  t.ok(/Closed Monday for the holiday\./.test(ann) && /special-blocks-announcement-warning/.test(ann) && /data-dismiss-key="vpb-announcement-/.test(ann), 'announcement shows in its date window, closable');
  t.ok(!/Future notice/.test(html), 'a future announcement stays hidden');
  t.ok(/announcement\.js/.test(html), 'announcement script loads');

  const slides = part('special-blocks-slideshow');
  t.ok((slides.match(/class="special-blocks-slide"/g) || []).length === 2 && /alt="The depot in 1911"/.test(slides) && /data-seconds="5"/.test(slides), 'slideshow: slides with images, descriptions, timing; image-less slide skipped');
  t.ok(/aria-roledescription="carousel"/.test(slides) && /slideshow\.js/.test(html), 'slideshow is an accessible carousel with its script');

  const galleries = html.split('special-blocks special-blocks-gallery"').slice(1);
  t.ok(galleries.length === 2, 'two galleries');
  t.ok(/data-caption="First"/.test(galleries[0]) && /data-large="[^"]*large/.test(galleries[0]), 'chosen gallery: captions and large images');
  t.ok(/From the Legacy Center/.test(galleries[1]) && /Legacy portrait/.test(galleries[1]) && !/Railroad photo/.test(galleries[1]), 'source gallery: items from the chosen site only');

  const tiles = part('special-blocks-topic-tiles');
  t.ok(/href="\/s\/dhs\/item\?fulltext_search=railroad"/.test(tiles) && /Classrooms and teachers/.test(tiles) && (tiles.match(/special-blocks-tile"/g) || []).length === 2, 'topic tiles with links and text; empty tile skipped');

  const dec = part('special-blocks-browse-by-decade');
  t.ok(/1910s/.test(dec) && /1950s/.test(dec) && !/1920s/.test(dec), 'decades with items (1910s, 1950s), empty ones hidden');
  t.ok(/property%5B0%5D%5Btype%5D=sw&amp;property%5B0%5D%5Btext%5D=191/.test(dec) || /property%5B0%5D%5Btext%5D=191/.test(dec), 'decade links search dates starting 191');

  const subj = part('special-blocks-subjects-list');
  const order = ['Railroad', 'Maps', 'Schools'].map(x => subj.indexOf('>' + x));
  t.ok(order.every(i => i > 0) && order[0] < order[1], 'subjects listed, most used first: ' + order);
  t.ok(/>Railroad <span class="special-blocks-count">\(2\)/.test(subj), 'subject counts: Railroad has 2 items');
  t.ok(/special-blocks-weight-5/.test(subj), 'word cloud weights');

  const oralPart = part('special-blocks-oral-history');
  t.ok(/<audio/.test(oralPart) && /When did you first ride the train\?/.test(oralPart) && /1978-05-02/.test(oralPart), 'oral history: player, transcript, date');
  t.ok(/<p>Resident: In 1925, to Galveston\.<\/p>/.test(oralPart), 'transcript split into paragraphs');
  t.ok(/This item has no file of that kind/.test(part('special-blocks-document-transcription')), 'document transcription says so when the item has no image');

  const people = part('special-blocks-people');
  t.ok(/Pat Doe/.test(people) && /Board president/.test(people) && /mailto:pat@example.org/.test(people) && !/not-an-email/.test(people), 'people cards; bad emails not linked');
  const partners = part('special-blocks-partners');
  t.ok(/alt="City of Dickinson"/.test(partners) && /Text Only/.test(partners) && /special-blocks-logos-gray/.test(partners), 'partner logos with names');
  const fund = part('special-blocks-fundraising');
  t.ok(/\$12,400/.test(fund) && /\$20,000/.test(fund) && /aria-valuenow="62"/.test(fund) && /width: 62%/.test(fund), 'fundraising: amounts and 62% bar');
  const news = html.split('special-blocks special-blocks-newsletter"').slice(1);
  t.ok(news.length === 1 && /action="https:\/\/example.us1.list-manage.com\/subscribe\/post\?u=abc&amp;id=def"/.test(news[0]) && /name="EMAIL"/.test(news[0]) && /name="FNAME"/.test(news[0]), 'newsletter form posts to the service; insecure address not shown');
  const cite = part('special-blocks-citation');
  t.ok(/&quot;Newer blocks\.&quot; DHS, Dickinson Historical Society\. http/.test(cite) && /Accessed /.test(cite), 'citation filled in for this page');
  t.ok(/Ask before reuse\./.test(cite) && /mailto:info@example.org/.test(cite), 'reuse text and contact link');

  // ---- visual editor: palette and live previews
  const ed = await H.openEditor(pageId), u = H.ui(ed);
  const labels = [...ed.d.querySelectorAll('.pal-label')].map(x => x.textContent);
  t.ok(['Announcement bar', 'Featured slideshow', 'Gallery', 'Explore by topic', 'Browse by decade', 'Subjects list', 'Oral history', 'Document and transcription', 'People', 'Partners and sponsors', 'Fundraising progress', 'Newsletter sign-up', 'How to cite and reuse'].every(l => labels.includes(l)), 'all new blocks in the palette');
  const fundLive = () => u.el(u.blk('specialFundraising')).querySelector('.b-live');
  for (let k = 0; k < 30 && !(fundLive() && /\$/.test(fundLive().textContent)); k++) await H.sleep(300); // wait for its server preview (up to 9 s)
  const fundPreview = fundLive();
  t.ok(fundPreview && /\$12,400/.test(fundPreview.textContent), 'live preview in the editor: ' + (fundPreview ? fundPreview.textContent.replace(/\s+/g, ' ').slice(0, 120) : 'none'));
  const futurePreview = [...ed.d.querySelectorAll('.blk')].map(b => b.textContent).find(x => /Future notice/.test(x));
  t.ok(futurePreview && /Not showing on the site today/.test(futurePreview), 'editor preview shows scheduled announcements, with a note');
  t.ok(!ed.errs.length, 'no editor errors ' + ed.errs);
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
