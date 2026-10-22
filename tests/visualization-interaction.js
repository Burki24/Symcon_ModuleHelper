'use strict';
const assert = require('node:assert/strict');
const {createRouter} = require(process.argv.includes('--minified') ? '../src/visualization/interaction.min.js' : '../src/visualization/interaction.js');
const listeners = {};
const doc = {addEventListener: (name, fn) => { listeners[name] = fn; }, removeEventListener() {}};
const env = {document: doc, addEventListener() {}, removeEventListener() {}, getComputedStyle: () => ({overflowY: 'auto', overflowX: 'auto'})};
const router = createRouter(env);
const list = {tagName: 'ASIDE', clientWidth: 100, clientHeight: 100, scrollHeight: 600, scrollWidth: 300, scrollTop: 0, scrollLeft: 0};
doc.elementFromPoint = () => list;
const wheel = extra => Object.assign({type: 'wheel', target: {tagName: 'BODY'}, clientX: 0, clientY: 0, deltaX: 0, deltaY: 2, deltaMode: 1,
    preventDefault() { this.prevented = true; }, stopImmediatePropagation() { this.stopped = true; }}, extra);
router.rememberPointer({pointerType: 'mouse', clientX: 50, clientY: 60});
let event = wheel();
assert.equal(router.handle(event), true);
assert.equal(list.scrollTop, 32, 'Native retargeting with zero coordinates must scroll the hit list.');
assert(event.prevented && event.stopped);
router.handle(wheel({shiftKey: true}));
assert.equal(list.scrollLeft, 32);
event = wheel({ctrlKey: true});
assert.equal(router.handle(event), false);
assert(!event.prevented);
let zoom = 0;
const map = {tagName: 'DIV', getBoundingClientRect: () => ({left: 0, top: 0, width: 100, height: 100}), clientHeight: 100};
doc.elementFromPoint = () => map;
router.registerWheelRegion(map, e => { zoom += Math.sign(e.deltaY); return true; });
router.handle(wheel({deltaY: -120, deltaMode: 0}));
assert.equal(zoom, -1, 'A registered map action must receive native retargeted wheels.');
router.clearPointer();
assert.equal(router.point(wheel()).clientX, 0);
router.dispose();

// The native app may send the wheel to an outer HTML loader, not its HTTP child.
const parentListeners = {}, childListeners = {};
const parent = {innerWidth: 500, innerHeight: 300, getComputedStyle: env.getComputedStyle,
    addEventListener: (n, f) => { parentListeners[n] = f; }, removeEventListener() {}};
parent.parent = parent;
const child = {innerWidth: 100, innerHeight: 50, getComputedStyle: env.getComputedStyle, parent,
    addEventListener: (n, f) => { childListeners[n] = f; }, removeEventListener() {}};
const childList = {...list, scrollTop: 0, scrollLeft: 0};
const frame = {tagName: 'IFRAME', contentWindow: child, getBoundingClientRect: () => ({left: 10, top: 20, width: 200, height: 100})};
parent.document = {querySelectorAll: () => [frame], elementFromPoint: () => frame};
child.document = {elementFromPoint: () => childList};
parent.postMessage = data => parentListeners.message({source: child, data});
child.postMessage = data => childListeners.message({source: parent, data});
const outer = createRouter(parent), inner = createRouter(child);
inner.rememberPointer({clientX: 20, clientY: 25});
assert.equal(outer.point(wheel()).clientX, 50, 'Child pointer coordinates must account for scaled iframe bounds.');
outer.handle(wheel({deltaY: 40, deltaMode: 0}));
assert.equal(childList.scrollTop, 40, 'The actual direct-parent bridge must reach the inner scrolling area.');
parent.document.activeElement = frame;
parent.document.hasFocus = () => true;
parentListeners.blur();
assert.equal(outer.point(wheel()).clientX, 50, 'Focusing a participating iframe must retain its pointer position.');
parent.document.hasFocus = () => false;
parentListeners.blur();
assert.equal(outer.point(wheel()).clientX, 0, 'Leaving the visualization must clear the pointer.');
childListeners.message({source: {}, data: ['burki24.wheel.v1', 'wheel', 0.2, 0.5, 0, 50]});
assert.equal(childList.scrollTop, 40, 'An unrelated sender cannot issue UI input.');
childListeners.message({source: parent, data: ['burki24.wheel.v1', 'wheel', NaN, 0.5, 0, 50]});
assert.equal(childList.scrollTop, 40, 'Malformed forwarded input must be rejected.');
inner.dispose(); outer.dispose();

// Existing graphic engines must receive retargeted input exactly once.
let graphicCalls = 0;
const graphic = {tagName: 'CANVAS'};
const graphicEnvironment = {...env, WheelEvent: class {
    constructor(type, options) { this.type = type; Object.assign(this, options); }
    preventDefault() { this.defaultPrevented = true; }
}};
graphicEnvironment.document = {elementFromPoint: () => graphic};
const graphicRouter = createRouter(graphicEnvironment);
graphic.dispatchEvent = copy => {
    assert.equal(graphicRouter.handle(copy), false, 'A corrected event must not recurse through the router.');
    graphicCalls++;
    copy.preventDefault();
};
graphicRouter.rememberPointer({clientX: 20, clientY: 25});
assert.equal(graphicRouter.handle(wheel({deltaMode: 0})), true);
assert.equal(graphicCalls, 1);
assert.equal(graphicRouter.handle(wheel({target: graphicEnvironment, clientX: undefined, clientY: undefined})), false);
graphicRouter.dispose();
console.log('Shared visualization interaction contracts passed.');
