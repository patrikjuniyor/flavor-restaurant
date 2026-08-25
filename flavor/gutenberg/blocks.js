(function (blocks, element, blockEditor, serverSideRender) {
	var el = element.createElement;
	var Inner = blockEditor.InnerBlocks || blockEditor.RichText;
	var RichText = blockEditor.RichText;
	var SSR = serverSideRender;

	function wrap(slug, title, fields) {
		blocks.registerBlockType('flavor/' + slug, {
			apiVersion: 2,
			title: title,
			category: 'widgets',
			icon: 'food',
			attributes: fields,
			edit: function (props) {
				return el(
					'div',
					{ className: 'flavor-block-editor' },
					el(SSR, { block: 'flavor/' + slug, attributes: props.attributes })
				);
			},
			save: function (props) {
				if (slug === 'hero' || slug === 'about' || slug === 'testimonial') {
					return el(RichText.Content, { tagName: 'p', value: props.attributes._inner || '' });
				}
				return null;
			},
		});
	}

	wrap('hero', 'Flavor هیرو', {
		title: { type: 'string', default: '' },
		cta: { type: 'string', default: '' },
		image: { type: 'string', default: '' },
	});
	wrap('about', 'Flavor درباره', { title: { type: 'string', default: '' } });
	wrap('gallery', 'Flavor گالری', { images: { type: 'string', default: '' } });
	wrap('testimonial', 'Flavor نظر', { name: { type: 'string', default: '' } });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.serverSideRender);
