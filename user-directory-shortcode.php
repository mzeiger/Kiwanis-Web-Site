<?php
/**
 * Plugin Name: MHK User Directory Shortcode
 * Description: Responsive [user_directory_2] member directory cards.
 * Version: 1.6.12
 *
 * Install: copy to wp-content/plugins/mhk-user-directory/ and activate,
 * or paste into Code Snippets WITHOUT the leading <?php tag (replace existing snippet).
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mhk_member_images_dir' ) ) {
	function mhk_member_images_dir() {
		return trailingslashit( ABSPATH ) . 'member_images';
	}
}

if ( ! function_exists( 'mhk_member_images_url' ) ) {
	function mhk_member_images_url() {
		return trailingslashit( home_url( '/member_images' ) );
	}
}

if ( ! function_exists( 'mhk_member_image_filename' ) ) {
	function mhk_member_image_filename( $user_id ) {
		$user_id = (int) $user_id;
		$fn      = basename( (string) get_user_meta( $user_id, 'member_image', true ) );
		$dir     = mhk_member_images_dir();
		if ( $fn && 'unknown.webp' !== $fn && preg_match( '/^[A-Za-z0-9._-]+$/', $fn ) && is_file( $dir . '/' . $fn ) ) {
			return $fn;
		}
		return 'unknown.webp';
	}
}

if ( ! function_exists( 'mhk_member_image_url' ) ) {
	function mhk_member_image_url( $user_id ) {
		$fn   = mhk_member_image_filename( $user_id );
		$path = mhk_member_images_dir() . '/' . $fn;
		$url  = mhk_member_images_url() . rawurlencode( $fn );
		if ( is_file( $path ) ) {
			$url = add_query_arg( 'v', (string) filemtime( $path ), $url );
		}
		return $url;
	}
}

/**
 * [user_directory_2] — member directory from WordPress users + Kiwanis meta fields.
 */
