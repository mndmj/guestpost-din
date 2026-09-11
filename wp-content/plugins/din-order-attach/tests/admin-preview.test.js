const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

class Element {
	constructor(tagName) {
		this.tagName = tagName;
		this.children = [];
		this.className = '';
		this.dataset = {};
		this.hidden = true;
		this.listeners = {};
		this.style = {};
		this.attributes = {};
	}

	addEventListener(type, listener) {
		this.listeners[type] = listener;
	}

	appendChild(child) {
		this.children.push(child);
	}

	replaceChildren() {
		this.children = [];
	}

	setAttribute(name, value) {
		this.attributes[name] = value;
	}
}

class DataTransfer {
	constructor() {
		const files = [];
		this.items = { add: (file) => files.push(file) };
		this.files = files;
	}
}

function createEnvironment() {
	const assetPath = path.join(__dirname, '..', 'assets', 'admin-preview.js');
	assert.equal(fs.existsSync(assetPath), true, 'admin preview asset must exist');

	const input = new Element('input');
	const preview = new Element('div');
	const alert = new Element('div');
	input.accept = '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.png,.zip';
	input.dataset.maxSize = '10485760';
	preview.dataset.removeLabel = 'Remove';
	alert.dataset.invalidTypeTemplate = '%s was removed: file type is not allowed.';
	alert.dataset.tooLargeTemplate = '%s was removed: file exceeds 10 MB.';
	const createdUrls = [];
	const revokedUrls = [];
	const document = {
		getElementById(id) {
			return id === 'din-order-attach-files'
				? input
				: id === 'din-order-attach-preview'
					? preview
					: id === 'din-order-attach-alert'
						? alert
						: null;
		},
		createElement(tagName) {
			return new Element(tagName);
		},
	};
	const URL = {
		createObjectURL(file) {
			const url = `blob:${file.name}`;
			createdUrls.push(url);
			return url;
		},
		revokeObjectURL(url) {
			revokedUrls.push(url);
		},
	};

	vm.runInNewContext(fs.readFileSync(assetPath, 'utf8'), { DataTransfer, document, URL });

	return { alert, createdUrls, input, preview, revokedUrls };
}

test('keeps preview cards synchronized with the selected upload batch', () => {
	const { createdUrls, input, preview, revokedUrls } = createEnvironment();

	input.files = [
		{ name: 'proof.jpg', size: 2048, type: 'image/jpeg' },
		{ name: 'document.pdf', size: 4096, type: 'application/pdf' },
		{ name: 'chart.png', size: 3072, type: 'image/png' },
		{ name: 'archive.zip', size: 5120, type: 'application/zip' },
	];
	input.listeners.change();

	assert.equal(preview.hidden, false);
	assert.equal(preview.children.length, 4);
	assert.deepEqual(createdUrls, ['blob:proof.jpg', 'blob:chart.png']);
	assert.equal(preview.children[0].children[0].alt, 'proof.jpg');
	assert.match(preview.children[0].children[1].textContent, /proof\.jpg.*2 KB/);
	assert.equal(preview.children[1].children[0].tagName, 'span');
	assert.equal(preview.children[1].children[0].textContent, 'PDF');
	assert.match(preview.children[1].children[1].textContent, /document\.pdf.*4 KB/);
	assert.equal(preview.children[1].children[2].type, 'button');
	assert.equal(preview.children[1].children[2].textContent, 'Remove');
	assert.equal(preview.children[1].children[2].attributes['aria-label'], 'Remove document.pdf');
	assert.equal(input.files.length, 4, 'rendering must not modify the upload batch');

	preview.children[1].children[2].listeners.click();

	assert.deepEqual(input.files.map((file) => file.name), ['proof.jpg', 'chart.png', 'archive.zip']);
	assert.equal(preview.children.length, 3);
	assert.doesNotMatch(preview.children[1].children[1].textContent, /document\.pdf/);
	assert.deepEqual(revokedUrls, ['blob:proof.jpg', 'blob:chart.png']);

	while (preview.children.length) {
		preview.children[0].children[2].listeners.click();
	}

	assert.equal(input.files.length, 0);
	assert.equal(preview.hidden, true);
});

test('removes invalid selected files immediately and reports each reason', () => {
	const { alert, input, preview } = createEnvironment();

	input.files = [
		{ name: 'proof.pdf', size: 4096, type: 'application/pdf' },
		{ name: 'large.pdf', size: 11534336, type: 'application/pdf' },
		{ name: 'video.mp4', size: 1024, type: 'video/mp4' },
	];
	input.listeners.change();

	assert.deepEqual(input.files.map((file) => file.name), ['proof.pdf']);
	assert.equal(preview.children.length, 1);
	assert.equal(alert.hidden, false);
	assert.deepEqual(
		alert.children[0].children.map((item) => item.textContent),
		[
			'large.pdf was removed: file exceeds 10 MB.',
			'video.mp4 was removed: file type is not allowed.',
		]
	);

	input.files = [{ name: 'replacement.zip', size: 2048, type: 'application/zip' }];
	input.listeners.change();

	assert.equal(alert.hidden, true);
	assert.equal(alert.children.length, 0);
});
