const { wp, jQuery, ajaxurl, smAdmin } = window;

jQuery(document).ready(() => {
	wp.media.view.AttachmentCompat.prototype.on('ready', () => {
		const visibleRow = document.querySelector('.compat-field-sm_visibility');
		if (!visibleRow) {
			return;
		}

		const field = visibleRow.querySelector('.field');
		const input = field.querySelector('input');

		let { value } = input;

		value = parseInt(value, 10) === 1;

		const attachmentId = input.name.replace(/^attachments\[([0-9]+)\].*$/, '$1');

		input.type = 'checkbox';

		input.addEventListener('change', (event) => {
			jQuery.ajax({
				url: ajaxurl,
				method: 'post',
				dataType: 'json',
				data: {
					action: 'sm_set_visibility',
					nonce: smAdmin.nonce,
					public: event.target.checked ? 1 : 0,
					postId: parseInt(attachmentId, 10),
				},
			});

			event.preventDefault();
			event.stopPropagation();
		});
	});
});
