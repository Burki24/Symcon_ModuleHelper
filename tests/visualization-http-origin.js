'use strict';

const assert = require('node:assert/strict');
const {resolve} = require('../src/visualization/http-origin.js');
const expected = 'https://symcon.example.invalid:3777';
assert.equal(resolve({location: {href: expected + '/webfront/#view'}}), expected);
assert.equal(resolve({location: {origin: expected}}), expected);
assert.equal(resolve({location: {href: 'data:text/html,example'}, document: {referrer: expected + '/#view'}}), expected);
assert.equal(resolve({location: {href: 'about:srcdoc', ancestorOrigins: [expected]}}), expected);
assert.equal(resolve({location: {}, parent: {location: {href: expected + '/view'}}}), expected);
const blocked = {location: {}, document: {referrer: expected + '/view'}};
Object.defineProperty(blocked, 'parent', {get() { throw new Error('cross origin'); }});
assert.equal(resolve(blocked), expected);
assert.equal(resolve({location: {origin: expected}, document: {referrer: 'https://other.invalid/'}}), expected);
const native = {location: {href: 'data:text/html,widget', origin: 'null', ancestorOrigins: []},
    document: {baseURI: expected + '/hook/ipsviewconnect/index.html?view=test#ignored', referrer: ''}};
Object.defineProperty(native, 'parent', {get() { throw new Error('native WebView'); }});
assert.equal(resolve(native), expected, 'Native IPSView HTMLBox may provide only its HTTP base URL.');
assert.equal(resolve({location: {href: 'about:blank'}, document: {baseURI: expected + '/client'}, parent: {location: {href: 'https://shell.invalid/'}}}), expected, 'The embedded document base identifies the server before a hosting application shell.');
assert.equal(resolve({location: {origin: expected}, document: {baseURI: 'https://other.invalid/'}}), expected, 'Real HTTP documents keep their own origin.');
for (const baseURI of ['', 'null', 'file:///local', 'data:text/html,example', 'javascript:alert(1)', 'https://user:pass@example.invalid/']) {
    assert.equal(resolve({location: {href: 'data:text/html,example'}, document: {baseURI}}), null, 'Unsafe base URLs cannot become endpoints.');
    assert.equal(resolve({location: {}, document: {baseURI, referrer: expected + '/view'}}), expected, 'An unusable base must preserve the referrer fallback.');
}
for (const referrer of ['', 'null', 'file:///local', 'javascript:alert(1)', 'https://user:pass@example.invalid/']) {
    assert.equal(resolve({location: {href: 'data:text/html,example'}, document: {referrer}}), null);
}
console.log('Embedded visualization HTTP origin contracts passed.');
