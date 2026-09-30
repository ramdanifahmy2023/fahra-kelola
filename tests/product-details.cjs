const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const root = path.resolve(__dirname, '..');

const actions = JSON.parse(execFileSync('php', ['tests/frontend-comfort-render.php', '--actions'], {cwd: root, encoding: 'utf8'}));
const calls = [];
for (const action of actions.actions) vm.runInNewContext(action, {
  triggerEditCookie: (...args) => calls.push(args),
  confirmDelete: (...args) => calls.push(args)
});
assert.equal(calls.length, 4);
assert.ok(calls.every(args => args[1] === actions.name), 'Quotes and ampersands preserve the selected shop name');
assert.equal(calls[1][2], '/procshops/delete');

let focused, opened = 0, replaced = 0;
const handlers = new Map();
const element = id => ({id, addEventListener(type, fn) { handlers.set(id + ':' + type, fn); }, focus() { focused = id; }, querySelectorAll() { return []; }});
const page = element('products-page');
const dialog = {...element('product-detail-dialog'), showModal() { opened++; }, close() { handlers.get('product-detail-dialog:close')(); }, getBoundingClientRect() { return {left:10,right:100,top:10,bottom:100}; }};
const close = element('product-detail-close');
const content = {...element('product-detail-content'), replaceChildren(value) { this.value = value; }};
const knownContent = {product: 11};
const template = {content: {cloneNode() { return knownContent; }}};
const doc = new Map([[page.id,page],[dialog.id,dialog],[close.id,close],[content.id,content],['product-detail-11',template]]);
const fallback = [];
const context = {document: {getElementById: id => doc.get(id), createElement() { const node = {setAttribute(name,value) { this[name] = value; }};fallback.push(node);return node; }}};
vm.runInNewContext(fs.readFileSync(path.join(root, 'public/assets/js/product-details.js'), 'utf8'), context);
const button = {...element('open-11'), dataset: {productDetails:'11'}};
const click = value => handlers.get('products-page:click')({target: {closest: () => value}});
click(button);
assert.equal(opened, 1);
assert.equal(content.value, knownContent, 'The detail belongs to the selected row');
assert.equal(focused, close.id, 'Focus enters the dialog');
handlers.get('product-detail-close:click')();
assert.equal(focused, button.id, 'Closing restores the initiating control');
click({...button, dataset:{productDetails:'999'}});
assert.equal(opened, 1, 'A missing row cannot show another product');
handlers.get('product-detail-dialog:click')({target:dialog,clientX:50,clientY:50});
assert.equal(focused, button.id, 'A click inside the dialog does not change focus');

const makeImage = (shop,complete) => ({complete,naturalWidth:0,classList:{contains:()=>shop},addEventListener(type,fn){this.onError=fn;},replaceWith(node){this.fallback=node;replaced++;}});
const shopImage = makeImage(true,true),productImage=makeImage(false,false);
page.querySelectorAll = () => [shopImage,productImage];
vm.runInNewContext(fs.readFileSync(path.join(root, 'public/assets/js/product-details.js'), 'utf8'), context);
productImage.onError();
assert.equal(replaced, 2);
assert.equal(shopImage.fallback.textContent, 'storefront');
assert.equal(productImage.fallback.textContent, 'inventory_2');
assert.equal(productImage.fallback['aria-hidden'], 'true');
assert.ok(shopImage.fallback.className.includes('product-shop-fallback'));
vm.runInNewContext(fs.readFileSync(path.join(root, 'public/assets/js/product-details.js'), 'utf8'), {document:{getElementById:()=>null}});
console.log('PASS: product selection/focus/image fallbacks and quoted shop actions (unit checks; native browser behavior not covered)');
