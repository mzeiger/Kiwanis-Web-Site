<?php
/**
 * [kiwanis_admin_edit_profile] — Baseline: lookup by username/email, then edit Kiwanis fields + enabled.
 *
 * Editor opens only when URL has kr_m (target user id) + _kraen (nonce). Param renamed from kr_uid to kr_m
 * to avoid clashes with other plugins/themes that reuse generic query keys.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'kr_normalize_phone' ) ) {
	function kr_normalize_phone( $raw ) {
		$digits = preg_replace( '/\D+/', '', (string) $raw );
		if ( strlen( $digits ) !== 10 ) {
			return '';
		}
		return substr( $digits, 0, 3 ) . '-' . substr( $digits, 3, 3 ) . '-' . substr( $digits, 6, 4 );
	}
}

if ( ! function_exists( 'kr_validate_phone_field' ) ) {
	function kr_validate_phone_field( $raw ) {
		$t = trim( (string) $raw );
		if ( $t === '' ) {
			return '';
		}
		$n = kr_normalize_phone( $t );
		if ( $n === '' ) {
			return new WP_Error( 'phone', 'Phone must be ###-###-#### (10 digits).' );
		}
		return $n;
	}
}

/**
 * Phone value for HTML inputs: normalized ###-###-#### when 10 digits, otherwise trimmed raw (admin repair).
 */
if ( ! function_exists( 'kr_ae_phone_display' ) ) {
	function kr_ae_phone_display( $raw ) {
		$n = kr_normalize_phone( $raw );
		return $n !== '' ? $n : trim( (string) $raw );
	}
}

if ( ! function_exists( 'mhk_member_images_dir' ) ) {
	/**
	 * Absolute path to public_html/wp-mhk/member_images.
	 */
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
	/**
	 * Associated image filename, or unknown.webp when missing.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
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
	/**
	 * Public URL for a member photo (falls back to unknown.webp).
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
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

if ( ! function_exists( 'mhk_member_has_custom_image' ) ) {
	function mhk_member_has_custom_image( $user_id ) {
		return 'unknown.webp' !== mhk_member_image_filename( $user_id );
	}
}

if ( ! function_exists( 'mhk_delete_member_image' ) ) {
	/**
	 * Remove a member photo file and clear member_image meta. Leaves unknown.webp in place.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if a custom image was present and removed.
	 */
	function mhk_delete_member_image( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}

		$dir     = mhk_member_images_dir();
		$removed = false;
		$old     = basename( (string) get_user_meta( $user_id, 'member_image', true ) );
		if ( $old && 'unknown.webp' !== $old && preg_match( '/^[A-Za-z0-9._-]+$/', $old ) ) {
			$old_path = $dir . '/' . $old;
			if ( is_file( $old_path ) ) {
				$removed = @unlink( $old_path ) || $removed;
			}
		}

		foreach ( array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ) as $ext ) {
			$path = $dir . '/' . $user_id . '.' . $ext;
			if ( is_file( $path ) ) {
				$removed = @unlink( $path ) || $removed;
			}
		}

		delete_user_meta( $user_id, 'member_image' );
		return $removed || ( $old && 'unknown.webp' !== $old );
	}
}

/**
 * Canonical URL of the PAGE that hosts the shortcode (used in hidden kr_ae_return).
 */
function kr_ae_editor_page_canonical_base() {
	global $post;

	if ( isset( $post->ID ) && $post->ID ) {
		return get_permalink( $post->ID );
	}

	return home_url( '/' );
}

/**
 * Intended redirect URL during admin-post (where global $post is wrong).
 */
function kr_ae_return_base() {
	if ( isset( $_POST['kr_ae_return'] ) ) {
		$u = esc_url_raw( wp_unslash( $_POST['kr_ae_return'] ) );
		$ok = wp_validate_redirect( $u, false );

		if ( $ok ) {
			return $ok;
		}
	}

	$ref = wp_get_referer();
	if ( $ref ) {
		$r = wp_validate_redirect( esc_url_raw( $ref ), false );
		if ( $r ) {
			return remove_query_arg(
				array( 'kr_m', '_kraen', '_wpnonce', 'kr_ae_error', 'kr_ok' ),
				$r
			);
		}
	}

	global $post;
	if ( isset( $post->ID ) && $post->ID ) {
		return get_permalink( $post->ID );
	}

	return home_url( '/' );
}

function kr_ae_bail_message( $message ) {
	wp_safe_redirect( add_query_arg( 'kr_ae_error', rawurlencode( wp_strip_all_tags( (string) $message ) ), kr_ae_return_base() ) );
	exit;
}

function kr_ae_get_request_error_message() {
	if ( isset( $_GET['kr_ae_error'] ) ) {
		return sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['kr_ae_error'] ) ) );
	}

	return '';
}

/**
 * Lookup target member (login or email) or return readable error string / empty WP_Error.
 *
 * @return array{user?: WP_User, error?: string}
 */
function kr_ae_lookup_member_resolve( string $query ) : array {
	$query = trim( $query );

	if ( $query === '' ) {
		return array( 'error' => 'Enter a username or email address.' );
	}

	if ( is_email( $query ) ) {
		$user = get_user_by( 'email', sanitize_email( $query ) );
	} else {
		$user = get_user_by( 'login', sanitize_user( $query, false ) );
	}

	if ( ! $user instanceof WP_User ) {
		return array( 'error' => 'No user found with that username or email.' );
	}

	$tid = (int) $user->ID;

	if ( ! user_can_edit_user( get_current_user_id(), $tid ) ) {
		return array( 'error' => 'You are not allowed to edit that member.' );
	}

	return array( 'user' => $user );
}

/**
 * Redirect URL builder after lookup — full URL safe for redirects and JS location.
 *
 * @return string|'bad_base'
 */
function kr_ae_lookup_redirect_dest( WP_User $user ) {
	$return_prop = isset( $_POST['kr_ae_return'] ) ? esc_url_raw( wp_unslash( $_POST['kr_ae_return'] ) ) : '';
	$b           = $return_prop ? wp_validate_redirect( $return_prop, false ) : false;

	if ( ! $b ) {
		return 'bad_base';
	}

	$t = (int) $user->ID;

	return add_query_arg(
		array(
			'kr_m'   => $t,
			'_kraen' => wp_create_nonce( 'kr_ae_form_' . $t ),
		),
		$b
	);
}

/**
 * LOOK UP USER (fallback if JS disabled): admin-post ----------------------------
 */
add_action(
	'admin_post_kr_ae_lookup',
	static function () {
		if ( ! current_user_can( 'edit_users' ) ) {
			kr_ae_bail_message( 'You do not have permission to edit users.' );
		}

		check_admin_referer( 'kr_ae_lookup', '_krnlen' );

		$query = isset( $_POST['kr_ae_who'] ) ? sanitize_text_field( wp_unslash( $_POST['kr_ae_who'] ) ) : '';
		$out     = kr_ae_lookup_member_resolve( $query );

		if ( isset( $out['error'] ) ) {
			kr_ae_bail_message( $out['error'] );
		}

		$r = kr_ae_lookup_redirect_dest( $out['user'] );
		if ( 'bad_base' === $r ) {
			kr_ae_bail_message( 'Invalid return page. Reload this screen from your admin editor page.' );
		}

		wp_safe_redirect( $r );
		exit;
	}
);

/**
 * LOOK UP USER (AJAX — avoids nested-<form> issues in builders like Oxygen).
 */
add_action(
	'wp_ajax_kr_ae_lookup_member',
	static function () {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error( array( 'message' => 'You do not have permission to edit users.' ), 403 );
		}

		check_ajax_referer( 'kr_ae_lookup', 'nonce' );

		$query = isset( $_POST['kr_ae_who'] ) ? sanitize_text_field( wp_unslash( $_POST['kr_ae_who'] ) ) : '';
		$out   = kr_ae_lookup_member_resolve( $query );

		if ( isset( $out['error'] ) ) {
			wp_send_json_error( array( 'message' => $out['error'] ) );
		}

		$url = kr_ae_lookup_redirect_dest( $out['user'] );
		if ( 'bad_base' === $url ) {
			wp_send_json_error( array( 'message' => 'Return URL missing or invalid — reload from the page where you placed [kiwanis_admin_edit_profile].' ) );
		}

		wp_send_json_success( array( 'redirect' => $url ) );
	}
);

/**
 * Enabled = 0 locks the WordPress login. Blank meta stays unlocked (directory treats blank as enabled).
 *
 * @param int $user_id User ID.
 * @return bool
 */
function kr_ae_user_is_login_locked( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return false;
	}

	$raw = strtolower( trim( (string) get_user_meta( $user_id, 'enabled', true ) ) );
	return in_array( $raw, array( '0', 'no', 'n', 'false', 'off' ), true );
}

