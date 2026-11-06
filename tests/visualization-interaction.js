'use strict';
const assert = require('node:assert/strict');
const {createRouter} = require(process.argv.includes('--minified') ? '../src/visualization/interaction.min.js' : '../src/visualization/interaction.js');
const listeners = {};
const doc = {addEventListener: (name, fn) => { listeners[name] = fn; }, removeEventListener() {}};
const env = {document: doc, addEventListener() {}, removeEventListener() {}, getComputedStyle: () => ({overflowY: 'auto', overflowX: 'auto'})};
const router = createRouter(env);

// A native Symcon preview host expects its own Message envelope and must not
// receive our private frame protocol unless the loader explicitly opts in.
const foreignMessages = [], foreignListeners = {};
const foreignHost = {postMessage(data) { foreignMessages.push(data); }};
const foreignEnvironment = {document: {elementFromPoint: () => list}, parent: foreignHost,
    innerWidth: 100, innerHeight: 100, getComputedStyle: env.getComputedStyle,
    addEventListener: (name, fn) => { foreignListeners[name] = fn; }, removeEventListener() {}};
const foreignRouter = createRouter(foreignEnvironment);
foreignRouter.rememberPointer({clientX: 20, clientY: 30});
assert.deepEqual(foreignMessages, [], 'Unmarked children must never send readiness or pointer messages to a foreign host.');
const list = {tagName: 'ASIDE', clientWidth: 100, clientHeight: 100, scrollHeight: 600, scrollWidth: 300, scrollTop: 0, scrollLeft: 0};
doc.elementFromPoint = () => list;
const wheel = extra => Object.assign({type: 'wheel', target: {tagName: 'BODY'}, clientX: 0, clientY: 0, deltaX: 0, deltaY: 2, deltaMode: 1,
    preventDefault() { this.prevented = true; }, stopImmediatePropagation() { this.stopped = true; }}, extra);
foreignListeners.message({source: foreignHost, data: ['burki24.wheel.v1', 'wheel', 0.2, 0.3, 0, 40]});
assert.equal(list.scrollTop, 0, 'Unmarked children must reject frame input even from their actual parent.');
assert.equal(foreignRouter.handle(wheel()), true, 'Local wheel routing must still work in a foreign host.');
assert.equal(list.scrollTop, 32);
list.scrollTop = 0;
foreignRouter.dispose();
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
const child = {name: 'burki24.wheel.v1', innerWidth: 100, innerHeight: 50, getComputedStyle: env.getComputedStyle, parent,
    addEventListener: (n, f) => { childListeners[n] = f; }, removeEventListener() {}};
const childList = {...list, scrollTop: 0, scrollLeft: 0};
const frame = {name: 'burki24.wheel.v1', tagName: 'IFRAME', contentWindow: child, getBoundingClientRect: () => ({left: 10, top: 20, width: 200, height: 100})};
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
outer.clearPointer();
frame.name = '';
parentListeners.message({source: child, data: ['burki24.wheel.v1', 'ready']});
parentListeners.message({source: child, data: ['burki24.wheel.v1', 'pointer', 0.5, 0.5]});
assert.equal(outer.point(wheel()).clientX, 0, 'Unmarked frames must not register or update their parent pointer.');
outer.handle(wheel({target: frame, clientX: 50, clientY: 60}));
assert.equal(childList.scrollTop, 40, 'Removing the explicit frame marker must stop forwarding.');
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

// Browser-like setters are essential: a real element clamps wheel input at its
// scroll limits instead of accepting negative/out-of-range positions.
function scrollBox(tag, parent, options = {}) {
    const value = {tagName: tag, parentElement: parent, clientWidth: 200, clientHeight: 200,
        scrollWidth: 600, scrollHeight: 600, overflowX: 'auto', overflowY: 'auto', ...options};
    let top = options.top ?? 0, left = options.left ?? 0;
    Object.defineProperties(value, {
        scrollTop: {get: () => top, set: next => { top = Math.max(0, Math.min(value.scrollHeight - value.clientHeight, next)); }},
        scrollLeft: {get: () => left, set: next => { left = Math.max(options.minLeft ?? 0, Math.min(options.maxLeft ?? value.scrollWidth - value.clientWidth, next)); }}
    });
    return value;
}
const root = scrollBox('HTML', null, {top: 424, clientHeight: 400, scrollHeight: 1200, overflowX: 'visible', overflowY: 'visible'});
const boundaryBody = {tagName: 'BODY', parentElement: root, overflowX: 'visible', overflowY: 'visible'};
const nested = scrollBox('DIV', boundaryBody, {scrollHeight: 401});
const hit = {tagName: 'SECTION', parentElement: nested};
const boundaryDocument = {scrollingElement: root, body: boundaryBody, elementFromPoint: () => hit};
const boundaryEnvironment = {document: boundaryDocument, addEventListener() {}, removeEventListener() {},
    getComputedStyle: node => ({overflowX: node.overflowX ?? 'visible', overflowY: node.overflowY ?? 'visible'})};
