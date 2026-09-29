<?php
/**
 * Plugin Name: MHK Flex Glass
 * Description: Gutenberg flex container with optional glass (frosted) effect. Works when post content is rendered through Oxygen.
 * Version: 1.0.2
 * Author: Monument Hill Kiwanis
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
	exit;
}

define('MHK_FLEX_GLASS_VERSION', '1.0.2');
define('MHK_FLEX_GLASS_URL', plugin_dir_url(__FILE__));
define('MHK_FLEX_GLASS_PATH', plugin_dir_path(__FILE__));

/**
 * Register block assets and the block itself.
 */
function mhk_flex_glass_register() {

	wp_register_style(
		'mhk-flex-glass-style',
		MHK_FLEX_GLASS_URL . 'style.css',
		array(),
		MHK_FLEX_GLASS_VERSION
	);

	wp_register_style(
		'mhk-flex-glass-editor',
		MHK_FLEX_GLASS_URL . 'editor.css',
		array('mhk-flex-glass-style'),
		MHK_FLEX_GLASS_VERSION
	);

	wp_register_script(
		'mhk-flex-glass-editor-script',
		MHK_FLEX_GLASS_URL . 'block.js',
		array(
			'wp-blocks',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-i18n',
		),
		MHK_FLEX_GLASS_VERSION,
		true
	);

	register_block_type('mhk/flex-glass', array(
		'editor_script' => 'mhk-flex-glass-editor-script',
		'editor_style'  => 'mhk-flex-glass-editor',
		'style'         => 'mhk-flex-glass-style',
	));
}
add_action('init', 'mhk_flex_glass_register');

/**
 * Ensure front-end CSS loads even when Oxygen embeds post content
 * without going through the normal block asset pipeline.
 */
function mhk_flex_glass_enqueue_front_style($content) {
	if (!is_string($content) || $content === '') {
		return $content;
	}
	if (strpos($content, 'wp-block-mhk-flex-glass') === false && strpos($content, 'mhk-flex-glass') === false) {
		return $content;
	}
	wp_enqueue_style('mhk-flex-glass-style');
	return $content;
}
add_filter('the_content', 'mhk_flex_glass_enqueue_front_style', 5);

/**
 * Mark core/html blocks that are direct children of Flex Glass.
 */
function mhk_flex_glass_mark_html_child($parsed_block, $source_block, $parent_block) {
	if (
		($parsed_block['blockName'] ?? '') === 'core/html'
		&& is_array($parent_block)
		&& ($parent_block['blockName'] ?? '') === 'mhk/flex-glass'
	) {
		if (!isset($parsed_block['attrs']) || !is_array($parsed_block['attrs'])) {
			$parsed_block['attrs'] = array();
		}
		$parsed_block['attrs']['mhkFlexGlassChild'] = true;
	}
	return $parsed_block;
}
add_filter('render_block_data', 'mhk_flex_glass_mark_html_child', 10, 3);

/**
 * Wrap those HTML blocks so multiple root tags count as ONE flex item.
 */
function mhk_flex_glass_wrap_html_child($block_content, $block) {
	if (empty($block['attrs']['mhkFlexGlassChild'])) {
		return $block_content;
	}
	if ($block_content === '' || $block_content === null) {
		return $block_content;
	}
	// Avoid double-wrapping if already wrapped.
	if (strpos(ltrim($block_content), 'mhk-flex-unit') !== false) {
		return $block_content;
	}
	return '<div class="mhk-flex-unit">' . $block_content . '</div>';
}
add_filter('render_block_core/html', 'mhk_flex_glass_wrap_html_child', 10, 2);
