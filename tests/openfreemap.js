'use strict';

const assert = require('node:assert/strict');
const api = require('../src/maps/openfreemap.js');
const style = {version: 8, center: [10, 20], zoom: 1, sources: {ofm: {type: 'vector', url: 'https://example.invalid/source.json'}}, layers: [
    {id: 'background', type: 'background', paint: {'background-color': '#ffffff'}},
    {id: 'water', type: 'fill', 'source-layer': 'water', paint: {'fill-color': '#abcdef'}},
    {id: 'roads', type: 'line', 'source-layer': 'transportation'},
    {id: 'buildings', type: 'fill', 'source-layer': 'building'},
    {id: 'poi', type: 'symbol', 'source-layer': 'poi', layout: {'text-field': ['get', 'name']}},
    {id: 'place', type: 'symbol', 'source-layer': 'place', layout: {'text-field': ['get', 'name']}}
]};
const config = {theme: 'light', lightStyle: 'liberty', darkStyle: 'dark', customColors: true, lightWaterColor: '#123456', lightRoadColor: '#654321', lightTextColor: '#111111', showPOI: false, showBuildings: false, language: 'de'};
assert.equal(api.styleURL(config, false), 'https://tiles.openfreemap.org/styles/liberty');
assert.equal(api.styleURL(config, true), 'https://tiles.openfreemap.org/styles/dark');
for (const bad of ['http://example.invalid/style', 'https://user:pass@example.invalid/style', 'javascript:bad', 'https://example.invalid/#secret']) {
    assert.equal(api.styleURL({...config, lightStyleURL: bad}, false), null);
}
assert.equal(api.darkMode({theme: 'auto'}, {document: {body: {}}, getComputedStyle() { return {backgroundColor: 'rgb(30, 40, 50)'}; }}), true);
assert.equal(api.darkMode({theme: 'light'}, {matchMedia() { return {matches: true}; }}), false);
const rendered = api.prepareStyle(style, config, false);
assert.equal(rendered.layers[1].paint['fill-color'], '#123456');
assert.equal(rendered.layers[2].paint['line-color'], '#654321');
assert.equal(rendered.layers[3].layout.visibility, 'none');
assert.equal(rendered.layers[4].layout.visibility, 'none');
assert.equal(rendered.layers[5].paint['text-color'], '#111111');
assert.deepEqual(rendered.layers[5].layout['text-field'], ['coalesce', ['get', 'name:de'], ['get', 'name'], ['get', 'name:latin']]);
assert.equal(rendered.center, undefined, 'Style camera defaults cannot reset the user viewport.');
assert.equal(rendered.zoom, undefined);
assert.equal(style.layers[1].paint['fill-color'], '#abcdef', 'The cached upstream style remains unchanged.');
assert.equal(api.prepareStyle(style, {...config, customColors: false}, false).layers[1].paint['fill-color'], '#abcdef');

async function lifecycle() {
    let creates = 0;
    const applied = [];
    const requests = [];
    const element = {dataset: {}};
    const vector = {on() {}, setStyle(value) { applied.push(value); }};
    const layer = {addTo() { creates++; return this; }, getMaplibreMap() { return vector; }};
    const map = {getContainer() { return element; }, setView() { throw new Error('Style changes must not touch the Leaflet camera.'); }, attributionControl: {addAttribution() {}, removeAttribution() {}}, removeLayer() {}};
    const environment = {L: {maplibreGL() { return layer; }}, maplibregl: {}, AbortController, setTimeout, clearTimeout,
        async fetch(url, options) {
            requests.push({url, options});
            return {ok: true, async json() { return style; }};
        }};
    const controller = api.createController(map, environment, () => {});
    await controller.update(config);
    await controller.update(config);
    assert.equal(applied.length, 1, 'Identical data updates must not reload a style.');
    await controller.update({...config, lightWaterColor: '#aabbcc'});
    assert.equal(applied.length, 2);
    assert.equal(requests.length, 1, 'Color edits reuse the cached style.');
    await controller.update({...config, theme: 'dark'});
    assert.equal(creates, 1, 'Theme and color changes retain the same vector map.');
    assert.equal(element.dataset.mapTheme, 'dark');
    assert.equal(requests.length, 2);
    assert.deepEqual(Object.keys(requests[0].options).sort(), ['credentials', 'mode', 'redirect', 'signal']);
    assert.equal(requests[0].options.credentials, 'omit', 'No account credentials or bearer headers reach the provider.');
    let requestedScript = '';
    const loaderEnvironment = {L: {}, setTimeout, clearTimeout, SymconHTTPOrigin: {resolve() { return 'https://symcon.example.invalid'; }}, document: {
        createElement() { return {}; },
        head: {appendChild(script) {
            if (script.src) {
                requestedScript = script.src;
                loaderEnvironment.maplibregl = {};
                queueMicrotask(() => script.onload());
            } else loaderEnvironment.L.maplibreGL = () => {};
        }}
    }};
    await api.loadRenderer({path: '/hook/map/assets/maplibre-gl.js', serverURL: '', bridge: 'synthetic bridge'}, loaderEnvironment);
    assert.equal(requestedScript, 'https://symcon.example.invalid/hook/map/assets/maplibre-gl.js', 'Embedded native pages load the renderer from the inferred Symcon origin.');
    await assert.rejects(api.loadRenderer({path: '/asset.js', serverURL: 'file:///tmp', bridge: ''}, loaderEnvironment));
    controller.destroy();
    console.log('OpenFreeMap themes, palette, details and persistent layer contracts passed.');
}
lifecycle().catch(error => { console.error(error); process.exitCode = 1; });