/**
 * Reject password login for a disabled member.
 *
 * @param WP_User|WP_Error|null $user     Authenticated user, or an earlier error.
 * @param string                $username Unused.
 * @param string                $password Unused.
 * @return WP_User|WP_Error|null
 */
function kr_ae_block_locked_member_login( $user, $username, $password ) {
	unset( $username, $password );

	if ( ! $user instanceof WP_User ) {
		return $user;
	}

	if ( ! kr_ae_user_is_login_locked( $user->ID ) ) {
		return $user;
	}

	return new WP_Error(
		'kr_account_disabled',
		'<strong>Error:</strong> This account has been disabled. Contact a club administrator.'
	);
}
add_filter( 'authenticate', 'kr_ae_block_locked_member_login', 99, 3 );

/**
 * Disabled members cannot request a password reset.
 *
 * @param bool|WP_Error $allow   Whether reset is allowed.
 * @param int           $user_id User ID.
 * @return bool|WP_Error
 */
function kr_ae_block_locked_member_password_reset( $allow, $user_id ) {
	if ( kr_ae_user_is_login_locked( $user_id ) ) {
		return new WP_Error(
			'kr_account_disabled',
			'This account has been disabled. Contact a club administrator.'
		);
	}

	return $allow;
}
add_filter( 'allow_password_reset', 'kr_ae_block_locked_member_password_reset', 10, 2 );

/**
 * Sign out a member who is already logged in when their account is disabled.
 */
function kr_ae_logout_locked_member() {
	if ( ! is_user_logged_in() || wp_doing_cron() ) {
		return;
	}

	$user_id = get_current_user_id();
	if ( ! kr_ae_user_is_login_locked( $user_id ) ) {
		return;
	}

	wp_logout();

	if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	wp_safe_redirect( wp_login_url() );
	exit;
}
add_action( 'init', 'kr_ae_logout_locked_member', 1 );

/**
 * SAVE PROFILE ---------------------------------------------------------------
 */
add_action(
	'admin_post_kr_ae_save',
	static function () {
		if ( ! current_user_can( 'edit_users' ) ) {
			kr_ae_bail_message( 'You do not have permission to edit users.' );
		}

		$target_id = isset( $_POST['kr_uid_hidden'] ) ? absint( $_POST['kr_uid_hidden'] ) : 0;

		if ( ! $target_id ) {
			kr_ae_bail_message( 'Invalid member selected.' );
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_krsave'] ?? '' ) ), 'kr_ae_save_' . $target_id ) ) {
			kr_ae_bail_message( 'Expired save nonce. Reload and try again.' );
		}

		if (
			! isset( $_POST['_kraen_save'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_kraen_save'] ) ), 'kr_ae_form_' . $target_id )
		) {
			kr_ae_bail_message( 'Stale security token. Re-open member from lookup.' );
		}

		if ( ! user_can_edit_user( get_current_user_id(), $target_id ) ) {
			kr_ae_bail_message( 'You are not allowed to edit that member.' );
		}

		$wp_user = get_userdata( $target_id );

		if ( ! $wp_user instanceof WP_User ) {
			kr_ae_bail_message( 'Member no longer exists.' );
		}

		$user_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		$pass1      = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
		$pass2      = isset( $_POST['pass2'] ) ? (string) wp_unslash( $_POST['pass2'] ) : '';

		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';

		$street = isset( $_POST['street_address'] ) ? sanitize_text_field( wp_unslash( $_POST['street_address'] ) ) : '';
		$city   = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$state  = isset( $_POST['state'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['state'] ) ) ) : '';
		$zip    = isset( $_POST['zip'] ) ? sanitize_text_field( wp_unslash( $_POST['zip'] ) ) : '';

		$spouse           = isset( $_POST['spouse'] ) ? sanitize_text_field( wp_unslash( $_POST['spouse'] ) ) : '';
		$birth_month      = isset( $_POST['birth_month'] ) ? absint( $_POST['birth_month'] ) : 0;
		$birth_day        = isset( $_POST['birth_day'] ) ? absint( $_POST['birth_day'] ) : 0;
		$sponsor          = isset( $_POST['sponsor'] ) ? sanitize_text_field( wp_unslash( $_POST['sponsor'] ) ) : '';
		$year_joined      = isset( $_POST['year_joined_kiwanis'] ) ? sanitize_text_field( wp_unslash( $_POST['year_joined_kiwanis'] ) ) : '';
		$honorary_member  = isset( $_POST['honorary_member'] ) ? sanitize_text_field( wp_unslash( $_POST['honorary_member'] ) ) : '';
		$life_member      = isset( $_POST['life_member'] ) ? sanitize_text_field( wp_unslash( $_POST['life_member'] ) ) : '';
		$enabled_status   = isset( $_POST['enabled_status'] ) ? sanitize_text_field( wp_unslash( $_POST['enabled_status'] ) ) : '';

		$home_phone   = wp_unslash( $_POST['home_phone'] ?? '' );
		$mobile_phone = wp_unslash( $_POST['mobile_phone'] ?? '' );

		if ( $user_email === '' || ! is_email( $user_email ) ) {
			kr_ae_bail_message( 'Valid email required.' );
		}

		$exists = email_exists( $user_email );
		if ( $exists && (int) $exists !== $target_id ) {
			kr_ae_bail_message( 'That email is already assigned to someone else.' );
		}

		if ( $first_name === '' || $last_name === '' ) {
			kr_ae_bail_message( 'First and last name are required.' );
		}

		if ( '' !== $pass1 || '' !== $pass2 ) {
			if ( $pass1 !== $pass2 ) {
				kr_ae_bail_message( 'Password fields do not match.' );
			}
		}

		$h = kr_validate_phone_field( $home_phone );
		if ( is_wp_error( $h ) ) {
			kr_ae_bail_message( $h->get_error_message() );
		}
		$m = kr_validate_phone_field( $mobile_phone );
		if ( is_wp_error( $m ) ) {
			kr_ae_bail_message( $m->get_error_message() );
		}

		$digits_home   = preg_replace( '/\D+/', '', (string) $home_phone );
		$digits_mobile = preg_replace( '/\D+/', '', (string) $mobile_phone );
		if ( '' === $digits_home && '' === $digits_mobile ) {
			kr_ae_bail_message( 'Provide at least one telephone number.' );
		}

		if ( $street === '' || $city === '' ) {
			kr_ae_bail_message( 'Street address and city required.' );
		}

		if ( ! preg_match( '/^[A-Z]{2}$/', $state ) ) {
			kr_ae_bail_message( 'State must be exactly two letters (e.g., NC).' );
		}
		if ( ! preg_match( '/^\d{5}$/', $zip ) ) {
			kr_ae_bail_message( 'ZIP code must contain five digits.' );
		}

		if ( $birth_month < 1 || $birth_month > 12 ) {
			kr_ae_bail_message( 'Choose a birth month.' );
		}

		$month_days = array( 31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		$max_day    = (int) $month_days[ $birth_month - 1 ];
		if ( $birth_day < 1 || $birth_day > $max_day ) {
			kr_ae_bail_message( 'Birth day invalid for chosen month.' );
		}

		if ( ! preg_match( '/^\d{4}$/', $year_joined ) ) {
			kr_ae_bail_message( 'Year joined must be ####.' );
		}

		if ( ! in_array( $honorary_member, array( 'yes', 'no' ), true ) || ! in_array( $life_member, array( 'yes', 'no' ), true ) ) {
			kr_ae_bail_message( 'Answer honorary and life member questions.' );
		}

		if ( ! in_array( $enabled_status, array( '0', '1' ), true ) ) {
			kr_ae_bail_message( 'Choose Enabled YES or NO.' );
		}

		if ( '0' === $enabled_status && (int) get_current_user_id() === $target_id ) {
			kr_ae_bail_message( 'You cannot disable your own account.' );
		}

		$wp_update_args = array(
			'ID'         => $target_id,
			'user_email' => $user_email,
			'first_name' => $first_name,
			'last_name'  => $last_name,
		);

		if ( '' !== $pass1 ) {
			$wp_update_args['user_pass'] = $pass1;
		}

		$updated_core = wp_update_user( wp_slash( $wp_update_args ) );

		if ( is_wp_error( $updated_core ) ) {
			kr_ae_bail_message( $updated_core->get_error_message() );
		}

		update_user_meta( $target_id, 'home_phone', $h );
		update_user_meta( $target_id, 'mobile_phone', $m );
		update_user_meta( $target_id, 'street_address', $street );
		update_user_meta( $target_id, 'city', $city );
		update_user_meta( $target_id, 'state', $state );
		update_user_meta( $target_id, 'zip', $zip );
		update_user_meta( $target_id, 'spouse', $spouse );
		update_user_meta( $target_id, 'birth_month', $birth_month );
		update_user_meta( $target_id, 'birth_day', $birth_day );
		update_user_meta( $target_id, 'sponsor', $sponsor );
		update_user_meta( $target_id, 'year_joined_kiwanis', $year_joined );
		update_user_meta( $target_id, 'honorary_member', $honorary_member );
		update_user_meta( $target_id, 'life_member', $life_member );
		update_user_meta( $target_id, 'enabled', '1' === $enabled_status ? '1' : '0' );

		if ( '0' === $enabled_status && class_exists( 'WP_Session_Tokens' ) ) {
			WP_Session_Tokens::get_instance( $target_id )->destroy_all();
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'kr_m'  => $target_id,
					'kr_ok' => '1',
				),
				kr_ae_return_base()
			),
			'kr_ae_form_' . $target_id,
			'_kraen'
		);

		wp_safe_redirect( $url );
		exit;
	}
);