const boundary = createRouter(boundaryEnvironment);
const boundaryWheel = extra => wheel({target: hit, clientX: 20, clientY: 20, deltaMode: 0, ...extra});
event = boundaryWheel({deltaY: -300});
assert.equal(boundary.handle(event), true);
assert.equal(nested.scrollTop, 0, 'An inner list at its upper boundary must remain clamped.');
assert.equal(root.scrollTop, 124, 'A wheel at the inner upper boundary must reach the scrolled document.');
assert(event.prevented && event.stopped);

root.scrollTop = 100; nested.scrollTop = 201;
assert.equal(boundary.handle(boundaryWheel({deltaY: 80})), true);
assert.equal(root.scrollTop, 180, 'The lower boundary must also allow the document to move.');
assert.equal(nested.scrollTop, 201);

root.scrollTop = 100; nested.scrollTop = 190;
assert.equal(boundary.handle(boundaryWheel({deltaY: 80})), true);
assert.equal(nested.scrollTop, 201);
assert.equal(root.scrollTop, 100, 'The remainder of a partly consumed wheel must not produce a second scroll.');

root.scrollTop = 100; nested.scrollTop = 40;
assert.equal(boundary.handle(boundaryWheel({deltaY: 50})), true);
assert.equal(nested.scrollTop, 90);
assert.equal(root.scrollTop, 100, 'A movable inner list owns the event; no second document movement.');

root.scrollLeft = 80; nested.scrollLeft = 400;
assert.equal(boundary.handle(boundaryWheel({deltaY: 20, shiftKey: true})), true);
assert.equal(root.scrollLeft, 100, 'Shift-wheel must pass a horizontal boundary to a movable ancestor.');
assert.equal(nested.scrollLeft, 400);

root.scrollTop = 100; nested.scrollTop = 0; nested.scrollLeft = 30;
assert.equal(boundary.handle(boundaryWheel({deltaX: 20, deltaY: -40})), true);
assert.equal(nested.scrollLeft, 50);
assert.equal(root.scrollTop, 100, 'A diagonal event remains owned by its first actually moving target.');

root.scrollTop = 0; nested.scrollTop = 0; nested.scrollLeft = 0;
event = boundaryWheel({deltaY: -30});
assert.equal(boundary.handle(event), false);
assert(!event.prevented && !event.stopped, 'An entirely exhausted scroll chain must not swallow the wheel.');
root.overflowY = 'hidden'; root.scrollTop = 100;
assert.equal(boundary.handle(boundaryWheel({deltaY: -30})), false);
assert.equal(root.scrollTop, 100, 'A hidden viewport is not a new manual scroll target.');
root.overflowY = 'visible'; boundaryBody.overflowY = 'hidden';
assert.equal(boundary.handle(boundaryWheel({deltaY: -30})), false);
assert.equal(root.scrollTop, 100, 'Body overflow propagated to the viewport must remain respected.');
boundaryBody.overflowY = 'visible';

const modal = scrollBox('DIALOG', boundaryBody, {open: true});
nested.parentElement = modal; modal.scrollTop = 0; root.scrollTop = 100;
event = boundaryWheel({deltaY: -30});
assert.equal(boundary.handle(event), true);
assert(event.prevented && event.stopped, 'A modal boundary must prevent default background scrolling.');
assert.equal(root.scrollTop, 100, 'An open modal boundary must not scroll the background document.');
modal.scrollTop = 60;
assert.equal(boundary.handle(boundaryWheel({deltaY: -30})), true);
assert.equal(modal.scrollTop, 30, 'An inner list may hand off to its own open dialog.');
nested.parentElement = boundaryBody;

const rtl = scrollBox('DIV', boundaryBody, {minLeft: -400, maxLeft: 0});
const rtlEvent = boundaryWheel({target: rtl, deltaY: 0, deltaX: -50});
assert.equal(boundary.handle(rtlEvent), true);
assert.equal(rtl.scrollLeft, -50, 'Observed movement also works for negative RTL scroll positions.');
assert.equal(root.scrollLeft, 100);
boundary.dispose();
console.log('Shared visualization interaction contracts passed.');
