'use strict';

// Optional isolated Chromium check. Uses existing Playwright; no live systems.
const assert = require('node:assert/strict'), fs = require('node:fs'), path = require('node:path');
const {chromium} = require('playwright');
const source = fs.readFileSync(path.join(__dirname, '../src/visualization/', process.argv.includes('--minified') ? 'interaction.min.js' : 'interaction.js'), 'utf8');
const script = '<script>' + source + '</script>';
const child = script + '<style>body{margin:0}#list{height:100px;overflow:auto}#map{height:100px;background:#ddf}</style>'
    + '<div id="list"><div style="height:1000px">Scrollable list</div></div><div id="map">15</div>'
    + '<script>SymconVisualizationInteraction.registerWheelRegion(document.getElementById("map"),function(e){'
    + 'var m=document.getElementById("map");m.textContent=Number(m.textContent)+Math.sign(e.deltaY);return true;});</script>';

(async function () {
    const browser = await chromium.launch({headless: true,
        ...(process.env.VISUALIZATION_BROWSER_EXECUTABLE ? {executablePath: process.env.VISUALIZATION_BROWSER_EXECUTABLE} : {})});
    try {
        for (const mode of ['foreign', 'foreign-opaque', 'srcdoc', 'opaque']) {
            const page = await browser.newPage({viewport: {width: 800, height: 500}}), errors = [];
            page.on('pageerror', e => errors.push(e.message));
            try {
                const foreign = mode.startsWith('foreign');
                const host = foreign
                    ? '<script>var received=0;addEventListener("message",function(e){received++;if(Array.isArray(e.data))throw Error("Unexpected Message envelope");});</script>'
                    : script;
                await page.setContent(host + '<iframe style="width:320px;height:220px;border:0" '
                    + (foreign ? '' : 'name="burki24.wheel.v1" ') + '></iframe>');
                await page.locator('iframe').evaluate((frame, input) => {
                    if (input.mode.includes('opaque')) frame.src = 'data:text/html;base64,' + btoa(input.child);
                    else frame.srcdoc = input.child;
                }, {mode, child});
                const inner = page.frames().find(frame => frame.parentFrame());
                await inner.locator('#map').waitFor();
                assert.equal(await inner.evaluate(() => window.name), foreign ? '' : 'burki24.wheel.v1');
                const list = await inner.locator('#list').boundingBox(), map = await inner.locator('#map').boundingBox();
                await page.mouse.move(list.x + 50, list.y + 50);
                await page.mouse.wheel(0, 120);
                await inner.waitForFunction(() => document.getElementById('list').scrollTop > 0);
                const scrolled = await inner.locator('#list').evaluate(el => el.scrollTop);
                await page.mouse.wheel(0, -60);
                await inner.waitForFunction(before => document.getElementById('list').scrollTop < before, scrolled);
                assert.equal(await inner.locator('#map').textContent(), '15', 'List scrolling must not zoom the map.');
                await page.mouse.move(map.x + 50, map.y + 50);
                await page.mouse.wheel(0, 120);
                await inner.waitForFunction(() => document.getElementById('map').textContent === '16');
                await page.mouse.wheel(0, -120);
                await inner.waitForFunction(() => document.getElementById('map').textContent === '15');
                if (foreign) {
                    assert.equal(await page.evaluate(() => received), 0, 'A foreign host must receive no bridge messages.');
                } else {
                    // The native app can retarget a wheel to the outer loader at (0, 0).
                    await page.waitForFunction(() => SymconVisualizationInteraction.point({clientX:0,clientY:0}).clientX > 0);
                    await page.evaluate(() => document.body.dispatchEvent(new WheelEvent('wheel', {deltaY:120,bubbles:true,cancelable:true})));
                    await inner.waitForFunction(() => document.getElementById('map').textContent === '16');
                    await page.mouse.move(list.x + 50, list.y + 50);
                    await page.mouse.wheel(0, 60);
                    const before = await inner.locator('#list').evaluate(el => el.scrollTop);
                    await page.evaluate(() => document.body.dispatchEvent(new WheelEvent('wheel', {deltaY:60,bubbles:true,cancelable:true})));
                    await inner.waitForFunction(before => document.getElementById('list').scrollTop > before, before);
                    assert.equal(await inner.locator('#map').textContent(), '16');
                }
                assert.deepEqual(errors, [], mode + ' must remain free of uncaught host errors.');
                console.log(mode + ': real wheel in both directions, isolated list/zoom and frame routing passed.');
            } finally { await page.close(); }
        }
    } finally { await browser.close(); }
}()).catch(error => {console.error(error); process.exitCode = 1;});