/**
 * AJAX: email uniqueness for ADMIN edit (optional UX)
 */
function kr_ajax_kr_ae_check_email_admin() {
	if ( ! current_user_can( 'edit_users' ) ) {
		wp_send_json_error( array( 'message' => 'Denied.' ), 403 );
	}

	check_ajax_referer( 'kr_ae_ajax_mail', 'nonce' );

	$target_id = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;
	$email_raw = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

	if ( ! $target_id || ! $email_raw || ! is_email( $email_raw ) ) {
		wp_send_json_error( array( 'message' => 'Bad input.' ), 400 );
	}

	if ( ! user_can_edit_user( get_current_user_id(), $target_id ) ) {
		wp_send_json_error( array( 'message' => 'Forbidden.' ), 403 );
	}

	$wp_user_check = get_userdata( $target_id );
	if ( ! $wp_user_check instanceof WP_User ) {
		wp_send_json_error( array( 'message' => 'User missing.' ), 404 );
	}

	if ( strcasecmp( $email_raw, $wp_user_check->user_email ) === 0 ) {
		wp_send_json_success(
			array(
				'ok'      => true,
				'message' => 'Current email unchanged.',
			)
		);
	}

	$exists = email_exists( $email_raw );
	if ( $exists && (int) $exists !== $target_id ) {
		wp_send_json_error( array( 'message' => 'Email already belongs to someone else.' ) );
	}

	wp_send_json_success(
		array(
			'ok'      => true,
			'message' => 'Looks free.',
		)
	);
}

add_action( 'wp_ajax_kr_ae_check_email_admin', 'kr_ajax_kr_ae_check_email_admin' );

/**
 * AJAX: upload a member photo into member_images and store the filename in user meta `member_image`.
 */
add_action(
	'wp_ajax_kr_ae_upload_member_image',
	static function () {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error( array( 'message' => 'You do not have permission to edit users.' ) );
		}

		$target_id = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;
		if ( $target_id <= 0 || ! user_can_edit_user( get_current_user_id(), $target_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid member selected.' ) );
		}

		check_ajax_referer( 'kr_ae_upload_' . $target_id, 'nonce' );

		if ( empty( $_FILES['member_image'] ) || ! is_array( $_FILES['member_image'] ) ) {
			wp_send_json_error( array( 'message' => 'Choose an image file first.' ) );
		}

		$file = $_FILES['member_image'];
		if ( ! empty( $file['error'] ) ) {
			wp_send_json_error( array( 'message' => 'Upload failed. Try a smaller image.' ) );
		}
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'No file received.' ) );
		}
		if ( (int) $file['size'] > 5 * 1024 * 1024 ) {
			wp_send_json_error( array( 'message' => 'Image must be 5 MB or smaller.' ) );
		}

		$check   = wp_check_filetype_and_ext( $file['tmp_name'], isset( $file['name'] ) ? $file['name'] : '' );
		$allowed = array( 'jpg', 'jpeg', 'png', 'webp', 'gif' );
		$ext     = strtolower( (string) ( $check['ext'] ?? '' ) );
		if ( ! $ext || ! in_array( $ext, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => 'Use a JPG, PNG, WebP, or GIF image.' ) );
		}

		$info = @getimagesize( $file['tmp_name'] );
		if ( ! $info ) {
			wp_send_json_error( array( 'message' => 'That file is not a valid image.' ) );
		}

		$dir = mhk_member_images_dir();
		if ( ! wp_mkdir_p( $dir ) || ! is_writable( $dir ) ) {
			wp_send_json_error( array( 'message' => 'The member_images folder is not writable.' ) );
		}

		$old = basename( (string) get_user_meta( $target_id, 'member_image', true ) );
		if ( $old && 'unknown.webp' !== $old && preg_match( '/^[A-Za-z0-9._-]+$/', $old ) ) {
			$old_path = $dir . '/' . $old;
			if ( is_file( $old_path ) ) {
				@unlink( $old_path );
			}
		}

		$filename = $target_id . '.' . $ext;
		$dest     = $dir . '/' . $filename;
		if ( is_file( $dest ) && $dest !== $dir . '/' . $old ) {
			@unlink( $dest );
		}
		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			wp_send_json_error( array( 'message' => 'Could not save the image on the server.' ) );
		}
		@chmod( $dest, 0644 );

		update_user_meta( $target_id, 'member_image', $filename );

		wp_send_json_success(
			array(
				'url'      => mhk_member_image_url( $target_id ),
				'filename' => $filename,
				'message'  => 'Photo uploaded and associated with this member.',
			)
		);
	}
);

/**
 * AJAX: remove a member photo and fall back to unknown.webp.
 */
add_action(
	'wp_ajax_kr_ae_remove_member_image',
	static function () {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error( array( 'message' => 'You do not have permission to edit users.' ) );
		}

		$target_id = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;
		if ( $target_id <= 0 || ! user_can_edit_user( get_current_user_id(), $target_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid member selected.' ) );
		}

		check_ajax_referer( 'kr_ae_upload_' . $target_id, 'nonce' );

		if ( ! mhk_member_has_custom_image( $target_id ) ) {
			wp_send_json_error( array( 'message' => 'This member does not have a photo to remove.' ) );
		}

		mhk_delete_member_image( $target_id );

		wp_send_json_success(
			array(
				'url'     => mhk_member_image_url( $target_id ),
				'message' => 'Photo removed. The User Directory will show the placeholder image.',
			)
		);
	}
);

/**
 * LOOKUP SCREEN HTML — no wrapping <form> (avoids invalid nested forms in page builders).
 */
