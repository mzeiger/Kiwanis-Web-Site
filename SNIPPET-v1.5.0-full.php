<?php
/**
 * Plugin Name: MHK User Directory Shortcode
 * Description: Responsive [user_directory_2] member directory table.
 * Version: 1.5.5
 *
 * Install: copy to wp-content/plugins/mhk-user-directory/ and activate,
 * or paste into Code Snippets WITHOUT the leading <?php tag (replace existing snippet).
 */

defined( 'ABSPATH' ) || exit;

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
	<div class="user-directory-wrap ud-cq-host" id="<?php echo esc_attr( $uid ); ?>" data-ud-version="1.5.5">
		<style>
			/* Let Oxygen/Breakdance parents shrink (overflow on one container is not enough) */
			.oxy-shortcode:has(> .user-directory-wrap),
			.user-directory-shortcode:has(.user-directory-wrap),
			.bde-themeless-template-content-area:has(.user-directory-wrap),
			.oxy-container:has(.user-directory-wrap) {
				overflow: visible !important;
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
				--ud-break-table: 1100px;
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
				overflow-x: clip;
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
				align-items: center;
				justify-content: center;
				gap: 0.5rem 0.75rem;
				width: 100%;
			}
			#<?php echo esc_html( $uid ); ?> .ud-search-row label {
				font-weight: 600;
				font-size: 0.95rem;
				flex: 0 1 auto;
			}
			#<?php echo esc_html( $uid ); ?> .ud-search {
				min-width: 0;
				width: auto;
				flex: 1 1 220px;
				max-width: 360px;
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
			#<?php echo esc_html( $uid ); ?> .ud-scroll-hint {
				display: none;
				font-size: 0.8rem;
				color: var(--ud-muted);
				margin: 0 0 0.5rem;
			}
			#<?php echo esc_html( $uid ); ?> .ud-scroll {
				width: 100%;
				max-width: 100%;
				min-width: 0;
				border: 1px solid var(--ud-border);
				border-radius: 10px;
				overflow-x: scroll;
				overflow-y: auto;
				-webkit-overflow-scrolling: touch;
				overscroll-behavior-x: contain;
				max-height: min(70vh, 640px);
				background: #fff;
				display: block;
			}
			/* Wide child drives horizontal scroll; table stays max-content (not width:100%) */
			#<?php echo esc_html( $uid ); ?> .ud-table-wrap {
				display: block;
				width: max-content;
				min-width: 100%;
				max-width: none;
			}
			#<?php echo esc_html( $uid ); ?> table.ud-table {
				width: max-content;
				min-width: 100%;
				border-collapse: collapse;
				border-spacing: 0;
				font-size: 0.9rem;
				border: 1px solid var(--ud-border);
				table-layout: auto;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table thead th,
			#<?php echo esc_html( $uid ); ?> .ud-table tbody td {
				border: 1px solid var(--ud-border);
			}
			#<?php echo esc_html( $uid ); ?> .ud-table thead th {
				z-index: 2;
				background: var(--ud-head);
				color: #334155;
				text-align: left;
				font-weight: 600;
				padding: 0.65rem 0.75rem;
				white-space: nowrap;
			}
			/* ud-sticky-col: name column styling only (no position:sticky left — that blocked horizontal scroll feel) */
			#<?php echo esc_html( $uid ); ?> .ud-table thead th.ud-sticky-col {
				z-index: 3;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table thead th .ud-col-hint {
				display: block;
				margin-top: 0.15rem;
				font-size: 0.72rem;
				font-weight: 400;
				font-style: italic;
				color: #64748b;
				white-space: normal;
				line-height: 1.25;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table thead th .ud-col-hint em {
				font-weight: inherit;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody td {
				padding: 0.55rem 0.75rem;
				vertical-align: top;
				white-space: nowrap;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr:nth-child(odd) td {
				background: var(--ud-row-odd);
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr:nth-child(even) td {
				background: var(--ud-row-even);
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled td,
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled:nth-child(odd) td,
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled:nth-child(even) td,
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled td.ud-sticky-col {
				background: #f08080 !important;
				background-color: #f08080 !important;
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr:hover:not(.ud-disabled) td {
				background: var(--ud-row-hover);
				font-weight: var(--ud-row-hover-weight);
			}
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr:hover:not(.ud-disabled) td a {
				font-weight: var(--ud-row-hover-weight);
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
			#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.hidden {
				display: none !important;
			}
			#<?php echo esc_html( $uid ); ?> .ud-empty {
				padding: 1rem 0.75rem;
				color: var(--ud-muted);
				font-size: 0.9rem;
			}

			/* Wide *content area*: table scrolls inside .ud-scroll (not full viewport) */
			@container ud (min-width: 1101px) {
				#<?php echo esc_html( $uid ); ?> .ud-scroll-hint {
					display: block;
					text-align: center;
				}
				#<?php echo esc_html( $uid ); ?> .ud-scroll {
					overflow-x: scroll !important;
					overflow-y: auto !important;
					-webkit-overflow-scrolling: touch;
				}
				#<?php echo esc_html( $uid ); ?> table.ud-table {
					display: table !important;
					width: max-content !important;
					min-width: max(100%, <?php echo $show_admin_cols ? '1100px' : '960px'; ?>) !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-table thead {
					display: table-header-group !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-table tbody {
					display: table-row-group !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-table tbody tr {
					display: table-row !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-table tbody td,
				#<?php echo esc_html( $uid ); ?> .ud-table thead th {
					display: table-cell !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-table thead th {
					position: sticky;
					top: 0;
					z-index: 2;
					background: var(--ud-head);
					box-shadow: 0 1px 0 var(--ud-border);
				}
				#<?php echo esc_html( $uid ); ?> .ud-table thead th.ud-sticky-col {
					z-index: 3;
				}
			}
			@media (min-width: 1101px) {
				@supports not (container-type: inline-size) {
					#<?php echo esc_html( $uid ); ?> .ud-scroll-hint {
						display: block;
						text-align: center;
					}
					#<?php echo esc_html( $uid ); ?> .ud-scroll {
						overflow-x: scroll !important;
						overflow-y: auto !important;
						-webkit-overflow-scrolling: touch;
					}
					#<?php echo esc_html( $uid ); ?> table.ud-table {
						display: table !important;
						width: max-content !important;
						min-width: max(100%, <?php echo $show_admin_cols ? '1100px' : '960px'; ?>) !important;
					}
					#<?php echo esc_html( $uid ); ?> .ud-table thead th {
						position: sticky;
						top: 0;
						z-index: 2;
						background: var(--ud-head);
						box-shadow: 0 1px 0 var(--ud-border);
					}
					#<?php echo esc_html( $uid ); ?> .ud-table thead th.ud-sticky-col {
						z-index: 3;
					}
				}
			}

			/* Narrow *content area*: card layout — uses container width, not monitor width */
			@container ud (max-width: 1100px) {
				#<?php echo esc_html( $uid ); ?> .ud-scroll-hint {
					display: none !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-scroll {
					overflow-x: visible !important;
					overflow-y: visible !important;
					max-height: none !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-table-wrap {
					display: block !important;
					width: 100% !important;
					min-width: 0 !important;
					max-width: 100% !important;
				}
				#<?php echo esc_html( $uid ); ?> table.ud-table {
					width: 100% !important;
					min-width: 0 !important;
					max-width: 100% !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-table thead {
					display: none !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-table tbody {
					display: block !important;
					width: 100% !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-table tbody tr {
					display: block !important;
					width: 100% !important;
					margin: 0 !important;
					border: var(--ud-card-border-width) solid var(--ud-card-border) !important;
					border-radius: 8px !important;
					overflow: hidden;
					background: var(--ud-row-odd) !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-table tbody tr:not(:last-child)::after {
					content: '';
					display: block;
					margin: var(--ud-card-gap) 0;
					border-bottom: var(--ud-card-border-width) solid var(--ud-card-border);
				}
				#<?php echo esc_html( $uid ); ?> .ud-table tbody tr:nth-child(even) td,
				#<?php echo esc_html( $uid ); ?> .ud-table tbody tr:nth-child(odd) td {
					background: var(--ud-row-odd) !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled,
				#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled td,
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
				#<?php echo esc_html( $uid ); ?> .ud-table tbody td {
					display: flex !important;
					align-items: flex-start;
					justify-content: space-between;
					gap: 1rem;
					white-space: normal !important;
					word-break: break-word;
					padding: 0.5rem 0.75rem !important;
					border-bottom: 1px solid var(--ud-border) !important;
					position: static !important;
					left: auto !important;
					box-shadow: none !important;
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
			}
			@media (max-width: 1100px) {
				#<?php echo esc_html( $uid ); ?> .ud-scroll-hint {
					display: none !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-scroll {
					overflow-x: visible !important;
					overflow-y: visible !important;
					max-height: none !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-table-wrap {
					display: block !important;
					width: 100% !important;
					min-width: 0 !important;
					max-width: 100% !important;
				}
				#<?php echo esc_html( $uid ); ?> table.ud-table {
					width: 100% !important;
					min-width: 0 !important;
					max-width: 100% !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-table thead {
					display: none !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-table tbody {
					display: block !important;
					width: 100% !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-table tbody tr {
					display: block !important;
					width: 100% !important;
					margin: 0 !important;
					border: var(--ud-card-border-width) solid var(--ud-card-border) !important;
					border-radius: 8px !important;
					overflow: hidden;
					background: var(--ud-row-odd) !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-table tbody tr:not(:last-child)::after {
					content: '';
					display: block;
					margin: var(--ud-card-gap) 0;
					border-bottom: var(--ud-card-border-width) solid var(--ud-card-border);
				}
				#<?php echo esc_html( $uid ); ?> .ud-table tbody tr:nth-child(even) td,
				#<?php echo esc_html( $uid ); ?> .ud-table tbody tr:nth-child(odd) td {
					background: var(--ud-row-odd) !important;
				}
				#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled,
				#<?php echo esc_html( $uid ); ?> .ud-table tbody tr.ud-disabled td,
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
				#<?php echo esc_html( $uid ); ?> .ud-table tbody td {
					display: flex !important;
					align-items: flex-start;
					justify-content: space-between;
					gap: 1rem;
					white-space: normal !important;
					word-break: break-word;
					padding: 0.5rem 0.75rem !important;
					border-bottom: 1px solid var(--ud-border) !important;
					position: static !important;
					left: auto !important;
					box-shadow: none !important;
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
			}
		</style>

		<div class="ud-toolbar">
			<div class="ud-search-row">
				<label for="<?php echo esc_attr( $uid ); ?>-search">Search by last name (starts with)</label>
				<input
					id="<?php echo esc_attr( $uid ); ?>-search"
					class="ud-search"
					type="search"
					autocomplete="off"
					placeholder="Type beginning of last name…"
					aria-controls="<?php echo esc_attr( $uid ); ?>-table"
				/>
				<span class="ud-meta" data-role="ud-count"></span>
			</div>
		</div>

		<p class="ud-scroll-hint" aria-hidden="true">Swipe or scroll sideways to see all columns on wider screens.</p>

		<div class="ud-scroll">
			<?php if ( empty( $rows ) ) : ?>
				<div class="ud-empty">No members found.</div>
			<?php else : ?>
				<div class="ud-table-wrap">
				<table class="ud-table" id="<?php echo esc_attr( $uid ); ?>-table">
					<thead>
						<tr>
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
							$last_raw = isset( $row['last_name'] ) ? (string) $row['last_name'] : '';
							$last_key = strtolower( $last_raw );
							$email    = isset( $row['user_email'] ) ? trim( (string) $row['user_email'] ) : '';
							$enabled  = $is_member_enabled( $row['enabled_l'] ?? '' );
							$cell_style = $enabled ? '' : ' style="background-color:#f08080!important"';
							?>
							<tr class="<?php echo $enabled ? '' : 'ud-disabled'; ?>" data-last-name="<?php echo esc_attr( $last_key ); ?>">
								<td class="ud-sticky-col"<?php echo $cell_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-label="Last name"><?php echo esc_html( $row['last_name'] ?? '' ); ?></td>
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
		var input = root.querySelector('.ud-search');
		var countEl = root.querySelector('[data-role="ud-count"]');
		var scrollEl = root.querySelector('.ud-scroll');
		var tableWrap = root.querySelector('.ud-table-wrap');
		var tableEl = root.querySelector('.ud-table');
		var rows = Array.prototype.slice.call(root.querySelectorAll('tbody tr'));

		function isTableLayout() {
			if (!tableEl) return false;
			var thead = tableEl.querySelector('thead');
			return thead && window.getComputedStyle(thead).display !== 'none';
		}

		function syncTableScrollWidth() {
			if (!scrollEl || !tableWrap || !tableEl || !isTableLayout()) {
				if (tableWrap) {
					tableWrap.style.width = '';
					tableWrap.style.minWidth = '';
				}
				return;
			}
			var w = Math.max(tableEl.scrollWidth, tableEl.offsetWidth);
			if (w > 0) {
				tableWrap.style.width = w + 'px';
				tableWrap.style.minWidth = Math.max(w, scrollEl.clientWidth) + 'px';
			}
		}

		var syncTimer;
		function scheduleSync() {
			window.clearTimeout(syncTimer);
			syncTimer = window.setTimeout(syncTableScrollWidth, 80);
		}

		if (typeof ResizeObserver !== 'undefined') {
			var ro = new ResizeObserver(scheduleSync);
			ro.observe(root);
			if (tableEl) ro.observe(tableEl);
		}
		window.addEventListener('resize', scheduleSync);
		scheduleSync();

		if (!input || !rows.length) {
			if (countEl) countEl.textContent = '';
			return;
		}
		function update() {
			var q = (input.value || '').trim().toLowerCase();
			var shown = 0;
			rows.forEach(function (tr) {
				var ln = tr.getAttribute('data-last-name') || '';
				var match = !q || ln.startsWith(q);
				tr.classList.toggle('hidden', !match);
				if (match) shown++;
			});
			if (countEl) {
				if (q && shown === 0) {
					countEl.innerHTML = '<span class="ud-no-match">No match on last name!</span>';
				} else {
					countEl.textContent = shown + ' of ' + rows.length + ' shown';
				}
			}
		}
		input.addEventListener('input', update);
		update();
		scheduleSync();
	})();
	</script>
	<?php
	return ob_get_clean();
}

add_shortcode( 'user_directory_2', 'user_directory_shortcode_2' );
