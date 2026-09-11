(function () {
	'use strict';

	const input = document.getElementById('din-order-attach-files');
	const preview = document.getElementById('din-order-attach-preview');
	const alert = document.getElementById('din-order-attach-alert');
	let objectUrls = [];

	if (!input || !preview || !alert) {
		return;
	}

	function clearPreview() {
		objectUrls.forEach((url) => URL.revokeObjectURL(url));
		objectUrls = [];
		preview.replaceChildren();
		preview.hidden = true;
	}

	function clearAlert() {
		alert.replaceChildren();
		alert.hidden = true;
	}

	function filterSelectedFiles() {
		const allowedExtensions = input.accept.toLowerCase().split(',').map((extension) => extension.trim());
		const maxSize = Number(input.dataset.maxSize);
		const transfer = new DataTransfer();
		const messages = [];

		clearAlert();
		Array.from(input.files || []).forEach((file) => {
			const extension = file.name.includes('.') ? `.${file.name.split('.').pop().toLowerCase()}` : '';
			let template = '';

			if (!allowedExtensions.includes(extension)) {
				template = alert.dataset.invalidTypeTemplate;
			} else if (file.size > maxSize) {
				template = alert.dataset.tooLargeTemplate;
			}

			if (template) {
				messages.push(template.replace('%s', file.name));
			} else {
				transfer.items.add(file);
			}
		});

		if (!messages.length) {
			return;
		}

		input.files = transfer.files;
		const list = document.createElement('ul');
		messages.forEach((message) => {
			const item = document.createElement('li');

			item.textContent = message;
			list.appendChild(item);
		});
		alert.appendChild(list);
		alert.hidden = false;
	}

	function renderPreview() {
		clearPreview();

		Array.from(input.files || []).forEach((file, fileIndex) => {
			const isJpeg = file.type === 'image/jpeg' && /\.jpg$/i.test(file.name);
			const isPng = file.type === 'image/png' && /\.png$/i.test(file.name);
			const item = document.createElement('figure');
			const caption = document.createElement('figcaption');
			const remove = document.createElement('button');

			item.className = 'din-order-attach-preview__item';

			if (isJpeg || isPng) {
				const image = document.createElement('img');
				const objectUrl = URL.createObjectURL(file);

				objectUrls.push(objectUrl);
				image.src = objectUrl;
				image.alt = file.name;
				item.appendChild(image);
			} else {
				const fileType = document.createElement('span');
				const extension = file.name.includes('.') ? file.name.split('.').pop() : 'FILE';

				fileType.className = 'din-order-attach-preview__file-type';
				fileType.textContent = extension.toUpperCase();
				item.appendChild(fileType);
			}

			caption.textContent = `${file.name} · ${Math.max(1, Math.ceil(file.size / 1024))} KB`;
			remove.type = 'button';
			remove.className = 'button button-small din-order-attach-preview__remove';
			remove.textContent = preview.dataset.removeLabel || 'Remove';
			remove.setAttribute('aria-label', `${remove.textContent} ${file.name}`);
			remove.addEventListener('click', function () {
				const transfer = new DataTransfer();

				Array.from(input.files || []).forEach((candidate, candidateIndex) => {
					if (candidateIndex !== fileIndex) {
						transfer.items.add(candidate);
					}
				});

				input.files = transfer.files;
				renderPreview();
			});

			item.appendChild(caption);
			item.appendChild(remove);
			preview.appendChild(item);
		});

		preview.hidden = preview.children.length === 0;
	}

	input.addEventListener('change', function () {
		filterSelectedFiles();
		renderPreview();
	});
}());