function kr_ae_render_lookup_markup( string $banner_error = '', string $banner_notice = '' ) {
	$page_canonical_raw = kr_ae_editor_page_canonical_base();
	$page_safe          = esc_url( $page_canonical_raw );
	$nonce              = wp_create_nonce( 'kr_ae_lookup' );
	$ajax               = esc_url( admin_url( 'admin-ajax.php' ) );
	$error_html         = $banner_error !== '' ? '<div class="kr-ae-flash kr-ae-flash-error">' . esc_html( $banner_error ) . '</div>' : '';
	$notice_html        = $banner_notice !== '' ? '<div class="kr-ae-flash kr-ae-flash-success">' . esc_html( $banner_notice ) . '</div>' : '';

	return '
<div class="kr-ae-wrap">
	<style>
		.oxy-container.container-24{width:100%;max-width:100%;margin-left:auto;margin-right:auto;box-sizing:border-box;}
		.kr-ae-wrap{width:100%;max-width:520px;margin:1.75rem auto;box-sizing:border-box;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#111827;line-height:1.55;}
		.kr-ae-wrap input[type=text],.kr-ae-wrap input[type=email]{width:100%;box-sizing:border-box;padding:.62rem;border:1px solid #cbd5e1;border-radius:10px;margin-top:.4rem;font-size:1rem;}
		.kr-ae-btn{margin-top:.9rem;display:inline-flex;gap:.55rem;border:0;background:#111827;color:#fff;padding:.72rem 1.2rem;font-weight:700;border-radius:10px;cursor:pointer;}
		.kr-ae-btn:hover{background:#0b1220;}
		.kr-ae-btn:disabled{opacity:.55;cursor:not-allowed;}
		.kr-ae-flash{margin:.75rem 0;padding:.8rem;border-radius:10px;}
		.kr-ae-flash-error{border:1px solid #fca5a5;background:#fef2f2;color:#991b1b;}
		.kr-ae-flash-success{border:1px solid #86efac;background:#ecfdf5;color:#065f46;}
		.kr-ae-muted{color:#64748b;font-size:.93rem;margin-top:.35rem;}
		.kr-ae-lookup-busy{font-size:.9rem;color:#4338ca;margin-top:.65rem;display:none;font-weight:600;}
	</style>
	' . $error_html . $notice_html . '
	<noscript>
		<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post">
			<input type="hidden" name="action" value="kr_ae_lookup"/>
			' . wp_nonce_field( 'kr_ae_lookup', '_krnlen', true, false ) . '
			<input type="hidden" name="kr_ae_return" value="' . $page_safe . '"/>
			<label for="kr_ae_who_fallback"><strong>Member username OR email address</strong></label>
			<input type="text" id="kr_ae_who_fallback" name="kr_ae_who" required/>
			<p class="kr-ae-muted">JavaScript is off — fallback posts to wp-admin/admin-post.php.</p>
			<button class="kr-ae-btn" type="submit">Load profile</button>
		</form>
	</noscript>
	<div id="kr-ae-lookup-root"
		style="display:contents;"
		data-ajax="' . esc_attr( $ajax ) . '"
		data-nonce="' . esc_attr( $nonce ) . '"
		data-return="' . esc_attr( $page_canonical_raw ) . '">

	<label for="kr_ae_who_field"><strong>Member username OR email address</strong></label>
	<input type="text" id="kr_ae_who_field" name="kr_ae_who_live" autocomplete="username" placeholder="e.g., jane_member or member@club.org"/>
	<p id="kr_ae_lookup_msg" class="kr-ae-flash kr-ae-flash-error" style="display:none;margin:.75rem 0;" role="alert" aria-live="polite"></p>
	<p class="kr-ae-muted">Enter the member’s WordPress username or email, then click Load profile.</p>
	<button class="kr-ae-btn" id="kr_ae_lookup_btn" type="button">Load profile</button>
	<p id="kr_ae_lookup_busy" class="kr-ae-lookup-busy" aria-live="polite">Looking up member…</p>
	</div>
</div>
<script>
(function(){var r=document.getElementById(\'kr-ae-lookup-root\');if(!r)return;var ajax=r.getAttribute(\'data-ajax\');var nonce=r.getAttribute(\'data-nonce\');var ret=r.getAttribute(\'data-return\');var inp=document.getElementById(\'kr_ae_who_field\');var btn=document.getElementById(\'kr_ae_lookup_btn\');var msg=document.getElementById(\'kr_ae_lookup_msg\');var busy=document.getElementById(\'kr_ae_lookup_busy\');function showErr(t){if(!msg)return;msg.textContent=t||\'\';msg.style.display=t?\'block\':\'none\';}if(inp){r.addEventListener(\'keydown\',function(e){if(e.key===\'Enter\'&&e.target===inp){e.preventDefault();if(btn)btn.click();}});}if(btn){btn.addEventListener(\'click\',function(){showErr(\'\');var who=(inp&&inp.value||\'\').trim();if(!who){showErr(\'Enter a username or email address.\');return;}btn.disabled=true;if(busy)busy.style.display=\'block\';var b=new URLSearchParams();b.set(\'action\',\'kr_ae_lookup_member\');b.set(\'nonce\',nonce);b.set(\'kr_ae_who\',who);b.set(\'kr_ae_return\',ret||\'\');fetch(ajax,{method:\'POST\',credentials:\'same-origin\',headers:{\'Content-Type\':\'application/x-www-form-urlencoded\'},body:b.toString()}).then(function(x){return x.json();}).then(function(j){if(busy)busy.style.display=\'none\';btn.disabled=false;if(!j.success){showErr(j.data&&j.data.message?j.data.message:\'Lookup failed.\');return;}window.location.href=j.data.redirect;}).catch(function(){if(busy)busy.style.display=\'none\';btn.disabled=false;showErr(\'Network error or invalid response.\');});});}})();
</script>';
}

/**
 * EDITOR SCREEN HTML ---------------------------------------------------------.
 *
 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped — assembled HTML above & below uses esc_* inline.
 */

function kr_ae_render_editor_markup( WP_User $user, string $error_notice = '', string $ok_notice = '' ) {
	$id = (int) $user->ID;

	$months = array(
		1  => 'January',
		2  => 'February',
		3  => 'March',
		4  => 'April',
		5  => 'May',
		6  => 'June',
		7  => 'July',
		8  => 'August',
		9  => 'September',
		10 => 'October',
		11 => 'November',
		12 => 'December',
	);

	$m = array(
		'home_phone'          => get_user_meta( $id, 'home_phone', true ),
		'mobile_phone'        => get_user_meta( $id, 'mobile_phone', true ),
		'street_address'      => get_user_meta( $id, 'street_address', true ),
		'city'                => get_user_meta( $id, 'city', true ),
		'state'               => get_user_meta( $id, 'state', true ),
		'zip'                 => get_user_meta( $id, 'zip', true ),
		'spouse'              => get_user_meta( $id, 'spouse', true ),
		'birth_month'         => (int) get_user_meta( $id, 'birth_month', true ),
		'birth_day'           => (int) get_user_meta( $id, 'birth_day', true ),
		'sponsor'             => get_user_meta( $id, 'sponsor', true ),
		'year_joined_kiwanis' => get_user_meta( $id, 'year_joined_kiwanis', true ),
		'honorary_member'     => get_user_meta( $id, 'honorary_member', true ),
		'life_member'         => get_user_meta( $id, 'life_member', true ),
		'enabled'             => get_user_meta( $id, 'enabled', true ),
	);

	if ( '' === $m['enabled'] ) {
		$m['enabled'] = '0'; // sensible default visually = No
	}

	$page_base = kr_ae_editor_page_canonical_base();
	$page_safe = esc_url( $page_base );
	$post_url  = esc_url( admin_url( 'admin-post.php' ) );

	$error_box = '';
	if ( '' !== $error_notice ) {
		$error_box = '<div class="kr-ae-flash kr-ae-flash-error">' . esc_html( $error_notice ) . '</div>';
	}
	$ok_box = '';
	if ( '' !== $ok_notice ) {
		$ok_box = '<div class="kr-ae-flash kr-ae-flash-success">' . esc_html( $ok_notice ) . '</div>';
	}

	$month_options_html = '';

	foreach ( $months as $key => $label ) {
		$month_options_html .= sprintf(
			'<option value="%d" %s>%s</option>',
			$key,
			selected( $m['birth_month'], $key, false ),
			esc_html( $label )
		);
	}

	$ajax_nonce = esc_attr( wp_create_nonce( 'kr_ae_ajax_mail' ) );

	ob_start();

	$photo_url         = mhk_member_image_url( $id );
	$has_custom_photo  = mhk_member_has_custom_image( $id );
	$upload_nonce_attr = esc_attr( wp_create_nonce( 'kr_ae_upload_' . $id ) );

	echo '<div id="kr-ae-root" class="kr-ae-wrap kr-ae-editor-shell" ';
	echo 'data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" ';
	echo 'data-email-nonce="' . $ajax_nonce . '" ';
	echo 'data-upload-nonce="' . $upload_nonce_attr . '" ';
	echo 'data-member-id="' . esc_attr( (string) $id ) . '" ';
	echo 'data-saved-bd="' . esc_attr( (string) max( (int) $m['birth_day'], 1 ) ) . '">';
	?>
<style>
.oxy-container.container-24 {
	width: 100%;
	max-width: 100%;
	margin-left: auto;
	margin-right: auto;
	box-sizing: border-box;
}
.kr-ae-editor-shell {
	width: 100%;
	max-width: 960px;
	margin: 2rem auto 3rem auto;
	box-sizing: border-box;
	padding: clamp(14px,3vw,30px);
	background: #fafafa;
	border: 1px solid #cbd5f5;
	border-radius: 22px;
	font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
	color: #020617;
}
.kr-ae-stack {
	display: flex;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: .75rem 1rem;
	align-items: center;
}
.kr-ae-reset {
	display: inline-flex;
	align-items: center;
	gap: .48rem;
	font-weight: 700;
	padding: .6rem 1rem;
	border-radius: 999px;
	background: white;
	border: 1px solid #dbeafe;
	color: #0f172a;
	text-decoration: none;
}
.kr-ae-reset:hover{border-color:#60a5fa;}
.kr-ae-chip{
	display:inline-flex;align-items:center;gap:.4rem;background:#eef2ff;border-radius:999px;padding:.42rem .8rem;color:#4338ca;font-weight:650;font-size:.88rem;margin-left:.65rem;border:1px solid #dbeafe;}
.kr-ae-fields{
	display:grid;
	grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
	column-gap:1.4rem;
}
.kr-ae-group{
	grid-column:1/-1;background:white;border-radius:18px;padding:clamp(14px,2.5vw,22px);border:1px solid #e2e8f0;margin:.75rem 0;
}
.kr-ae-group h3{margin:.2rem 0 .95rem;font-size:clamp(17px,1.95vw,20px);}
.kr-ae-single{grid-column:1/-1;}
.kr-ae-fields label{font-weight:600;font-size:.9rem;display:flex;flex-direction:column;margin:.72rem .12rem;color:#475569;}
.kr-ae-fields input[type=text],
.kr-ae-fields input[type=email],
.kr-ae-fields input[type=password],
.kr-ae-fields select{
	font:inherit;color:#0f172a;border:1px solid #dbe7f3;border-radius:12px;background:#fdfefe;padding:.68rem;margin-top:.4rem;width:100%;box-sizing:border-box;
}
.kr-ae-muted{color:#475569;margin:.25rem .1rem;line-height:1.45;font-size:.9rem;font-weight:normal;}
.kr-ae-required{color:#dc2626;}
.kr-ae-flag-row{display:flex;gap:1rem;flex-wrap:wrap;margin-top:.42rem;font-weight:normal!important;}
.kr-ae-flag-row span{display:inline-flex;gap:.58rem;background:#eef2ff;border-radius:999px;padding:.35rem .8rem;color:#4338ca;}
.kr-ae-flag-row span input{margin:0;width:auto;height:auto;}
.kr-ae-flash{border-radius:13px;margin:.72rem .12rem;font-weight:550;padding:.9rem;line-height:1.45;}
.kr-ae-flash-error{border:1px solid #fecaca;background:#fef2f2;color:#991b1b;}
.kr-ae-flash-success{border:1px solid #bbf7d0;background:#ecfdf5;color:#166534;}
.kr-ae-submit-row{margin:.9rem .12rem 0;}
.kr-ae-submit{
	border:0;
	border-radius:13px;
	background:linear-gradient(115deg,#0f172a,#1d273a);
	padding:.85rem clamp(26px,3vw,40px);
	color:#fff;font-weight:800;letter-spacing:.02em;text-transform:none;
	cursor:pointer;box-shadow:0 22px 50px rgba(15,23,42,.25);
	transition:filter .22s ease,transform .08s ease;
}
.kr-ae-submit:hover{filter:brightness(1.08);transform:translateY(-1px);}
.kr-ae-pass-field{
	margin:.72rem .12rem;
}
.kr-ae-pass-field > label{
	display:block;font-weight:600;font-size:.9rem;color:#475569;margin:0 0 .15rem;
}
.kr-ae-pass-wrap{
	position:relative;display:block;margin-top:0;width:100%;
}
.kr-ae-pass-wrap input{
	margin-top:0!important;
	padding-right:2.85rem!important;
	box-sizing:border-box;
}
.kr-ae-pass-toggle{
	position:absolute;right:.4rem;top:50%;transform:translateY(-50%);
	display:inline-flex;align-items:center;justify-content:center;
	width:2.35rem;height:2.15rem;padding:0;margin:0;border:0;border-radius:10px;
	background:transparent;color:#64748b;cursor:pointer;line-height:0;
	transition:color .15s ease,background .15s ease;
}
.kr-ae-pass-toggle:hover{color:#0f172a;background:rgba(15,23,42,.06);}
.kr-ae-pass-toggle:focus{outline:none;}
.kr-ae-pass-toggle:focus-visible{
	outline:2px solid #4338ca;outline-offset:2px;
}
.kr-ae-pass-toggle svg{display:block;pointer-events:none;}
.kr-ae-eye-off{display:none;}
.kr-ae-pass-toggle[aria-pressed="true"] .kr-ae-eye-open{display:none;}
.kr-ae-pass-toggle[aria-pressed="true"] .kr-ae-eye-off{display:block;}
.kr-ae-photo-row{display:flex;flex-wrap:wrap;align-items:center;gap:1.25rem;}
.kr-ae-photo-drop{
	width:100%;box-sizing:border-box;
	padding:.85rem 1rem;
	border:2px dashed #93c5fd;
	border-radius:16px;
	background:#f8fbff;
	transition:border-color .15s ease,background .15s ease,box-shadow .15s ease;
}
.kr-ae-photo-drop.is-dragover{
	border-color:#4338ca;
	background:#eef2ff;
	box-shadow:0 0 0 4px rgba(67,56,202,.12);
}
.kr-ae-photo-preview{width:148px;height:148px;object-fit:cover;border-radius:16px;border:1px solid #dbeafe;background:#fff;box-shadow:0 10px 24px rgba(15,23,42,.08);}
.kr-ae-photo-actions{display:flex;flex-direction:column;align-items:flex-start;gap:.55rem;}
.kr-ae-photo-btn{
	border:0;border-radius:13px;cursor:pointer;
	background:linear-gradient(115deg,#1d4ed8,#4338ca);
	padding:.75rem 1.2rem;color:#fff;font-weight:750;
	box-shadow:0 16px 32px rgba(67,56,202,.22);
}
.kr-ae-photo-btn:hover{filter:brightness(1.06);}
.kr-ae-photo-btn-remove{
	background:#fff;
	color:#991b1b;
	border:1px solid #fca5a5;
	box-shadow:none;
}
.kr-ae-photo-btn-remove:hover{background:#fef2f2;filter:none;}
.kr-ae-photo-btn-remove[hidden]{display:none !important;}
.kr-ae-photo-hint{margin:0;font-size:.88rem;color:#64748b;line-height:1.35;}
.kr-ae-photo-status{min-height:1.2em;font-size:.9rem;color:#334155;}
.kr-ae-photo-status.is-error{color:#991b1b;}
.kr-ae-photo-status.is-ok{color:#166534;}
</style>

<?php echo $error_box . $ok_box; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

<div class="kr-ae-stack">
	<strong style="letter-spacing:.01em;color:#475569;text-transform:uppercase;font-size:.78rem;">
		You are securely editing:&nbsp;<span style="color:#4338ca;"><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></span>
		<span class="kr-ae-chip">ID <?php echo (int) $id; ?></span>
	</strong>
	<a class="kr-ae-reset" href="<?php echo esc_url( $page_safe ); ?>" title="<?php esc_attr_e( 'Start a new lookup', 'default' ); ?>">⟵ Lookup different member</a>
</div>

<section class="kr-ae-group" id="kr-ae-photo-box">
	<h3>Member photo</h3>
	<p class="kr-ae-muted">
		Upload a picture into <code>member_images</code>. It is associated with this member immediately and appears in the User Directory.
		You can click the button or drag a photo onto the box below. Remove photo deletes the file and shows the placeholder image.
	</p>
	<div class="kr-ae-photo-row kr-ae-photo-drop" id="kr-ae-photo-drop">
		<img id="kr-ae-photo-preview" class="kr-ae-photo-preview" src="<?php echo esc_url( $photo_url ); ?>" alt="<?php echo esc_attr( $user->display_name ?: $user->user_login ); ?>">
		<div class="kr-ae-photo-actions">
			<input type="file" id="kr-ae-photo-file" accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif" hidden>
			<button type="button" class="kr-ae-photo-btn" id="kr-ae-photo-btn">Upload member photo</button>
			<button type="button" class="kr-ae-photo-btn kr-ae-photo-btn-remove" id="kr-ae-photo-remove"<?php echo $has_custom_photo ? '' : ' hidden'; ?>>Remove photo</button>
			<p class="kr-ae-photo-hint">Or drag a photo onto this box</p>
			<div id="kr-ae-photo-status" class="kr-ae-photo-status" aria-live="polite"></div>
		</div>
	</div>
</section>

<form class="kr-ae-fields" action="<?php echo esc_url( $post_url ); ?>" method="post" id="kr-ae-edit-form">

	<input type="hidden" name="action" value="kr_ae_save"/>
	<input type="hidden" name="kr_ae_return" value="<?php echo esc_attr( $page_safe ); ?>"/>
	<input type="hidden" name="kr_uid_hidden" value="<?php echo esc_attr( (string) $id ); ?>"/>

	<?php wp_nonce_field( 'kr_ae_save_' . $id, '_krsave' ); ?>
	<input type="hidden" name="_kraen_save" value="<?php echo esc_attr( wp_create_nonce( 'kr_ae_form_' . $id ) ); ?>" />

	<section class="kr-ae-group">
		<h3>Account basics</h3>
		<p class="kr-ae-muted">
			Default WordPress identifiers. Password resets do <strong>not</strong> require the member&apos;s existing password.
		</p>
		<div class="kr-ae-single kr-ae-muted">Username:&nbsp;<code><?php echo esc_html( $user->user_login ); ?></code>&nbsp;(read-only)</div>

		<label>Email&nbsp;<span class="kr-ae-required">*</span>
			<input type="email" name="user_email" id="kr_ae_email_live" autocomplete="email" required value="<?php echo esc_attr( $user->user_email ); ?>">
			<small style="margin-top:.3rem;display:block;color:#64748b;min-height:1.05em;line-height:1.35;">
				Status:&nbsp;<span id="kr_ae_email_ping" aria-live="polite"></span>
			</small>
		</label>

		<div class="kr-ae-pass-field">
			<label for="kr_ae_pass1">New password&nbsp;<small class="kr-ae-muted">(optional)</small></label>
			<span class="kr-ae-pass-wrap">
				<input type="password" name="pass1" id="kr_ae_pass1" maxlength="128" autocomplete="new-password" />
				<button type="button" class="kr-ae-pass-toggle" aria-label="<?php echo esc_attr__( 'Show password', 'default' ); ?>" aria-pressed="false">
					<svg class="kr-ae-eye-open" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>
						<circle cx="12" cy="12" r="3"/>
					</svg>
					<svg class="kr-ae-eye-off" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
						<path d="M10.73 5.08A10.4 10.4 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
						<path d="M6.61 6.61A13.5 13.5 0 0 0 2 12s3 7 10 7a9.7 9.7 0 0 0 5.39-1.61"/>
						<line x1="2" x2="22" y1="2" y2="22"/>
					</svg>
				</button>
			</span>
		</div>

		<div class="kr-ae-pass-field">
			<label for="kr_ae_pass2">Confirm new password&nbsp;<small class="kr-ae-muted">(repeat if changing)</small></label>
			<span class="kr-ae-pass-wrap">
				<input type="password" name="pass2" id="kr_ae_pass2" maxlength="128" autocomplete="new-password" />
				<button type="button" class="kr-ae-pass-toggle" aria-label="<?php echo esc_attr__( 'Show password', 'default' ); ?>" aria-pressed="false">
					<svg class="kr-ae-eye-open" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>
						<circle cx="12" cy="12" r="3"/>
					</svg>
					<svg class="kr-ae-eye-off" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
						<path d="M10.73 5.08A10.4 10.4 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
						<path d="M6.61 6.61A13.5 13.5 0 0 0 2 12s3 7 10 7a9.7 9.7 0 0 0 5.39-1.61"/>
						<line x1="2" x2="22" y1="2" y2="22"/>
					</svg>
				</button>
			</span>
		</div>
	</section>

	<section class="kr-ae-group">
		<h3>Structured name</h3>
		<label>
			Legal first name&nbsp;<span class="kr-ae-required">*</span>
			<input type="text" name="first_name" required maxlength="160" autocomplete="given-name"
				value="<?php echo esc_attr( $user->first_name ); ?>">
		</label>
		<label>
			Legal last name&nbsp;<span class="kr-ae-required">*</span>
			<input type="text" name="last_name" required maxlength="160" autocomplete="family-name"
				value="<?php echo esc_attr( $user->last_name ); ?>">
		</label>
	</section>

	<section class="kr-ae-group">
		<h3>Contact&nbsp;&amp;&nbsp;mailing footprint</h3>

		<p class="kr-ae-muted kr-ae-single" style="margin-top:0;">
			At least ONE phone MUST include all <strong>TEN DIGITS</strong>. Auto-dash formatting &middot;
			Use <abbr title="United States Postal abbreviation">two-letter USPS</abbr> state &middot;
			ZIP&nbsp;five digits ONLY.
		</p>

		<label>
			Land-line / home&nbsp;<small class="kr-ae-muted">###-###-####</small>
			<input type="text" name="home_phone" class="kr-ae-phone" maxlength="12" inputmode="numeric" autocomplete="tel" spellcheck="false"
				value="<?php echo esc_attr( kr_ae_phone_display( $m['home_phone'] ) ); ?>">
		</label>

		<label>
			Wireless / mobile&nbsp;<small class="kr-ae-muted">###-###-####</small>
			<input type="text" name="mobile_phone" class="kr-ae-phone" maxlength="12" inputmode="numeric" autocomplete="tel-mobile" spellcheck="false"
				value="<?php echo esc_attr( kr_ae_phone_display( $m['mobile_phone'] ) ); ?>">
		</label>

		<label class="kr-ae-single">
			Street&nbsp;<span class="kr-ae-required">*</span>&nbsp;(no PO Boxes unless club-approved)
			<input type="text" name="street_address" required maxlength="250" autocomplete="street-address"
				value="<?php echo esc_attr( $m['street_address'] ); ?>">
		</label>

		<label>
			Municipality / city&nbsp;<span class="kr-ae-required">*</span>
			<input type="text" name="city" required maxlength="120" autocomplete="address-level2"
				value="<?php echo esc_attr( $m['city'] ); ?>">
		</label>

		<label>
			State / province USPS&nbsp;<span class="kr-ae-required">*</span>
			<input type="text" name="state" id="kr_ae_state_up" maxlength="2" autocomplete="address-level1" style="text-transform: uppercase"
				value="<?php echo esc_attr( $m['state'] ); ?>">
			<span class="kr-ae-muted">
				UPPERCASE TWO LETTERS&nbsp;&mdash; e.g.&nbsp;<code>NC</code>
			</span>
		</label>

		<label>
			ZIP / postal&nbsp;<span class="kr-ae-required">*</span>
			<input type="text" name="zip" id="kr_ae_zip_digits" maxlength="5" autocomplete="postal-code"
				inputmode="numeric"
				value="<?php echo esc_attr( $m['zip'] ); ?>">
		</label>
	</section>

	<section class="kr-ae-group">
		<h3>Membership intelligence</h3>

		<label class="kr-ae-single">
			Spouse / partner&nbsp;<small class="kr-ae-muted">(optional&nbsp;&mdash;&nbsp;kitchen-table roster)</small>
			<input type="text" name="spouse" maxlength="200" autocomplete="off"
				value="<?php echo esc_attr( $m['spouse'] ); ?>">
		</label>

		<label>
			Kiwanian birth month&nbsp;<span class="kr-ae-required">*</span>
			<select name="birth_month" id="kr_ae_b_month" required>
				<option disabled value="">-- choose --</option>
				<?php echo $month_options_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</select>
		</label>

		<label>
			Birth DAY&nbsp;<span class="kr-ae-required">*</span>
			<select name="birth_day" id="kr_ae_b_day" required <?php disabled( $m['birth_month'], 0 ); ?>>

			</select>
			<span class="kr-ae-muted">February honours leap-day edge via 29 selectable days&nbsp;&mdash; adjust if tighter rule needed.</span>
		</label>

		<label class="kr-ae-single">
			Sponsoring Kiwanian / club referrer&nbsp;<small class="kr-ae-muted">(optional)</small>
			<input type="text" name="sponsor" maxlength="200" autocomplete="off"
				value="<?php echo esc_attr( $m['sponsor'] ); ?>">
		</label>

		<label class="kr-ae-single">
			Calendar year inducted into Kiwanis&nbsp;<span class="kr-ae-required">*</span>
			<input type="text" name="year_joined_kiwanis" maxlength="4" autocomplete="off" placeholder="yyyy"
				inputmode="numeric"
				value="<?php echo esc_attr( $m['year_joined_kiwanis'] ); ?>">
		</label>

		<div class="kr-ae-single kr-ae-muted" style="font-weight:bold;margin-top:.72rem;line-height:1.45;color:#4338ca;">
			Optional club recognition flags:&nbsp;(radio buttons propagate EXACTLY YES / NO literals into database)
			<hr style="opacity:.42;margin:.82rem auto;border:none;border-bottom:1px dashed #cfe3ff;">
		</div>

		<div class="kr-ae-single kr-ae-muted" style="font-weight:640;margin-bottom:.3rem;line-height:1.45;color:#4338ca;">
			HONORARY Lifetime recognition tier?
			<div class="kr-ae-flag-row" role="radiogroup" aria-label="Honorary member">
				<span><label><input type="radio" name="honorary_member" value="yes" <?php checked( 'yes', $m['honorary_member'] ); ?> required/> YES</label></span>
				<span><label><input type="radio" name="honorary_member" value="no" <?php checked( 'no', $m['honorary_member'] ); ?> /> NO</label></span>
			</div>
		</div>

		<div class="kr-ae-single kr-ae-muted" style="font-weight:640;margin:.75rem auto .46rem;line-height:1.45;color:#4338ca;">
			LIFE member under club constitution?
			<div class="kr-ae-flag-row" role="radiogroup" aria-label="Life member">
				<span><label><input type="radio" name="life_member" value="yes" <?php checked( 'yes', $m['life_member'] ); ?> required/> YES</label></span>
				<span><label><input type="radio" name="life_member" value="no" <?php checked( 'no', $m['life_member'] ); ?> /> NO</label></span>
			</div>
		</div>

		<div class="kr-ae-single kr-ae-muted" style="margin-top:.9rem;line-height:1.45;color:#4338ca;padding:clamp(12px,2vw,18px);border-radius:16px;background:linear-gradient(125deg,#eff6ff,#fefce8);border:1px solid #fcd34d;">
			<strong>Operational ENABLED flag</strong> &mdash; controls roster exports / dues automation.
			<strong>No</strong> also locks the WordPress login, signs that member out, and blocks password reset until you choose <strong>Yes</strong> again.
			Radios coerce user-meta&nbsp;<code>enabled</code> strictly to string&nbsp;<strong>1</strong> or&nbsp;<strong>0</strong>.

			<div class="kr-ae-flag-row" role="radiogroup" aria-label="Enabled roster flag" style="margin-top:.7rem;color:#92400e;">
				<span><label>
					<input type="radio" name="enabled_status" value="1" <?php checked( true, $m['enabled'] === '1' || $m['enabled'] === 1 ); ?> required/>
					YES&nbsp;&mdash; writes&nbsp;<strong>enabled = 1</strong>
				</label></span>
				<span><label>
					<input type="radio" name="enabled_status" value="0" <?php checked( true, ! ( $m['enabled'] === '1' || $m['enabled'] === 1 ) ); ?> />
					NO&nbsp;&mdash; writes&nbsp;<strong>enabled = 0</strong> and locks WordPress login
				</label></span>
			</div>

			<small style="margin-top:.6rem;display:inline-block;line-height:1.4;">
				Use <strong>No</strong> for suspension / bereavement placeholders / duplicate legacy imports until reconciled. The member cannot log in while this is No.<br/>
				Use <strong>Yes</strong> once dues &amp; background checks GREEN across committee workflow.
			</small>
		</div>
	</section>

	<p class="kr-ae-submit-row">
		<button class="kr-ae-submit" type="submit">&nbsp;Commit roster-safe profile save&nbsp;&#10003;</button>
	</p>
</form>

<script>
(function(){
	var root=document.getElementById('kr-ae-root');
	if(!root)return;
	function digitsOnly(raw){return (raw||'').replace(/\D/g,'');}
	function formatPhoneMasked(d){
		d=d||'';
		if(d.length<=3)return d;
		if(d.length<=6)return d.slice(0,3)+'-'+d.slice(3);
		return d.slice(0,3)+'-'+d.slice(3,6)+'-'+d.slice(6,10);
	}
	function caretAfterDigitCount(formatted,digitCount){
		digitCount=Math.max(0,Math.min(digitCount,10));
		if(digitCount===0)return 0;
		var n=0;
		for(var i=0;i<formatted.length;i++){
			if(/\d/.test(formatted.charAt(i))){
				n++;
				if(n===digitCount)return i+1;
			}
		}
		return formatted.length;
	}
	root.querySelectorAll('.kr-ae-phone').forEach(function(inp){
		function applyMask(){
			var sel=typeof inp.selectionStart==='number'?inp.selectionStart:0;
			var dlim=digitsOnly(inp.value).slice(0,10);
			var digitsLeft=digitsOnly(inp.value.slice(0,sel)).length;
			var dc=Math.min(digitsLeft,dlim.length);
			var formatted=formatPhoneMasked(dlim);
			inp.value=formatted;
			var pos=caretAfterDigitCount(formatted,dc);
			try{inp.setSelectionRange(pos,pos);}catch(e){}
		}
		inp.addEventListener('input',applyMask);
		inp.addEventListener('blur',applyMask);
		inp.addEventListener('keydown',function(e){
			var k=e.key||'';
			if(k==='Enter'||k==='Tab'||k==='Escape')return;
			if(k==='ArrowLeft'||k==='ArrowRight'||k==='ArrowUp'||k==='ArrowDown'||k==='Home'||k==='End'||k==='Backspace'||k==='Delete')return;
			if((e.ctrlKey||e.metaKey)&&(k==='a'||k==='c'||k==='v'||k==='x'||k==='A'||k==='C'||k==='V'||k==='X'))return;
			if(/^Numpad[0-9]$/.test(k))return;
			if(k.length===1 && /\d/.test(k))return;
			if(k.length===1)e.preventDefault();
		});
		applyMask();
	});
	var ST=root.querySelector('#kr_ae_state_up');
	if(ST){ ST.addEventListener('input',function(){ ST.value=ST.value.replace(/[^A-Za-z]/g,'').slice(0,2).toUpperCase(); }); }

	var zp=root.querySelector('#kr_ae_zip_digits');
	if(zp){ zp.addEventListener('input',function(){ zp.value = zp.value.replace(/\D/g,'').slice(0,5); }); }

	var yj=root.querySelector('input[name="year_joined_kiwanis"]');
	if(yj){ yj.addEventListener('input',function(){ yj.value = yj.value.replace(/\D/g,'').slice(0,4); }); }

	root.querySelectorAll('.kr-ae-pass-wrap').forEach(function(wrap){
		var inp = wrap.querySelector('input');
		var btn = wrap.querySelector('.kr-ae-pass-toggle');
		if(!inp || !btn){return;}
		function sync(){
			var showing = inp.type === 'text';
			btn.setAttribute('aria-pressed', showing ? 'true' : 'false');
			btn.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
		}
		function toggle(ev){
			if(ev){ ev.preventDefault(); ev.stopPropagation(); }
			inp.type = (inp.type === 'password') ? 'text' : 'password';
			sync();
		}
		btn.addEventListener('click', toggle);
		sync();
	});

	var ajaxUrl = root.dataset.ajax;
	var ajaxNonceMail = root.dataset.emailNonce;
	var memberIdVal = parseInt(root.dataset.memberId||'0',10);
	var savedDayInit = parseInt(root.dataset.savedBd||'1',10);
	var uploadNonce = root.dataset.uploadNonce;
	var photoBtn = document.getElementById('kr-ae-photo-btn');
	var photoRemove = document.getElementById('kr-ae-photo-remove');
	var photoFile = document.getElementById('kr-ae-photo-file');
	var photoPreview = document.getElementById('kr-ae-photo-preview');
	var photoStatus = document.getElementById('kr-ae-photo-status');
	var photoDrop = document.getElementById('kr-ae-photo-drop');
	function setPhotoRemoveVisible(show){
		if (!photoRemove) return;
		photoRemove.hidden = !show;
	}
	function setPhotoStatus(kind, text){
		if (!photoStatus) return;
		photoStatus.classList.remove('is-error','is-ok');
		if (kind === 'error') photoStatus.classList.add('is-error');
		if (kind === 'ok') photoStatus.classList.add('is-ok');
		photoStatus.textContent = text || '';
	}
	function isAllowedPhoto(file){
		if (!file) return false;
		var n = (file.name || '').toLowerCase();
		var t = (file.type || '').toLowerCase();
		if (t === 'image/jpeg' || t === 'image/png' || t === 'image/webp' || t === 'image/gif') return true;
		return /\.(jpe?g|png|webp|gif)$/.test(n);
	}
	function dtHasFiles(e){
		var types = e.dataTransfer && e.dataTransfer.types;
		if (!types) return false;
		for (var i = 0; i < types.length; i++) {
			if (types[i] === 'Files') return true;
		}
		return false;
	}
	function uploadMemberPhoto(file){
		if (!file) return;
		if (!isAllowedPhoto(file)) {
			setPhotoStatus('error', 'Use a JPG, PNG, WebP, or GIF image.');
			return;
		}
		if (file.size > 5 * 1024 * 1024) {
			setPhotoStatus('error', 'Image must be 5 MB or smaller.');
			return;
		}
		var fd = new FormData();
		fd.append('action', 'kr_ae_upload_member_image');
		fd.append('nonce', uploadNonce);
		fd.append('target_id', String(memberIdVal));
		fd.append('member_image', file);
		setPhotoStatus('', 'Uploading…');
		fetch(ajaxUrl, { method:'POST', credentials:'same-origin', body: fd })
			.then(function(r){ return r.json(); })
			.then(function(j){
				if (!j.success) {
					throw new Error(j.data && j.data.message ? j.data.message : 'Upload failed');
				}
				if (photoPreview && j.data && j.data.url) {
					photoPreview.src = j.data.url + (j.data.url.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
				}
				setPhotoRemoveVisible(true);
				setPhotoStatus('ok', (j.data && j.data.message) ? j.data.message : 'Photo saved.');
			})
			.catch(function(err){
				setPhotoStatus('error', err.message || 'Upload failed');
			})
			.finally(function(){ photoFile.value = ''; });
	}
	if (photoRemove && ajaxUrl && uploadNonce && memberIdVal) {
		photoRemove.addEventListener('click', function(){
			if (photoRemove.hidden) return;
			if (!window.confirm('Remove this member photo? The User Directory will show the placeholder image.')) return;
			var body = new URLSearchParams();
			body.set('action', 'kr_ae_remove_member_image');
			body.set('nonce', uploadNonce);
			body.set('target_id', String(memberIdVal));
			photoRemove.disabled = true;
			setPhotoStatus('', 'Removing photo…');
			fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			})
				.then(function(r){ return r.json(); })
				.then(function(j){
					if (!j.success) {
						throw new Error(j.data && j.data.message ? j.data.message : 'Could not remove photo');
					}
					if (photoPreview && j.data && j.data.url) {
						photoPreview.src = j.data.url + (j.data.url.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
					}
					setPhotoRemoveVisible(false);
					setPhotoStatus('ok', (j.data && j.data.message) ? j.data.message : 'Photo removed.');
				})
				.catch(function(err){
					setPhotoStatus('error', err.message || 'Could not remove photo');
				})
				.finally(function(){ photoRemove.disabled = false; });
		});
	}
	if (photoBtn && photoFile && ajaxUrl && uploadNonce && memberIdVal) {
		photoBtn.addEventListener('click', function(){ photoFile.click(); });
		photoFile.addEventListener('change', function(){
			if (!photoFile.files || !photoFile.files[0]) return;
			uploadMemberPhoto(photoFile.files[0]);
		});
		if (photoDrop) {
			var dragDepth = 0;
			photoDrop.addEventListener('dragenter', function(e){
				if (!dtHasFiles(e)) return;
				e.preventDefault();
				e.stopPropagation();
				dragDepth++;
				photoDrop.classList.add('is-dragover');
			});
			photoDrop.addEventListener('dragover', function(e){
				if (!dtHasFiles(e)) return;
				e.preventDefault();
				e.stopPropagation();
				if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
			});
			photoDrop.addEventListener('dragleave', function(e){
				e.preventDefault();
				dragDepth--;
				if (dragDepth <= 0) {
					dragDepth = 0;
					photoDrop.classList.remove('is-dragover');
				}
			});
			photoDrop.addEventListener('drop', function(e){
				e.preventDefault();
				e.stopPropagation();
				dragDepth = 0;
				photoDrop.classList.remove('is-dragover');
				var files = e.dataTransfer && e.dataTransfer.files;
				if (!files || !files[0]) return;
				uploadMemberPhoto(files[0]);
			});
		}
		root.addEventListener('dragover', function(e){
			if (!dtHasFiles(e)) return;
			e.preventDefault();
		});
		root.addEventListener('drop', function(e){
			if (!dtHasFiles(e)) return;
			e.preventDefault();
		});
	}

	function monthLength(m){
		m=parseInt(m,10);
		if(!m)return 31;
		if(m===2)return 29;
		if([4,6,9,11].indexOf(m)!==-1)return 30;
		return 31;
	}
	var bm=root.querySelector('#kr_ae_b_month');
	var bd=root.querySelector('#kr_ae_b_day');

	function repaintBirthDays(preserved){
		if(!bm||!bd)return;

		var m = parseInt(bm.value,10)||0;

		while(bd.firstChild){
			bd.removeChild(bd.firstChild);
		}

		if(!m){
			var ph=document.createElement('option');
			ph.value=''; ph.textContent='— pick month above —';
			bd.disabled=true;
			bd.appendChild(ph);
			return;
		}
		var maxDays=monthLength(m);
		bd.disabled=false;

		var pick = (preserved !== null && preserved !== undefined) ? preserved : (parseInt(bd.dataset.lastGood||savedDayInit,10)||1);
		for(var dd=1; dd<=maxDays; dd++){
			var opt=document.createElement('option');
			opt.value=String(dd); opt.textContent=String(dd);
			bd.appendChild(opt);
		}
		if(pick > maxDays) pick = maxDays;
		bd.selectedIndex=pick - 1;
		bd.dataset.lastGood=pick;

	}

	if(bm&&bd){
		bm.addEventListener('change',function(){
			delete bd.dataset.lastGood;
			repaintBirthDays(null);
		});
		repaintBirthDays(savedDayInit);
		bd.addEventListener('change',function(){ bd.dataset.lastGood=bd.value; });
	}


	var pingSpan=document.getElementById('kr_ae_email_ping');
	var emailInput=document.getElementById('kr_ae_email_live');
	if(emailInput&&pingSpan&&ajaxUrl&&memberIdVal){
		emailInput.addEventListener('blur',function(){
			var address=(emailInput.value||'').trim();
			pingSpan.textContent='';

			if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(address)){
				pingSpan.textContent='⚠ malformed email formatting';
				return;
			}
			var body=new URLSearchParams();
			body.append('action','kr_ae_check_email_admin');
			body.append('nonce', ajaxNonceMail);
			body.append('target_id', String(memberIdVal));
			body.append('email', address);

			pingSpan.textContent='… checking uniqueness …';

			fetch( ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:String(body)})
				.then(function(r){return r.json();})
				.then(function(j){
					if(!j.success){
						throw new Error(j.data && j.data.message ? j.data.message : 'Server denied email');
					}
					pingSpan.textContent=j.data.message || 'Looks good ✅';
				})
				.catch(function(err){
					pingSpan.textContent='⚠ '+ (err.message || 'offline / blocked');
				});
		});
	}


	var FORM=document.getElementById('kr-ae-edit-form');
	if(FORM){
		FORM.addEventListener('submit',function(ev){

			var inp1=FORM.querySelector('[name=\"pass1\"]');
			var inp2=FORM.querySelector('[name=\"pass2\"]');
			var p1=inp1?inp1.value.trim():'';
			var p2=inp2?inp2.value.trim():'';

			if( (p1||p2) && p1 !== p2 ){
				ev.preventDefault();alert('Confirmation password mismatched');return;
			}

			var hPh=FORM.querySelector('.kr-ae-phone[name=\"home_phone\"]');
			var mPh=FORM.querySelector('.kr-ae-phone[name=\"mobile_phone\"]');
			var dh=digitsOnly(hPh?hPh.value:'');
			var dm=digitsOnly(mPh?mPh.value:'');

			if( !dh.length && !dm.length ){
				ev.preventDefault();alert('Provide at LEAST ONE 10-digit US-style phone');
				return;
			}
			if(dh.length && dh.length!==10){
				ev.preventDefault();alert('Home phone malformed');return;
			}
			if(dm.length && dm.length!==10){
				ev.preventDefault();alert('Mobile phone malformed');return;
			}


			var stEl=FORM.querySelector('#kr_ae_state_up');
			var st=stEl?stEl.value.trim():'';
			if(!/^[A-Z]{2}$/.test(st)){
				ev.preventDefault();alert('STATE two letters USPS');return;
			}


			var z5El=FORM.querySelector('#kr_ae_zip_digits');
			var z5=z5El?z5El.value.trim():'';
			if(!/^\d{5}$/.test(z5)){
				ev.preventDefault();alert('ZIP must be five DIGITS ONLY');return;
			}


			var yEl=FORM.querySelector('input[name=\"year_joined_kiwanis\"]');
			var y=yEl?yEl.value.trim():'';
			if(!/^\d{4}$/.test(y)){
				ev.preventDefault();alert('Year inducted must look like 1998');return;
			}



		});
	}
})();
</script>

<?php echo '</div>'; return ob_get_clean(); }

/**
 * Shortcode ENTRY -----------------------------------------------------------
 */
function kr_ae_shortcode_routine() {

	if ( ! current_user_can( 'edit_users' ) ) {

		return '<p class="kr-ae-deny">You lack <code>edit_users</code> capability required for delegated roster remediation.</p><style>.kr-ae-deny{font-family:system-ui;padding:35px;color:#92400e;}</style>';
	}

	$route_error_banner = kr_ae_get_request_error_message();

	$new_flag_ok = '';
	if ( isset( $_GET['kr_ok'] ) && '1' === $_GET['kr_ok'] ) {
		$new_flag_ok = 'Profile saved cleanly — thank you for housekeeping member records.';
	}

	$url_target_candidate = isset( $_GET['kr_m'] ) ? absint( $_GET['kr_m'] ) : 0;
	$url_nonce_guard      = isset( $_GET['_kraen'] ) ? sanitize_text_field( wp_unslash( $_GET['_kraen'] ) ) : '';

	if ( ! $route_error_banner ) {

		unset( $_GET['kr_ae_error'], $_REQUEST['kr_ae_error'], $_REQUEST['kr_ce'] );
		delete_transient( 'kr_ae_banner_suppress_optional' );

	}

	if (
		$url_target_candidate
		&& $url_nonce_guard
		&& wp_verify_nonce( $url_nonce_guard, 'kr_ae_form_' . $url_target_candidate )
	) {

		if ( user_can_edit_user( get_current_user_id(), $url_target_candidate ) ) {

			$member_object = get_userdata( $url_target_candidate );

			if ( $member_object instanceof WP_User ) {

				return kr_ae_render_editor_markup( $member_object, $route_error_banner, $new_flag_ok );

			}
		}
	}

	return kr_ae_render_lookup_markup( $route_error_banner );
}

add_shortcode( 'kiwanis_admin_edit_profile', 'kr_ae_shortcode_routine' );
