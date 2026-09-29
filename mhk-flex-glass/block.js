(function (wp) {
	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var useInnerBlocksProps = wp.blockEditor.useInnerBlocksProps;
	var InnerBlocks = wp.blockEditor.InnerBlocks;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var __ = wp.i18n.__;

	/**
	 * CSS variables drive both editor and front-end flex layout.
	 */
	function buildCssVars(attributes) {
		return {
			'--mhk-flex-direction': attributes.direction || 'column',
			'--mhk-align-items': attributes.alignItems || 'center',
			'--mhk-justify-content': attributes.justifyContent || 'center',
			'--mhk-flex-wrap': attributes.wrap ? 'wrap' : 'nowrap',
			'--mhk-gap': (attributes.gap != null ? attributes.gap : 10) + 'px',
			'--mhk-max-width': attributes.maxWidth || '100%',
			'--mhk-padding': (attributes.padding != null ? attributes.padding : 10) + 'px',
		};
	}

	function classNameFromAttributes(attributes) {
		var classes = ['mhk-flex-glass'];
		if (attributes.glass) {
			classes.push('mhk-flex-glass--frosted');
		}
		return classes.join(' ');
	}

	registerBlockType('mhk/flex-glass', {
		apiVersion: 2,
		title: __('Flex Glass', 'mhk-flex-glass'),
		description: __(
			'Flex container with optional frosted glass effect. Reliable on the front end.',
			'mhk-flex-glass'
		),
		icon: 'layout',
		category: 'design',
		supports: {
			anchor: true,
			className: true,
			html: false,
		},
		attributes: {
			direction: {
				type: 'string',
				default: 'column',
			},
			alignItems: {
				type: 'string',
				default: 'center',
			},
			justifyContent: {
				type: 'string',
				default: 'center',
			},
			wrap: {
				type: 'boolean',
				default: false,
			},
			gap: {
				type: 'number',
				default: 10,
			},
			maxWidth: {
				type: 'string',
				default: '318px',
			},
			padding: {
				type: 'number',
				default: 10,
			},
			glass: {
				type: 'boolean',
				default: true,
			},
		},

		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var blockProps = useBlockProps({
				className: classNameFromAttributes(attributes),
				style: buildCssVars(attributes),
			});

			/*
			 * Flex must be on this inner wrapper so the editor's
			 * block list layout actually reflows when settings change.
			 */
			var innerBlocksProps = useInnerBlocksProps(
				{
					className: 'mhk-flex-glass__layout',
				},
				{
					template: [],
					templateLock: false,
					renderAppender: InnerBlocks.ButtonBlockAppender,
				}
			);

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Layout', 'mhk-flex-glass'), initialOpen: true },
						el(SelectControl, {
							label: __('Direction', 'mhk-flex-glass'),
							value: attributes.direction,
							options: [
								{ label: __('Column (stack)', 'mhk-flex-glass'), value: 'column' },
								{ label: __('Row (side by side)', 'mhk-flex-glass'), value: 'row' },
							],
							onChange: function (value) {
								setAttributes({ direction: value });
							},
						}),
						el(SelectControl, {
							label: __('Align items', 'mhk-flex-glass'),
							value: attributes.alignItems,
							options: [
								{ label: __('Center', 'mhk-flex-glass'), value: 'center' },
								{ label: __('Start', 'mhk-flex-glass'), value: 'flex-start' },
								{ label: __('End', 'mhk-flex-glass'), value: 'flex-end' },
								{ label: __('Stretch', 'mhk-flex-glass'), value: 'stretch' },
							],
							onChange: function (value) {
								setAttributes({ alignItems: value });
							},
						}),
						el(SelectControl, {
							label: __('Justify', 'mhk-flex-glass'),
							value: attributes.justifyContent,
							options: [
								{ label: __('Center', 'mhk-flex-glass'), value: 'center' },
								{ label: __('Start', 'mhk-flex-glass'), value: 'flex-start' },
								{ label: __('End', 'mhk-flex-glass'), value: 'flex-end' },
								{ label: __('Space between', 'mhk-flex-glass'), value: 'space-between' },
							],
							onChange: function (value) {
								setAttributes({ justifyContent: value });
							},
						}),
						el(ToggleControl, {
							label: __('Allow wrap', 'mhk-flex-glass'),
							checked: !!attributes.wrap,
							onChange: function (value) {
								setAttributes({ wrap: value });
							},
						}),
						el(RangeControl, {
							label: __('Gap (px)', 'mhk-flex-glass'),
							value: attributes.gap,
							onChange: function (value) {
								setAttributes({ gap: value });
							},
							min: 0,
							max: 64,
						}),
						el(RangeControl, {
							label: __('Padding (px)', 'mhk-flex-glass'),
							value: attributes.padding,
							onChange: function (value) {
								setAttributes({ padding: value });
							},
							min: 0,
							max: 80,
						}),
						el(TextControl, {
							label: __('Max width', 'mhk-flex-glass'),
							help: __('e.g. 318px, 100%, 40rem', 'mhk-flex-glass'),
							value: attributes.maxWidth,
							onChange: function (value) {
								setAttributes({ maxWidth: value });
							},
						})
					),
					el(
						PanelBody,
						{ title: __('Glass', 'mhk-flex-glass'), initialOpen: true },
						el(ToggleControl, {
							label: __('Frosted glass effect', 'mhk-flex-glass'),
							checked: !!attributes.glass,
							onChange: function (value) {
								setAttributes({ glass: value });
							},
						}),
						el(
							'p',
							{
								style: {
									fontSize: '12px',
									color: '#646970',
									marginTop: '8px',
								},
							},
							__(
								'Tip: A Custom HTML block with several tags is kept as one flex item automatically.',
								'mhk-flex-glass'
							)
						)
					)
				),
				el('div', blockProps, el('div', innerBlocksProps))
			);
		},

		save: function (props) {
			var attributes = props.attributes;
			var blockProps = useBlockProps.save({
				className: classNameFromAttributes(attributes),
				style: buildCssVars(attributes),
			});

			var innerBlocksProps = useInnerBlocksProps.save({
				className: 'mhk-flex-glass__layout',
			});

			return el('div', blockProps, el('div', innerBlocksProps));
		},
	});
})(window.wp);