function user_directory_shortcode_2() {
	static $months = array(
		1  => 'Jan',
		2  => 'Feb',
		3  => 'Mar',
		4  => 'Apr',
		5  => 'May',
		6  => 'Jun',
		7  => 'Jul',
		8  => 'Aug',
		9  => 'Sep',
		10 => 'Oct',
		11 => 'Nov',
		12 => 'Dec',
	);

	$fmt_yes_no = static function ( $v ) {
		$v = strtolower( trim( (string) $v ) );
		if ( 'yes' === $v ) {
			return 'Yes';
		}
		if ( 'no' === $v ) {
			return 'No';
		}
		return '' === $v ? '—' : esc_html( (string) $v );
	};

	/**
	 * Latest `enabled` usermeta (handles duplicate rows — WP get_user_meta returns oldest).
	 *
	 * @param int $uid User ID.
	 * @return mixed Raw meta value or empty string if unset.
	 */
	$read_enabled_meta = static function ( $uid ) {
		global $wpdb;

		$uid = (int) $uid;
		if ( $uid <= 0 ) {
			return '';
		}

		$latest = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key IN ('enabled', 'ebnabled') ORDER BY umeta_id DESC LIMIT 1",
				$uid
			)
		);

		if ( null !== $latest ) {
			return $latest;
		}

		$enabled = get_user_meta( $uid, 'enabled', true );
		if ( '' !== (string) $enabled ) {
			return $enabled;
		}

		return get_user_meta( $uid, 'ebnabled', true );
	};

	/**
	 * Enabled when meta is explicitly truthy; disabled for 0/no/false/off; missing meta defaults to enabled (import default).
	 *
	 * @param mixed $raw enabled meta value.
	 * @return bool
	 */
	$is_member_enabled = static function ( $raw ) {
		if ( is_array( $raw ) ) {
			$raw = end( $raw );
		}

		$v = strtolower( trim( (string) $raw ) );
		if ( '' === $v ) {
			return true;
		}

		if ( in_array( $v, array( '1', 'yes', 'y', 'true', 'on' ), true ) ) {
			return true;
		}

		if ( in_array( $v, array( '0', 'no', 'n', 'false', 'off' ), true ) ) {
			return false;
		}

		return false;
	};

	$fmt_enabled = static function ( $v ) use ( $is_member_enabled ) {
		return $is_member_enabled( $v ) ? 'Yes' : 'No';
	};

	$users = get_users(
		array(
			'blog_id' => get_current_blog_id(),
			'fields'  => 'all',
		)
	);

	$rows = array();

	foreach ( $users as $u ) {
		$uid = (int) $u->ID;

		$first_name = get_user_meta( $uid, 'first_name', true );
		$last_name  = get_user_meta( $uid, 'last_name', true );

		$home_phone      = get_user_meta( $uid, 'home_phone', true );
		$mobile_phone    = get_user_meta( $uid, 'mobile_phone', true );
		$street_address  = get_user_meta( $uid, 'street_address', true );
		$city            = get_user_meta( $uid, 'city', true );
		$state           = get_user_meta( $uid, 'state', true );
		$zip             = get_user_meta( $uid, 'zip', true );
		$spouse          = get_user_meta( $uid, 'spouse', true );
		$birth_month     = (int) get_user_meta( $uid, 'birth_month', true );
		$birth_day       = (int) get_user_meta( $uid, 'birth_day', true );
		$sponsor         = get_user_meta( $uid, 'sponsor', true );
		$year_joined     = get_user_meta( $uid, 'year_joined_kiwanis', true );
		$honorary_member = get_user_meta( $uid, 'honorary_member', true );
		$life_member     = get_user_meta( $uid, 'life_member', true );
		$enabled_raw     = $read_enabled_meta( $uid );

		$birth_label = '';
		if ( $birth_month >= 1 && $birth_month <= 12 && $birth_day >= 1 ) {
			$mname       = isset( $months[ $birth_month ] ) ? $months[ $birth_month ] : '';
			$birth_label = trim( $mname . ' ' . $birth_day );
		}

		$rows[] = array(
			'last_name'         => $last_name,
			'first_name'        => $first_name,
			'user_login'        => $u->user_login,
			'user_email'        => $u->user_email,
			'street_address'    => $street_address,
			'city'              => $city,
			'state'             => $state,
			'zip'               => $zip,
			'home_phone'        => $home_phone,
			'mobile_phone'      => $mobile_phone,
			'spouse'            => $spouse,
			'birth'             => $birth_label,
			'sponsor'           => $sponsor,
			'year_joined'       => $year_joined,
			'honorary_member_l' => $honorary_member,
			'life_member_l'     => $life_member,
			'enabled_l'         => $enabled_raw,
			'photo_url'         => mhk_member_image_url( $uid ),
			'_sort_last'        => strtolower( (string) $last_name ),
			'_sort_first'       => strtolower( (string) $first_name ),
		);
	}

	usort(
		$rows,
		static function ( $a, $b ) {
			$ln = strcmp( $a['_sort_last'], $b['_sort_last'] );
			if ( 0 !== $ln ) {
				return $ln;
			}
			return strcmp( $a['_sort_first'], $b['_sort_first'] );
		}
	);

	foreach ( $rows as &$row ) {
		unset( $row['_sort_last'], $row['_sort_first'] );
	}
	unset( $row );

	if ( ! is_array( $rows ) ) {
		$rows = array();
	}

	$uid = 'ud-' . wp_unique_id();

	$show_admin_cols = is_user_logged_in()
		&& in_array( 'administrator', (array) wp_get_current_user()->roles, true );

	ob_start();
	?>
	<div class="user-directory-wrap ud-cq-host" id="<?php echo esc_attr( $uid ); ?>" data-ud-version="1.6.12">
		<style>
			/* Let Oxygen/Breakdance parents shrink (overflow on one container is not enough) */
			.oxy-shortcode:has(> .user-directory-wrap),
			.user-directory-shortcode:has(.user-directory-wrap),
			.bde-themeless-template-content-area:has(.user-directory-wrap),
			.oxy-container:has(.user-directory-wrap),
			.bde-shortcode:has(.user-directory-wrap),
			.bde-div:has(.user-directory-wrap),
			.bde-section:has(.user-directory-wrap),
			.bde-column:has(.user-directory-wrap),
			.bde-code-block:has(.user-directory-wrap),
			.container-8:has(.user-directory-wrap),
			.container-9:has(.user-directory-wrap),
			.container-21:has(.user-directory-wrap),
			.column-1:has(.user-directory-wrap),
			.column-3:has(.user-directory-wrap),
			.columns-1:has(.user-directory-wrap) {
				overflow: visible !important;
				overflow-x: visible !important;
				overflow-y: visible !important;
				height: auto !important;
				max-height: none !important;
				max-width: 100% !important;
				min-width: 0 !important;
				width: 100% !important;
			}
			#<?php echo esc_html( $uid ); ?>.ud-cq-host {
				container-type: inline-size;
				container-name: ud;
			}
			#<?php echo esc_html( $uid ); ?> {
				--ud-border: #e2e8f0;
				--ud-head: #f8fafc;
				--ud-text: #0f172a;
				--ud-muted: #64748b;
				--ud-count-color: green;
				--ud-row-odd: #ffffff;
				--ud-row-even: #e8eef5;
				--ud-row-hover: #dbe4f0;
				--ud-row-hover-weight: 600;
				--ud-row-disabled: #f08080;
				--ud-row-disabled-hover: #e86868;
				--ud-card-border: #64748b;
				--ud-card-border-width: 3px;
				--ud-card-gap: 1rem;
				font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
				color: var(--ud-text);
				margin: 1rem 0;
				width: 100%;
				max-width: 100%;
				min-width: 0;
				box-sizing: border-box;
				overflow: visible;
			}
			#<?php echo esc_html( $uid ); ?> *,
			#<?php echo esc_html( $uid ); ?> *::before,
			#<?php echo esc_html( $uid ); ?> *::after {
				box-sizing: border-box;
			}
			#<?php echo esc_html( $uid ); ?> .ud-toolbar {
				margin-bottom: 0.75rem;
			}
			#<?php echo esc_html( $uid ); ?> .ud-search-row {
				display: flex;
				flex-wrap: wrap;
				flex-direction: row;
				align-items: center;
				justify-content: center;
				gap: 0.75rem 1.25rem;
				width: 100%;
			}
			#<?php echo esc_html( $uid ); ?> .ud-search-group {
				display: flex;
				flex-wrap: nowrap;
				align-items: center;
				justify-content: center;
				gap: 0.5rem 0.75rem;
				min-width: 0;
			}
			#<?php echo esc_html( $uid ); ?> .ud-search-row label {
				font-weight: 600;
				font-size: 0.95rem;
				flex: 0 1 auto;
				white-space: nowrap;
			}
			#<?php echo esc_html( $uid ); ?> .ud-label-short {
				display: none;
			}
			#<?php echo esc_html( $uid ); ?> .ud-search {
				min-width: 0;
				width: 22rem;
				flex: 0 0 22rem;
				max-width: 100%;
				padding: 0.55rem 0.75rem;
				border: 1px solid var(--ud-border);
				border-radius: 8px;
				font-size: 0.95rem;
				outline: none;
				transition: border-color 0.15s ease, box-shadow 0.15s ease;
			}
			#<?php echo esc_html( $uid ); ?> .ud-search:focus {
				border-color: #94a3b8;
				box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.35);
			}
			#<?php echo esc_html( $uid ); ?> .ud-meta {
				font-size: 0.85rem;
				color: var(--ud-count-color);
				flex: 0 0 auto;
				white-space: nowrap;
				margin: 0;
			}
			#<?php echo esc_html( $uid ); ?> .ud-meta .ud-no-match {
				color: red;
			}
			@container ud (max-width: 1100px) {
				#<?php echo esc_html( $uid ); ?> {
					margin: 0.4rem 0;
				}
				#<?php echo esc_html( $uid ); ?> .ud-toolbar {
					margin-bottom: 0.3rem;
				}
				#<?php echo esc_html( $uid ); ?> .ud-search-row {
					flex-direction: column;
					align-items: stretch;
					gap: 0.3rem;
				}
				#<?php echo esc_html( $uid ); ?> .ud-search-group {
					width: 100%;
					flex-direction: row;
					flex-wrap: nowrap;
					align-items: center;
					gap: 0.4rem;
				}
				#<?php echo esc_html( $uid ); ?> .ud-search-row label {
					font-size: 0.78rem;
					white-space: nowrap;
					flex: 0 0 auto;
				}
				#<?php echo esc_html( $uid ); ?> .ud-label-full {
					display: none;
				}
				#<?php echo esc_html( $uid ); ?> .ud-label-short {
					display: inline;
				}
				#<?php echo esc_html( $uid ); ?> .ud-search {
					flex: 1 1 auto;
					width: auto;
					max-width: 100%;
					padding: 0.32rem 0.5rem;
					font-size: 0.85rem;
					border-radius: 6px;
				}
				#<?php echo esc_html( $uid ); ?> .ud-meta {
					font-size: 0.75rem;
					align-self: flex-end;
				}
				#<?php echo esc_html( $uid ); ?> .ud-search-hint {
					font-size: 0.7rem;
					margin: 0 0 0.3rem;
					line-height: 1.25;
				}
			}
			@media (max-width: 1100px) {
				#<?php echo esc_html( $uid ); ?> {
					margin: 0.4rem 0;
				}
				#<?php echo esc_html( $uid ); ?> .ud-toolbar {
					margin-bottom: 0.3rem;
				}
				#<?php echo esc_html( $uid ); ?> .ud-search-row {
					flex-direction: column;
					align-items: stretch;
					gap: 0.3rem;
				}
				#<?php echo esc_html( $uid ); ?> .ud-search-group {
					width: 100%;
					flex-direction: row;
					flex-wrap: nowrap;
					align-items: center;
					gap: 0.4rem;
				}
				#<?php echo esc_html( $uid ); ?> .ud-search-row label {
					font-size: 0.78rem;
					white-space: nowrap;
					flex: 0 0 auto;
				}
				#<?php echo esc_html( $uid ); ?> .ud-label-full {
					display: none;
				}
				#<?php echo esc_html( $uid ); ?> .ud-label-short {
					display: inline;
				}
				#<?php echo esc_html( $uid ); ?> .ud-search {
					flex: 1 1 auto;
					width: auto;
					max-width: 100%;
					padding: 0.32rem 0.5rem;
					font-size: 0.85rem;
					border-radius: 6px;
				}
				#<?php echo esc_html( $uid ); ?> .ud-meta {
					font-size: 0.75rem;
					align-self: flex-end;
				}
				#<?php echo esc_html( $uid ); ?> .ud-search-hint {
					font-size: 0.7rem;
					margin: 0 0 0.3rem;
					line-height: 1.25;
				}
			}
			#<?php echo esc_html( $uid ); ?> .ud-search-hint {
				display: block;
				font-size: 0.8rem;
				color: var(--ud-muted);
				margin: 0 0 0.5rem;
				text-align: center;
			}
			#<?php echo esc_html( $uid ); ?> .superscript {
				vertical-align: super;
				font-size: 0.7em;
				line-height: 0;
			}
			#<?php echo esc_html( $uid ); ?> .ud-scroll {
				width: 100%;
				max-width: 100%;
				min-width: 0;
				border: 1px solid var(--ud-border);
				border-radius: 10px;
				overflow: visible;
				overscroll-behavior: auto;
				max-height: none;
				background: #fff;
				display: block;
				padding: 0.75rem;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table-wrap {
				display: block;
				width: 100%;
				min-width: 0;
				max-width: 100%;
			}
			#<?php echo esc_html( $uid ); ?> table.ud-table {
				display: block;
				width: 100%;
				min-width: 0;
				max-width: 100%;
				border-collapse: collapse;
				border-spacing: 0;
				font-size: 0.9rem;
				border: none;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table thead {
				display: none !important;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody {
				display: grid !important;
				width: 100%;
				gap: var(--ud-card-gap);
				grid-template-columns: 1fr;
				align-items: stretch;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr {
				display: block !important;
				width: 100%;
				min-width: 0;
				margin: 0;
				border: var(--ud-card-border-width) solid var(--ud-card-border) !important;
				border-radius: 8px !important;
				overflow: hidden;
				background: var(--ud-row-odd) !important;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.hidden {
				display: none !important;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr:nth-child(even) td,
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr:nth-child(odd) td {
				background: var(--ud-row-odd) !important;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled,
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled td,
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled:nth-child(odd) td,
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled:nth-child(even) td,
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled td.ud-sticky-col {
				background: #f08080 !important;
				background-color: #f08080 !important;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr:hover:not(.ud-disabled) td {
				background: var(--ud-row-hover) !important;
				font-weight: var(--ud-row-hover-weight) !important;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr:hover:not(.ud-disabled) td a {
				font-weight: var(--ud-row-hover-weight) !important;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled:hover td,
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled:hover td.ud-sticky-col {
				background: #e86868 !important;
				background-color: #e86868 !important;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled td.ud-sticky-col,
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled td[data-label="First name"] {
				text-decoration: line-through;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled td.ud-sticky-col::before,
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled td[data-label="First name"]::before {
				text-decoration: none;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody td {
				display: flex !important;
				align-items: flex-start;
				justify-content: space-between;
				gap: 1rem;
				white-space: normal !important;
				word-break: break-word;
				padding: 0.5rem 0.75rem !important;
				border: none;
				border-bottom: 1px solid var(--ud-border) !important;
				position: static !important;
				left: auto !important;
				box-shadow: none !important;
				vertical-align: top;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody td:last-child {
				border-bottom: none !important;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody td::before {
				content: attr(data-label);
				flex: 0 0 42%;
				max-width: 42%;
				font-weight: 600;
				color: #334155;
				padding-right: 0.5rem;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr:hover td::before {
				font-weight: var(--ud-row-hover-weight);
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr:not(.ud-disabled) td.ud-sticky-col {
				background: var(--ud-head) !important;
				font-weight: 700;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody td.ud-card-photo {
				display: flex !important;
				justify-content: center;
				align-items: center;
				padding: 0.85rem 0.75rem 0.45rem !important;
				border-bottom: none !important;
				background: var(--ud-row-odd) !important;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody td.ud-card-photo::before {
				content: none !important;
				display: none !important;
				flex: none;
				max-width: none;
				padding: 0;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled td.ud-card-photo {
				background: #f08080 !important;
				background-color: #f08080 !important;
			}
			#<?php echo esc_html( $uid ); ?> .ud-card-photo img {
				display: block;
				width: 96px;
				height: 96px;
				object-fit: cover;
				object-position: center;
				border-radius: 8px;
				border: 1px solid var(--ud-border);
				background: #f8fafc;
				pointer-events: none;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table a.ud-mail,
			#<?php echo esc_html( $uid ); ?> .ud-table a.ud-map-link {
				color: #0369a1;
				text-decoration: underline;
				text-decoration-thickness: 1px;
				text-underline-offset: 2px;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table a.ud-mail:hover,
			#<?php echo esc_html( $uid ); ?> .ud-table a.ud-map-link:hover {
				color: #0c4a6e;
			}
			#<?php echo esc_html( $uid ); ?> .ud-empty {
				padding: 1rem 0.75rem;
				color: var(--ud-muted);
				font-size: 0.9rem;
			}
			@media (min-width: 520px) {
				@supports not (container-type: inline-size) {
					#<?php echo esc_html( $uid ); ?> .ud-table tbody {
						grid-template-columns: repeat(2, minmax(0, 1fr));
					}
				}
			}
			@media (min-width: 720px) {
				@supports not (container-type: inline-size) {
					#<?php echo esc_html( $uid ); ?> .ud-table tbody {
						grid-template-columns: repeat(3, minmax(0, 1fr));
					}
				}
			}
			@media (min-width: 920px) {
				@supports not (container-type: inline-size) {
					#<?php echo esc_html( $uid ); ?> .ud-table tbody {
						grid-template-columns: repeat(4, minmax(0, 1fr));
					}
				}
			}
			@media (min-width: 1100px) {
				@supports not (container-type: inline-size) {
					#<?php echo esc_html( $uid ); ?> .ud-table tbody {
						grid-template-columns: repeat(5, minmax(0, 1fr));
					}
				}
			}
			@container ud (min-width: 520px) {
				#<?php echo esc_html( $uid ); ?> .ud-table tbody {
					grid-template-columns: repeat(2, minmax(0, 1fr));
				}
			}
			@container ud (min-width: 720px) {
				#<?php echo esc_html( $uid ); ?> .ud-table tbody {
					grid-template-columns: repeat(3, minmax(0, 1fr));
				}
			}
			@container ud (min-width: 920px) {
				#<?php echo esc_html( $uid ); ?> .ud-table tbody {
					grid-template-columns: repeat(4, minmax(0, 1fr));
				}
			}
			@container ud (min-width: 1100px) {
				#<?php echo esc_html( $uid ); ?> .ud-table tbody {
					grid-template-columns: repeat(5, minmax(0, 1fr));
				}
			}


			@media (max-width: 1100px) {
				html:has(#<?php echo esc_html( $uid ); ?>),
				html:has(#<?php echo esc_html( $uid ); ?>) body {
					overflow-x: hidden !important;
					overflow-y: auto !important;
					height: auto !important;
					max-height: none !important;
					touch-action: pan-y;
				}
			}

		</style>

		<div class="ud-toolbar">
			<div class="ud-search-row">
				<div class="ud-search-group">
					<label for="<?php echo esc_attr( $uid ); ?>-search-last"><span class="ud-label-full">Search by last name (starts with)<span class="superscript">*</span></span><span class="ud-label-short">Last name<span class="superscript">*</span></span></label>
					<input
						id="<?php echo esc_attr( $uid ); ?>-search-last"
						class="ud-search ud-search-last"
						type="search"
						autocomplete="off"
						placeholder="Type beginning of last name…"
						aria-controls="<?php echo esc_attr( $uid ); ?>-table"
					/>
				</div>
				<div class="ud-search-group">
					<label for="<?php echo esc_attr( $uid ); ?>-search-first"><span class="ud-label-full">Search by first name (starts with)<span class="superscript">*</span></span><span class="ud-label-short">First name<span class="superscript">*</span></span></label>
					<input
						id="<?php echo esc_attr( $uid ); ?>-search-first"
						class="ud-search ud-search-first"
						type="search"
						autocomplete="off"
						placeholder="Type beginning of first name…"
						aria-controls="<?php echo esc_attr( $uid ); ?>-table"
					/>
				</div>
				<span class="ud-meta" data-role="ud-count"></span>
			</div>
		</div>

		<p class="ud-search-hint"><span class="superscript">*</span> Entering last AND first name will limit search to satisfying both.</p>

		<div class="ud-scroll">
			<?php if ( empty( $rows ) ) : ?>
				<div class="ud-empty">No members found.</div>
			<?php else : ?>
				<div class="ud-table-wrap">
				<table class="ud-table" id="<?php echo esc_attr( $uid ); ?>-table">
					<thead>
						<tr>
							<th class="ud-card-photo" scope="col">Photo</th>
							<th scope="col" class="ud-sticky-col">Last name</th>
							<th scope="col">First name</th>
							<?php if ( $show_admin_cols ) : ?>
								<th scope="col">Username</th>
							<?php endif; ?>
							<th scope="col">Email</th>
							<th scope="col">Street address<br><span class="ud-col-hint"><em>Click on Street address link for a map</em></span></th>
							<th scope="col">City</th>
							<th scope="col">State</th>
							<th scope="col">Zip Code</th>
							<th scope="col">Home phone</th>
							<th scope="col">Mobile phone</th>
							<th scope="col">Spouse</th>
							<th scope="col">Birth (month/day)</th>
							<th scope="col">Sponsor</th>
							<th scope="col">Year joined</th>
							<th scope="col">Honorary member</th>
							<th scope="col">Life member</th>
							<?php if ( $show_admin_cols ) : ?>
								<th scope="col">Enabled</th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$last_raw  = isset( $row['last_name'] ) ? (string) $row['last_name'] : '';
							$last_key  = strtolower( $last_raw );
							$first_raw = isset( $row['first_name'] ) ? (string) $row['first_name'] : '';
							$first_key = strtolower( $first_raw );
							$email    = isset( $row['user_email'] ) ? trim( (string) $row['user_email'] ) : '';
							$enabled  = $is_member_enabled( $row['enabled_l'] ?? '' );
							$cell_style = $enabled ? '' : ' style="background-color:#f08080!important"';
							$photo_url  = isset( $row['photo_url'] ) ? (string) $row['photo_url'] : '';
							$full_name  = trim( (string) ( $row['first_name'] ?? '' ) . ' ' . $last_raw );
							?>
							<tr class="<?php echo $enabled ? '' : 'ud-disabled'; ?>" data-last-name="<?php echo esc_attr( $last_key ); ?>" data-first-name="<?php echo esc_attr( $first_key ); ?>">
								<td class="ud-card-photo" data-label="Photo">
									<img
										src="<?php echo esc_url( $photo_url ); ?>"
										alt="<?php echo esc_attr( $full_name ); ?>"
										width="96"
										height="96"
										loading="lazy"
										decoding="async"
									>
								</td>
								<td class="ud-sticky-col"<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="Last name"><?php echo esc_html( $last_raw ); ?></td>
								<td<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="First name"><?php echo esc_html( $row['first_name'] ?? '' ); ?></td>
								<?php if ( $show_admin_cols ) : ?>
									<td<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="Username"><?php echo esc_html( $row['user_login'] ?? '' ); ?></td>
								<?php endif; ?>
								<td<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="Email">
									<?php
									if ( '' !== $email ) {
										printf(
											'<a class="ud-mail" style="color:inherit" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
											esc_url( 'mailto:' . $email ),
											esc_html( $email )
										);
									}
									?>
								</td>
								<td<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="Street address">
									<?php
									$street = isset( $row['street_address'] ) ? trim( (string) $row['street_address'] ) : '';
									if ( '' !== $street ) {
										$addr_parts = array();
										foreach ( array( 'street_address', 'city', 'state', 'zip' ) as $addr_key ) {
											$part = isset( $row[ $addr_key ] ) ? trim( (string) $row[ $addr_key ] ) : '';
											if ( '' !== $part ) {
												$addr_parts[] = $part;
											}
										}
										$full_address   = implode( ', ', $addr_parts );
										$map_search_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $full_address );
										printf(
											'<a class="ud-map-link" style="color:inherit" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
											esc_url( $map_search_url ),
											esc_html( $street )
										);
									}
									?>
								</td>
								<td<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="City"><?php echo esc_html( $row['city'] ?? '' ); ?></td>
								<td<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="State"><?php echo esc_html( $row['state'] ?? '' ); ?></td>
								<td<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="Zip Code"><?php echo esc_html( $row['zip'] ?? '' ); ?></td>
								<td<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="Home phone"><?php echo esc_html( $row['home_phone'] ?? '' ); ?></td>
								<td<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="Mobile phone"><?php echo esc_html( $row['mobile_phone'] ?? '' ); ?></td>
								<td<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="Spouse"><?php echo esc_html( $row['spouse'] ?? '' ); ?></td>
								<td<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="Birth (month/day)"><?php echo esc_html( $row['birth'] ?? '' ); ?></td>
								<td<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="Sponsor"><?php echo esc_html( $row['sponsor'] ?? '' ); ?></td>
								<td<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="Year joined"><?php echo esc_html( $row['year_joined'] ?? '' ); ?></td>
								<td<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="Honorary member"><?php echo $fmt_yes_no( $row['honorary_member_l'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="Life member"><?php echo $fmt_yes_no( $row['life_member_l'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<?php if ( $show_admin_cols ) : ?>
									<td<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="Enabled"><?php echo esc_html( $fmt_enabled( $row['enabled_l'] ?? '' ) ); ?></td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<script>
	(function () {
		var root = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
		if (!root) return;
		var lastInput = root.querySelector('.ud-search-last');
		var firstInput = root.querySelector('.ud-search-first');
		var countEl = root.querySelector('[data-role="ud-count"]');
		var scrollEl = root.querySelector('.ud-scroll');
		var rows = Array.prototype.slice.call(root.querySelectorAll('tbody tr'));

		function unlockAncestorScroll() {
			var node = root.parentElement;
			while (node && node !== document.documentElement) {
				var skipHeight = node.classList.contains('oxy-header-container') ||
					node.classList.contains('breakdance-header') ||
					(node.tagName === 'HEADER');
				node.style.setProperty('overflow', 'visible', 'important');
				node.style.setProperty('overflow-x', 'visible', 'important');
				node.style.setProperty('overflow-y', 'visible', 'important');
				node.style.setProperty('max-height', 'none', 'important');
				if (!skipHeight) {
					node.style.setProperty('height', 'auto', 'important');
				}
				node = node.parentElement;
			}
			root.style.overflow = 'visible';
			root.style.maxHeight = 'none';
			if (scrollEl) {
				scrollEl.style.overflow = 'visible';
				scrollEl.style.maxHeight = 'none';
			}
			document.documentElement.style.setProperty('overflow-y', 'auto', 'important');
			document.body.style.setProperty('overflow-y', 'auto', 'important');
			document.documentElement.style.setProperty('height', 'auto', 'important');
			document.body.style.setProperty('height', 'auto', 'important');
		}

		window.addEventListener('resize', unlockAncestorScroll);
		unlockAncestorScroll();
		window.setTimeout(unlockAncestorScroll, 50);
		window.setTimeout(unlockAncestorScroll, 400);

		if (!lastInput || !firstInput || !rows.length) {
			if (countEl) countEl.textContent = '';
			return;
		}
		function update() {
			var lastQ = (lastInput.value || '').trim().toLowerCase();
			var firstQ = (firstInput.value || '').trim().toLowerCase();
			var shown = 0;
			rows.forEach(function (tr) {
				var ln = tr.getAttribute('data-last-name') || '';
				var fn = tr.getAttribute('data-first-name') || '';
				var match = (!lastQ || ln.startsWith(lastQ)) && (!firstQ || fn.startsWith(firstQ));
				tr.classList.toggle('hidden', !match);
				if (match) shown++;
			});
			if (countEl) {
				if ((lastQ || firstQ) && shown === 0) {
					if (lastQ && firstQ) {
						countEl.innerHTML = '<span class="ud-no-match">No match on last name and first name!</span>';
					} else if (lastQ) {
						countEl.innerHTML = '<span class="ud-no-match">No match on last name!</span>';
					} else {
						countEl.innerHTML = '<span class="ud-no-match">No match on first name!</span>';
					}
				} else {
					countEl.textContent = shown + ' of ' + rows.length + ' shown';
				}
			}
		}
		lastInput.addEventListener('input', update);
		firstInput.addEventListener('input', update);
		update();
	})();
	</script>
	<?php
	return ob_get_clean();
}

add_shortcode( 'user_directory_2', 'user_directory_shortcode_2' );
