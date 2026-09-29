<?php
/**
 * Kiwanis profile editor (logged-in users).
 * Shortcode: [kiwanis_edit_profile]
 *
 * Shares kr_normalize_phone / kr_validate_phone_field with the registration snippet
 * when both are active (no redeclare fatal).
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
		$trim = trim( (string) $raw );
		if ( $trim === '' ) {
			return '';
		}
		$normalized = kr_normalize_phone( $trim );
		if ( $normalized === '' ) {
			return new WP_Error( 'bad_phone', 'Phone numbers must be 10 digits in the format ###-###-####.' );
		}
		return $normalized;
	}
}

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
 * AJAX: logged-in member uploads their own photo only.
 */
add_action(
	'wp_ajax_kr_ep_upload_member_image',
	static function () {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in to upload a photo.' ) );
		}

		$target_id = get_current_user_id();
		if ( $target_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Your account could not be loaded.' ) );
		}

		check_ajax_referer( 'kr_ep_upload_' . $target_id, 'nonce' );

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
				'message'  => 'Photo uploaded. It will appear in the User Directory.',
			)
		);
	}
);

function kr_ajax_check_edit_profile_field() {
	if ( ! is_user_logged_in() ) {
		wp_send_json(
			array(
				'available' => false,
				'message'   => 'You are not logged in.',
			)
		);
	}

	check_ajax_referer( 'kr_edit_ajax', 'nonce' );

	$current_id = get_current_user_id();
	$user_obj   = get_userdata( $current_id );
	if ( ! $user_obj ) {
		wp_send_json(
			array(
				'available' => false,
				'message'   => 'Invalid user.',
			)
		);
	}

	$field = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : '';
	$value = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';

	if ( 'email' !== $field ) {
		wp_send_json(
			array(
				'available' => false,
				'message'   => 'Invalid request.',
			)
		);
	}

	$e = sanitize_email( $value );

	if ( '' === $e ) {
		wp_send_json(
			array(
				'available' => false,
				'message'   => 'Please enter an email address.',
			)
		);
	}

	if ( ! is_email( $e ) ) {
		wp_send_json(
			array(
				'available' => false,
				'message'   => 'Please enter a valid email address.',
			)
		);
	}

	if ( strtolower( $e ) === strtolower( $user_obj->user_email ) ) {
		wp_send_json(
			array(
				'available' => true,
				'message'   => 'This is your current email.',
			)
		);
	}

	$existing = email_exists( $e );
	if ( $existing && (int) $existing !== $current_id ) {
		wp_send_json(
			array(
				'available' => false,
				'message'   => 'That email address is already registered.',
			)
		);
	}

	wp_send_json(
		array(
			'available' => true,
			'message'   => 'Email is available.',
		)
	);
}

add_action( 'wp_ajax_kr_check_edit_profile_field', 'kr_ajax_check_edit_profile_field' );

function kr_edit_profile_redirect_error( $message, $redirect_base = '' ) {
	$base = $redirect_base ? $redirect_base : home_url( '/' );
	wp_safe_redirect(
		add_query_arg(
			array(
				'profile' => 'error',
				'kr_err'  => rawurlencode( $message ),
			),
			$base
		)
	);
	exit;
}

function kr_render_edit_profile_form() {
	if ( ! is_user_logged_in() ) {
		return '<p class="kr-profile-login-notice">You must be logged in to edit your profile.</p>';
	}

	$user_id = get_current_user_id();
	$user    = get_userdata( $user_id );
	if ( ! $user ) {
		return '<p class="kr-profile-login-notice">Your account could not be loaded.</p>';
	}

	$notice = '';
	if ( isset( $_GET['profile'] ) ) {
		if ( 'success' === $_GET['profile'] ) {
			$notice = '<div class="kr-notice kr-notice-success">Profile updated.</div>';
		} elseif ( 'error' === $_GET['profile'] && ! empty( $_GET['kr_err'] ) ) {
			$notice = '<div class="kr-notice kr-notice-error">' . esc_html( rawurldecode( (string) $_GET['kr_err'] ) ) . '</div>';
		}
	}

	$meta = array(
		'home_phone'          => get_user_meta( $user_id, 'home_phone', true ),
		'mobile_phone'        => get_user_meta( $user_id, 'mobile_phone', true ),
		'street_address'      => get_user_meta( $user_id, 'street_address', true ),
		'city'                => get_user_meta( $user_id, 'city', true ),
		'state'               => get_user_meta( $user_id, 'state', true ),
		'zip'                 => get_user_meta( $user_id, 'zip', true ),
		'spouse'              => get_user_meta( $user_id, 'spouse', true ),
		'birth_month'         => (int) get_user_meta( $user_id, 'birth_month', true ),
		'birth_day'           => (int) get_user_meta( $user_id, 'birth_day', true ),
		'sponsor'             => get_user_meta( $user_id, 'sponsor', true ),
		'year_joined_kiwanis' => get_user_meta( $user_id, 'year_joined_kiwanis', true ),
		'honorary_member'     => get_user_meta( $user_id, 'honorary_member', true ),
		'life_member'         => get_user_meta( $user_id, 'life_member', true ),
		'enabled'             => get_user_meta( $user_id, 'enabled', true ),
	);

	if ( '' === $meta['enabled'] ) {
		$meta['enabled'] = '1';
	}

	$hon_yes  = ( 'yes' === $meta['honorary_member'] );
	$hon_no   = ( 'no' === $meta['honorary_member'] );
	$life_yes = ( 'yes' === $meta['life_member'] );
	$life_no  = ( 'no' === $meta['life_member'] );
	if ( ! $hon_yes && ! $hon_no ) {
		$hon_no = true;
	}
	if ( ! $life_yes && ! $life_no ) {
		$life_no = true;
	}

	ob_start();
	$uid          = 'kr-edit-' . wp_unique_id();
	$nonce        = wp_create_nonce( 'kr_edit_ajax' );
	$ajax         = esc_url( admin_url( 'admin-ajax.php' ) );
	$photo_url    = mhk_member_image_url( $user_id );
	$upload_nonce = wp_create_nonce( 'kr_ep_upload_' . $user_id );

	echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	$action_url = esc_url( admin_url( 'admin-post.php' ) );
	?>
	<div
		class="kr-reg-wrap kr-edit-wrap"
		id="<?php echo esc_attr( $uid ); ?>"
		data-nonce="<?php echo esc_attr( $nonce ); ?>"
		data-ajaxurl="<?php echo esc_attr( $ajax ); ?>"
		data-upload-nonce="<?php echo esc_attr( $upload_nonce ); ?>"
	>
	<fieldset class="kr-photo-box">
		<legend>My photo</legend>
		<p class="kr-field-hint">Upload a picture for the User Directory. You can click the button or drag a photo onto the box. It is saved immediately — you do not need to click Save profile.</p>
		<div class="kr-photo-row kr-photo-drop" id="kr-ep-photo-drop">
			<img id="kr-ep-photo-preview" class="kr-photo-preview" src="<?php echo esc_url( $photo_url ); ?>" alt="<?php echo esc_attr( $user->display_name ?: $user->user_login ); ?>">
			<div class="kr-photo-actions">
				<input type="file" id="kr-ep-photo-file" accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif" hidden>
				<button type="button" class="kr-photo-btn" id="kr-ep-photo-btn">Upload my photo</button>
				<p class="kr-photo-hint">Or drag a photo onto this box</p>
				<div id="kr-ep-photo-status" class="kr-photo-status" aria-live="polite"></div>
			</div>
		</div>
	</fieldset>
	<form class="kiwanis-edit-form" method="post" action="<?php echo $action_url; ?>">
		<input type="hidden" name="action" value="kr_edit_profile" />
		<?php wp_nonce_field( 'kr_edit_profile', 'kr_edit_nonce' ); ?>
		<?php wp_referer_field( true ); ?>
		<input type="hidden" name="enabled" value="1" />

		<fieldset>
			<legend>Account</legend>
			<p>
				<label for="kr_edit_user_login">Username</label><br />
				<input type="text" id="kr_edit_user_login" value="<?php echo esc_attr( $user->user_login ); ?>" readonly class="kr-readonly" />
				<span class="kr-field-hint">Usernames cannot be changed here.</span>
			</p>
			<p>
				<label for="kr_edit_user_email">Email <span class="req">*</span></label><br />
				<input type="email" name="user_email" id="kr_edit_user_email" required autocomplete="email" value="<?php echo esc_attr( $user->user_email ); ?>" />
				<span class="kr-field-status" id="kr_edit_user_email_status" aria-live="polite"></span>
			</p>

			<p class="kr-password-field">
				<label for="kr_edit_current_pass">Current password <span class="kr-edit-pass-hint">(required only if changing password)</span></label>
				<span class="kr-password-wrap">
					<input type="password" name="current_password" id="kr_edit_current_pass" autocomplete="current-password" />
					<button type="button" class="kr-pass-toggle" aria-pressed="false" aria-label="Show password" title="Show password">
						<span class="kr-pass-toggle-icon kr-icon-show" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
						</span>
						<span class="kr-pass-toggle-icon kr-icon-hide" aria-hidden="true" hidden>
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
						</span>
					</button>
				</span>
			</p>

			<p class="kr-password-field">
				<label for="kr_edit_user_pass">New password</label>
				<span class="kr-password-wrap">
					<input type="password" name="user_pass" id="kr_edit_user_pass" autocomplete="new-password" />
					<button type="button" class="kr-pass-toggle" aria-pressed="false" aria-label="Show password" title="Show password">
						<span class="kr-pass-toggle-icon kr-icon-show" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
						</span>
						<span class="kr-pass-toggle-icon kr-icon-hide" aria-hidden="true" hidden>
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
						</span>
					</button>
				</span>
			</p>
			<p class="kr-password-field">
				<label for="kr_edit_user_pass2">Confirm new password</label>
				<span class="kr-password-wrap">
					<input type="password" name="user_pass2" id="kr_edit_user_pass2" autocomplete="new-password" />
					<button type="button" class="kr-pass-toggle" aria-pressed="false" aria-label="Show password" title="Show password">
						<span class="kr-pass-toggle-icon kr-icon-show" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
						</span>
						<span class="kr-pass-toggle-icon kr-icon-hide" aria-hidden="true" hidden>
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
						</span>
					</button>
				</span>
			</p>
		</fieldset>

		<fieldset>
			<legend>Name</legend>
			<p>
				<label for="kr_edit_first_name">First name <span class="req">*</span></label><br />
				<input type="text" name="first_name" id="kr_edit_first_name" required value="<?php echo esc_attr( $user->first_name ); ?>" />
			</p>
			<p>
				<label for="kr_edit_last_name">Last name <span class="req">*</span></label><br />
				<input type="text" name="last_name" id="kr_edit_last_name" required value="<?php echo esc_attr( $user->last_name ); ?>" />
			</p>
		</fieldset>

		<fieldset>
			<legend>Contact</legend>
			<p class="kr-phone-hint">Provide at least one phone number <span class="req">*</span></p>
			<p>
				<label for="kr_edit_home_phone">Home phone (###-###-####)</label><br />
				<input type="text" name="home_phone" id="kr_edit_home_phone" inputmode="numeric" placeholder="555-123-4567" maxlength="12" autocomplete="tel" value="<?php echo esc_attr( $meta['home_phone'] ); ?>" />
			</p>
			<p>
				<label for="kr_edit_mobile_phone">Mobile phone (###-###-####)</label><br />
				<input type="text" name="mobile_phone" id="kr_edit_mobile_phone" inputmode="numeric" placeholder="555-987-6543" maxlength="12" autocomplete="tel" value="<?php echo esc_attr( $meta['mobile_phone'] ); ?>" />
			</p>
			<p>
				<label for="kr_edit_street_address">Street address <span class="req">*</span></label><br />
				<input type="text" name="street_address" id="kr_edit_street_address" required value="<?php echo esc_attr( $meta['street_address'] ); ?>" />
			</p>
			<p>
				<label for="kr_edit_city">City <span class="req">*</span></label><br />
				<input type="text" name="city" id="kr_edit_city" required value="<?php echo esc_attr( $meta['city'] ); ?>" />
			</p>
			<p>
				<label for="kr_edit_state">State (2 letters) <span class="req">*</span></label><br />
				<input type="text" name="state" id="kr_edit_state" maxlength="2" style="text-transform:uppercase" autocomplete="address-level1" required value="<?php echo esc_attr( $meta['state'] ); ?>" />
			</p>
			<p>
				<label for="kr_edit_zip">ZIP (#####) <span class="req">*</span></label><br />
				<input type="text" name="zip" id="kr_edit_zip" maxlength="5" inputmode="numeric" required value="<?php echo esc_attr( $meta['zip'] ); ?>" />
			</p>
		</fieldset>

		<fieldset>
			<legend>Profile</legend>
			<p>
				<label for="kr_edit_spouse">Spouse</label><br />
				<input type="text" name="spouse" id="kr_edit_spouse" value="<?php echo esc_attr( $meta['spouse'] ); ?>" />
			</p>

			<p>
				<label for="kr_edit_birth_month">Birth month <span class="req">*</span></label><br />
				<select name="birth_month" id="kr_edit_birth_month" required>
					<option value="">— Select —</option>
					<?php
					$months = array(
						1  => 'Jan',
						2  => 'Feb',
						3  => 'Mar',
						4  => 'Apr',
						5  => 'May',
						6  => 'June',
						7  => 'July',
						8  => 'Aug',
						9  => 'Sept',
						10 => 'Oct',
						11 => 'Nov',
						12 => 'Dec',
					);
					foreach ( $months as $num => $label ) {
						printf(
							'<option value="%d"%s>%s</option>',
							esc_attr( (string) $num ),
							selected( $meta['birth_month'], $num, false ),
							esc_html( $label )
						);
					}
					?>
				</select>
			</p>
			<p>
				<label for="kr_edit_birth_day">Birth day <span class="req">*</span></label><br />
				<select name="birth_day" id="kr_edit_birth_day" required <?php echo $meta['birth_month'] ? '' : 'disabled'; ?>>
					<?php if ( ! $meta['birth_month'] ) : ?>
						<option value="">— Select month first —</option>
					<?php endif; ?>
				</select>
			</p>

			<p>
				<label for="kr_edit_sponsor">Sponsor</label><br />
				<input type="text" name="sponsor" id="kr_edit_sponsor" value="<?php echo esc_attr( $meta['sponsor'] ); ?>" />
			</p>
			<p>
				<label for="kr_edit_year_joined_kiwanis">Year joined Kiwanis (####) <span class="req">*</span></label><br />
				<input type="text" name="year_joined_kiwanis" id="kr_edit_year_joined_kiwanis" maxlength="4" inputmode="numeric" required value="<?php echo esc_attr( $meta['year_joined_kiwanis'] ); ?>" />
			</p>

			<fieldset class="kr-radio-group">
				<legend>Honorary member <span class="req">*</span></legend>
				<label><input type="radio" name="honorary_member" value="yes" <?php checked( $hon_yes ); ?> required /> Yes</label>
				<label><input type="radio" name="honorary_member" value="no" <?php checked( $hon_no ); ?> /> No</label>
			</fieldset>

			<fieldset class="kr-radio-group">
				<legend>Life member <span class="req">*</span></legend>
				<label><input type="radio" name="life_member" value="yes" <?php checked( $life_yes ); ?> required /> Yes</label>
				<label><input type="radio" name="life_member" value="no" <?php checked( $life_no ); ?> /> No</label>
			</fieldset>
		</fieldset>

		<p><button type="submit" class="kr-submit">Save profile</button></p>
	</form>

	<style>
		.oxy-shortcode.my-register-shortcode {
			width: 100%;
			max-width: 100%;
			margin-left: auto;
			margin-right: auto;
			box-sizing: border-box;
		}
		.kr-edit-wrap {
			width: 100%;
			max-width: 560px;
			margin-left: auto;
			margin-right: auto;
			padding-left: 0.75rem;
			padding-right: 0.75rem;
			box-sizing: border-box;
		}
		.kr-edit-wrap .kiwanis-edit-form {
			width: 100%;
			max-width: 100%;
			margin-left: auto;
			margin-right: auto;
			box-sizing: border-box;
		}
		.kr-edit-wrap fieldset {
			min-width: 0;
			max-width: 100%;
			margin: 1rem 0;
			padding: 1rem;
			border: 1px solid #ccc;
			box-sizing: border-box;
		}
		.kr-edit-wrap input[type="text"],
		.kr-edit-wrap input[type="email"],
		.kr-edit-wrap input[type="password"],
		.kr-edit-wrap select {
			width: 100%;
			max-width: 100%;
			box-sizing: border-box;
		}
		.kr-edit-wrap legend { font-weight: 600; padding: 0 .35rem; }
		.kr-edit-wrap .kr-radio-group label { margin-right: 1rem; }
		.kr-edit-wrap .req { color: #b91c1c; }
		.kr-edit-wrap .kr-phone-hint { font-size: 0.9rem; color: #334155; margin-bottom: 0.25rem; }
		.kr-edit-wrap .kr-readonly { background: #f8fafc; color: #475569; }
		.kr-edit-wrap .kr-field-hint { display: block; font-size: 0.85rem; color: #64748b; margin-top: 0.25rem; }
		.kr-edit-wrap .kr-edit-pass-hint { font-size: 0.85rem; font-weight: normal; color: #64748b; }
		.kr-notice { padding: .75rem 1rem; margin: 1rem 0; border-radius: 6px; }
		.kr-notice-success { background: #ecfdf5; border: 1px solid #6ee7b7; }
		.kr-notice-error { background: #fef2f2; border: 1px solid #fca5a5; }
		.kr-submit { padding: .5rem 1rem; }

		.kr-edit-wrap .kr-field-status {
			display: block;
			font-size: 0.85rem;
			margin-top: 0.25rem;
			min-height: 1.2em;
		}
		.kr-edit-wrap .kr-field-status.kr-ok { color: #15803d; }
		.kr-edit-wrap .kr-field-status.kr-bad { color: #b91c1c; }
		.kr-edit-wrap .kr-field-status.kr-info { color: #64748b; }

		.kr-edit-wrap .kr-password-field label { display: block; margin-bottom: 0.25rem; }
		.kr-edit-wrap .kr-password-wrap {
			position: relative;
			display: block;
			width: 100%;
			max-width: 100%;
		}
		.kr-edit-wrap .kr-password-wrap input[type="password"],
		.kr-edit-wrap .kr-password-wrap input[type="text"] {
			width: 100%;
			box-sizing: border-box;
			padding: 0.4rem 2.75rem 0.4rem 0.5rem;
			max-width: 100%;
		}
		.kr-edit-wrap .kr-pass-toggle {
			position: absolute;
			right: 6px;
			top: 50%;
			transform: translateY(-50%);
			display: flex;
			align-items: center;
			justify-content: center;
			width: 2.25rem;
			height: 2.25rem;
			padding: 0;
			margin: 0;
			border: none;
			border-radius: 6px;
			background: transparent;
			color: #475569;
			cursor: pointer;
			line-height: 0;
		}
		.kr-edit-wrap .kr-pass-toggle:hover,
		.kr-edit-wrap .kr-pass-toggle:focus-visible {
			background: #f1f5f9;
			color: #0f172a;
			outline: none;
		}
		.kr-edit-wrap .kr-pass-toggle:focus-visible {
			box-shadow: 0 0 0 2px #fff, 0 0 0 4px #94a3b8;
		}
		.kr-edit-wrap .kr-pass-toggle svg { display: block; }
		.kr-edit-wrap .kr-pass-toggle-icon[hidden] { display: none !important; }
		.kr-profile-login-notice { padding: 1rem; border: 1px solid #fca5a5; background: #fef2f2; border-radius: 6px; }
		.kr-edit-wrap .kr-photo-box { width: 100%; max-width: 100%; margin: 1rem auto; padding: 1rem; border: 1px solid #ccc; box-sizing: border-box; }
		.kr-edit-wrap .kr-photo-row { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; }
		.kr-edit-wrap .kr-photo-drop {
			width: 100%;
			box-sizing: border-box;
			padding: 0.75rem 0.9rem;
			border: 2px dashed #93c5fd;
			border-radius: 12px;
			background: #f8fbff;
			transition: border-color .15s ease, background .15s ease, box-shadow .15s ease;
		}
		.kr-edit-wrap .kr-photo-drop.is-dragover {
			border-color: #1d4ed8;
			background: #eef2ff;
			box-shadow: 0 0 0 4px rgba(29,78,216,.12);
		}
		.kr-edit-wrap .kr-photo-preview { width: 148px; height: 148px; object-fit: cover; border-radius: 12px; border: 1px solid #cbd5e1; background: #fff; }
		.kr-edit-wrap .kr-photo-actions { display: flex; flex-direction: column; align-items: flex-start; gap: 0.5rem; }
		.kr-edit-wrap .kr-photo-btn {
			border: 0;
			border-radius: 8px;
			cursor: pointer;
			background: #1d4ed8;
			padding: 0.6rem 1rem;
			color: #fff;
			font-weight: 700;
		}
		.kr-edit-wrap .kr-photo-btn:hover { filter: brightness(1.06); }
		.kr-edit-wrap .kr-photo-hint { margin: 0; font-size: 0.85rem; color: #64748b; line-height: 1.35; }
		.kr-edit-wrap .kr-photo-status { min-height: 1.2em; font-size: 0.9rem; color: #334155; }
		.kr-edit-wrap .kr-photo-status.is-error { color: #b91c1c; }
		.kr-edit-wrap .kr-photo-status.is-ok { color: #15803d; }
	</style>

	<script>
	(function () {
		var root = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
		if (!root) return;

		var ajaxUrl = root.getAttribute('data-ajaxurl');
		var nonce = root.getAttribute('data-nonce');
		var uploadNonce = root.getAttribute('data-upload-nonce');
		var savedBirthDay = <?php echo (int) $meta['birth_day']; ?>;

		var photoBtn = document.getElementById('kr-ep-photo-btn');
		var photoFile = document.getElementById('kr-ep-photo-file');
		var photoPreview = document.getElementById('kr-ep-photo-preview');
		var photoStatus = document.getElementById('kr-ep-photo-status');
		var photoDrop = document.getElementById('kr-ep-photo-drop');
		function setPhotoStatus(kind, text) {
			if (!photoStatus) return;
			photoStatus.classList.remove('is-error', 'is-ok');
			if (kind === 'error') photoStatus.classList.add('is-error');
			if (kind === 'ok') photoStatus.classList.add('is-ok');
			photoStatus.textContent = text || '';
		}
		function isAllowedPhoto(file) {
			if (!file) return false;
			var n = (file.name || '').toLowerCase();
			var t = (file.type || '').toLowerCase();
			if (t === 'image/jpeg' || t === 'image/png' || t === 'image/webp' || t === 'image/gif') return true;
			return /\.(jpe?g|png|webp|gif)$/.test(n);
		}
		function dtHasFiles(e) {
			var types = e.dataTransfer && e.dataTransfer.types;
			if (!types) return false;
			for (var i = 0; i < types.length; i++) {
				if (types[i] === 'Files') return true;
			}
			return false;
		}
		function uploadMemberPhoto(file) {
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
			fd.append('action', 'kr_ep_upload_member_image');
			fd.append('nonce', uploadNonce);
			fd.append('member_image', file);
			setPhotoStatus('', 'Uploading…');
			fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					if (!j.success) {
						throw new Error(j.data && j.data.message ? j.data.message : 'Upload failed');
					}
					if (photoPreview && j.data && j.data.url) {
						photoPreview.src = j.data.url + (j.data.url.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
					}
					setPhotoStatus('ok', (j.data && j.data.message) ? j.data.message : 'Photo saved.');
				})
				.catch(function (err) {
					setPhotoStatus('error', err.message || 'Upload failed');
				})
				.finally(function () { photoFile.value = ''; });
		}
		if (photoBtn && photoFile && ajaxUrl && uploadNonce) {
			photoBtn.addEventListener('click', function () { photoFile.click(); });
			photoFile.addEventListener('change', function () {
				if (!photoFile.files || !photoFile.files[0]) return;
				uploadMemberPhoto(photoFile.files[0]);
			});
			if (photoDrop) {
				var dragDepth = 0;
				photoDrop.addEventListener('dragenter', function (e) {
					if (!dtHasFiles(e)) return;
					e.preventDefault();
					e.stopPropagation();
					dragDepth++;
					photoDrop.classList.add('is-dragover');
				});
				photoDrop.addEventListener('dragover', function (e) {
					if (!dtHasFiles(e)) return;
					e.preventDefault();
					e.stopPropagation();
					if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
				});
				photoDrop.addEventListener('dragleave', function (e) {
					e.preventDefault();
					dragDepth--;
					if (dragDepth <= 0) {
						dragDepth = 0;
						photoDrop.classList.remove('is-dragover');
					}
				});
				photoDrop.addEventListener('drop', function (e) {
					e.preventDefault();
					e.stopPropagation();
					dragDepth = 0;
					photoDrop.classList.remove('is-dragover');
					var files = e.dataTransfer && e.dataTransfer.files;
					if (!files || !files[0]) return;
					uploadMemberPhoto(files[0]);
				});
			}
			root.addEventListener('dragover', function (e) {
				if (!dtHasFiles(e)) return;
				e.preventDefault();
			});
			root.addEventListener('drop', function (e) {
				if (!dtHasFiles(e)) return;
				e.preventDefault();
			});
		}

		var showLabel = 'Show password';
		var hideLabel = 'Hide password';

		function setStatus(el, type, text) {
			el.textContent = text || '';
			el.className = 'kr-field-status';
			if (!text) return;
			if (type === 'ok') el.classList.add('kr-ok');
			else if (type === 'bad') el.classList.add('kr-bad');
			else el.classList.add('kr-info');
		}

		function checkEditEmail() {
			var input = root.querySelector('#kr_edit_user_email');
			var statusEl = root.querySelector('#kr_edit_user_email_status');
			var raw = (input.value || '').trim();
			setStatus(statusEl, '', '');
			if (!raw) return;

			if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(raw)) {
				setStatus(statusEl, 'bad', 'Please enter a valid email address.');
				return;
			}

			setStatus(statusEl, 'info', 'Checking…');

			var fd = new FormData();
			fd.append('action', 'kr_check_edit_profile_field');
			fd.append('nonce', nonce);
			fd.append('field', 'email');
			fd.append('value', raw);

			fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (!data || typeof data.available === 'undefined') {
					setStatus(statusEl, 'bad', 'Could not verify. Try again.');
					return;
				}
				if (data.available) {
					setStatus(statusEl, 'ok', data.message || 'OK.');
				} else {
					setStatus(statusEl, 'bad', data.message || 'Not available.');
				}
			})
			.catch(function () {
				setStatus(statusEl, 'bad', 'Could not verify. Check your connection.');
			});
		}

		var emailInput = root.querySelector('#kr_edit_user_email');
		var emailStatus = root.querySelector('#kr_edit_user_email_status');
		if (emailInput && emailStatus) {
			emailInput.addEventListener('blur', checkEditEmail);
			emailInput.addEventListener('input', function () { setStatus(emailStatus, '', ''); });
		}

		function wirePasswordToggle(wrap) {
			var input = wrap.querySelector('input');
			var btn = wrap.querySelector('.kr-pass-toggle');
			if (!input || !btn) return;
			var showIcon = btn.querySelector('.kr-icon-show');
			var hideIcon = btn.querySelector('.kr-icon-hide');

			function setRevealed(revealed) {
				input.type = revealed ? 'text' : 'password';
				btn.setAttribute('aria-pressed', revealed ? 'true' : 'false');
				btn.setAttribute('aria-label', revealed ? hideLabel : showLabel);
				btn.setAttribute('title', revealed ? hideLabel : showLabel);
				if (showIcon && hideIcon) {
					showIcon.hidden = revealed;
					hideIcon.hidden = !revealed;
				}
			}

			btn.addEventListener('click', function () {
				setRevealed(input.type === 'password');
			});
			setRevealed(false);
		}
		root.querySelectorAll('.kr-password-wrap').forEach(wirePasswordToggle);

		var monthEl = root.querySelector('#kr_edit_birth_month');
		var dayEl = root.querySelector('#kr_edit_birth_day');
		function daysInMonth(m) {
			m = parseInt(m, 10);
			if (m === 2) return 29;
			if ([4, 6, 9, 11].indexOf(m) !== -1) return 30;
			return 31;
		}
		function rebuildDays(preserveDay) {
			var m = monthEl.value;
			var previous = preserveDay != null ? String(preserveDay) : dayEl.value;
			dayEl.innerHTML = '';
			if (!m) {
				dayEl.disabled = true;
				var opt0 = document.createElement('option');
				opt0.value = '';
				opt0.textContent = '— Select month first —';
				dayEl.appendChild(opt0);
				return;
			}
			dayEl.disabled = false;
			var max = daysInMonth(parseInt(m, 10));
			for (var d = 1; d <= max; d++) {
				var opt = document.createElement('option');
				opt.value = String(d);
				opt.textContent = String(d);
				dayEl.appendChild(opt);
			}
			var use = previous;
			if (savedBirthDay && (!use || parseInt(use, 10) > max)) {
				use = String(Math.min(savedBirthDay, max));
			}
			if (use && parseInt(use, 10) <= max) {
				dayEl.value = use;
			}
		}
		monthEl.addEventListener('change', function () { rebuildDays(); });
		if (monthEl.value) {
			rebuildDays(savedBirthDay || null);
		} else {
			rebuildDays();
		}

		function digitsOnly(str) { return (str || '').replace(/\D/g, '').slice(0, 10); }
		function formatPhone(d) {
			if (d.length <= 3) return d;
			if (d.length <= 6) return d.slice(0, 3) + '-' + d.slice(3);
			return d.slice(0, 3) + '-' + d.slice(3, 6) + '-' + d.slice(6);
		}
		function wirePhone(id) {
			var el = root.querySelector('#' + id);
			if (!el) return;
			el.addEventListener('input', function () { el.value = formatPhone(digitsOnly(el.value)); });
			el.addEventListener('blur', function () { el.value = formatPhone(digitsOnly(el.value)); });
		}
		wirePhone('kr_edit_home_phone');
		wirePhone('kr_edit_mobile_phone');

		var stateEl = root.querySelector('#kr_edit_state');
		stateEl.addEventListener('input', function () {
			stateEl.value = stateEl.value.replace(/[^a-zA-Z]/g, '').slice(0, 2).toUpperCase();
		});

		var zipEl = root.querySelector('#kr_edit_zip');
		zipEl.addEventListener('input', function () {
			zipEl.value = zipEl.value.replace(/\D/g, '').slice(0, 5);
		});

		var yearEl = root.querySelector('#kr_edit_year_joined_kiwanis');
		yearEl.addEventListener('input', function () {
			yearEl.value = yearEl.value.replace(/\D/g, '').slice(0, 4);
		});

		root.querySelector('.kiwanis-edit-form').addEventListener('submit', function (e) {
			var newPass = root.querySelector('#kr_edit_user_pass').value;
			var newPass2 = root.querySelector('#kr_edit_user_pass2').value;
			var curPass = root.querySelector('#kr_edit_current_pass').value;

			if (newPass || newPass2) {
				if (newPass !== newPass2) {
					e.preventDefault();
					alert('New passwords must match.');
					return;
				}
				if (!curPass) {
					e.preventDefault();
					alert('Enter your current password to set a new password.');
					return;
				}
			}

			var hp = digitsOnly(root.querySelector('#kr_edit_home_phone').value);
			var mp = digitsOnly(root.querySelector('#kr_edit_mobile_phone').value);
			if (hp.length === 0 && mp.length === 0) {
				e.preventDefault();
				alert('Please enter at least one phone number (home or mobile).');
				return;
			}
			if (hp.length > 0 && hp.length !== 10) {
				e.preventDefault();
				alert('Home phone must be 10 digits in the form ###-###-####.');
				return;
			}
			if (mp.length > 0 && mp.length !== 10) {
				e.preventDefault();
				alert('Mobile phone must be 10 digits in the form ###-###-####.');
				return;
			}

			if (!/^[A-Z]{2}$/.test(stateEl.value)) {
				e.preventDefault();
				alert('State must be exactly two letters (e.g., NC).');
				return;
			}
			if (!/^\d{5}$/.test(zipEl.value)) {
				e.preventDefault();
				alert('ZIP must be exactly five digits.');
				return;
			}
			if (!/^\d{4}$/.test(yearEl.value)) {
				e.preventDefault();
				alert('Year joined must be four digits.');
				return;
			}
		});
	})();
	</script>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kiwanis_edit_profile', 'kr_render_edit_profile_form' );

function kr_process_profile_edit() {
	if ( ! isset( $_POST['action'] ) || 'kr_edit_profile' !== $_POST['action'] ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		kr_edit_profile_redirect_error( 'You must be logged in to update your profile.' );
	}

	if ( ! isset( $_POST['kr_edit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kr_edit_nonce'] ) ), 'kr_edit_profile' ) ) {
		kr_edit_profile_redirect_error( 'Security check failed. Please try again.' );
	}

	$redirect_base = ! empty( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : home_url( '/' );

	$user_id = get_current_user_id();
	$user    = get_userdata( $user_id );
	if ( ! $user ) {
		kr_edit_profile_redirect_error( 'Your account could not be loaded.', $redirect_base );
	}

	$user_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
	$user_pass  = isset( $_POST['user_pass'] ) ? (string) wp_unslash( $_POST['user_pass'] ) : '';
	$user_pass2 = isset( $_POST['user_pass2'] ) ? (string) wp_unslash( $_POST['user_pass2'] ) : '';
	$cur_pass   = isset( $_POST['current_password'] ) ? (string) wp_unslash( $_POST['current_password'] ) : '';

	$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
	$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';

	$home_phone   = isset( $_POST['home_phone'] ) ? wp_unslash( $_POST['home_phone'] ) : '';
	$mobile_phone = isset( $_POST['mobile_phone'] ) ? wp_unslash( $_POST['mobile_phone'] ) : '';
	$street       = isset( $_POST['street_address'] ) ? sanitize_text_field( wp_unslash( $_POST['street_address'] ) ) : '';
	$city         = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
	$state_raw    = isset( $_POST['state'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['state'] ) ) ) : '';
	$zip_raw      = isset( $_POST['zip'] ) ? sanitize_text_field( wp_unslash( $_POST['zip'] ) ) : '';
	$spouse       = isset( $_POST['spouse'] ) ? sanitize_text_field( wp_unslash( $_POST['spouse'] ) ) : '';

	$birth_month = isset( $_POST['birth_month'] ) ? absint( $_POST['birth_month'] ) : 0;
	$birth_day   = isset( $_POST['birth_day'] ) ? absint( $_POST['birth_day'] ) : 0;
	$sponsor     = isset( $_POST['sponsor'] ) ? sanitize_text_field( wp_unslash( $_POST['sponsor'] ) ) : '';
	$year_joined = isset( $_POST['year_joined_kiwanis'] ) ? sanitize_text_field( wp_unslash( $_POST['year_joined_kiwanis'] ) ) : '';

	$honorary = isset( $_POST['honorary_member'] ) ? sanitize_text_field( wp_unslash( $_POST['honorary_member'] ) ) : '';
	$life     = isset( $_POST['life_member'] ) ? sanitize_text_field( wp_unslash( $_POST['life_member'] ) ) : '';

	$enabled = isset( $_POST['enabled'] ) ? '1' : '0';

	if ( '' === $user_email || '' === $first_name || '' === $last_name ) {
		kr_edit_profile_redirect_error( 'Please fill in all required fields.', $redirect_base );
	}
	if ( ! is_email( $user_email ) ) {
		kr_edit_profile_redirect_error( 'Invalid email address.', $redirect_base );
	}

	$other = email_exists( $user_email );
	if ( $other && (int) $other !== $user_id ) {
		kr_edit_profile_redirect_error( 'That email address is already registered to another account.', $redirect_base );
	}

	$changing_pass = ( '' !== $user_pass || '' !== $user_pass2 );
	if ( $changing_pass ) {
		if ( $user_pass !== $user_pass2 ) {
			kr_edit_profile_redirect_error( 'New passwords must match.', $redirect_base );
		}
		if ( '' === $cur_pass || ! wp_check_password( $cur_pass, $user->user_pass, $user_id ) ) {
			kr_edit_profile_redirect_error( 'Current password is incorrect.', $redirect_base );
		}
	}

	$home_stripped   = preg_replace( '/\D+/', '', (string) $home_phone );
	$mobile_stripped = preg_replace( '/\D+/', '', (string) $mobile_phone );
	if ( $home_stripped === '' && $mobile_stripped === '' ) {
		kr_edit_profile_redirect_error( 'Please provide at least one phone number (home or mobile).', $redirect_base );
	}

	$hp = kr_validate_phone_field( $home_phone );
	if ( is_wp_error( $hp ) ) {
		kr_edit_profile_redirect_error( $hp->get_error_message(), $redirect_base );
	}
	$mp = kr_validate_phone_field( $mobile_phone );
	if ( is_wp_error( $mp ) ) {
		kr_edit_profile_redirect_error( $mp->get_error_message(), $redirect_base );
	}

	if ( $street === '' || $city === '' ) {
		kr_edit_profile_redirect_error( 'Please complete all required address and profile fields.', $redirect_base );
	}

	if ( ! preg_match( '/^[A-Z]{2}$/', $state_raw ) ) {
		kr_edit_profile_redirect_error( 'State must be two letters (e.g., NC).', $redirect_base );
	}
	if ( ! preg_match( '/^\d{5}$/', $zip_raw ) ) {
		kr_edit_profile_redirect_error( 'ZIP must be exactly five digits.', $redirect_base );
	}

	if ( $birth_month < 1 || $birth_month > 12 ) {
		kr_edit_profile_redirect_error( 'Please choose a valid birth month.', $redirect_base );
	}
	$dim = 31;
	if ( $birth_month === 2 ) {
		$dim = 29;
	} elseif ( in_array( $birth_month, array( 4, 6, 9, 11 ), true ) ) {
		$dim = 30;
	}
	if ( $birth_day < 1 || $birth_day > $dim ) {
		kr_edit_profile_redirect_error( 'Please choose a valid birth day for the selected month.', $redirect_base );
	}

	if ( ! preg_match( '/^\d{4}$/', $year_joined ) ) {
		kr_edit_profile_redirect_error( 'Year joined must be four digits.', $redirect_base );
	}

	if ( ! in_array( $honorary, array( 'yes', 'no' ), true ) || ! in_array( $life, array( 'yes', 'no' ), true ) ) {
		kr_edit_profile_redirect_error( 'Please answer honorary and life member questions.', $redirect_base );
	}

	$update_args = array(
		'ID'         => $user_id,
		'user_email' => $user_email,
		'first_name' => $first_name,
		'last_name'  => $last_name,
	);

	if ( $changing_pass ) {
		$update_args['user_pass'] = $user_pass;
	}

	$result = wp_update_user( $update_args );
	if ( is_wp_error( $result ) ) {
		kr_edit_profile_redirect_error( $result->get_error_message(), $redirect_base );
	}

	update_user_meta( $user_id, 'home_phone', $hp );
	update_user_meta( $user_id, 'mobile_phone', $mp );
	update_user_meta( $user_id, 'street_address', $street );
	update_user_meta( $user_id, 'city', $city );
	update_user_meta( $user_id, 'state', $state_raw );
	update_user_meta( $user_id, 'zip', $zip_raw );
	update_user_meta( $user_id, 'spouse', $spouse );
	update_user_meta( $user_id, 'birth_month', $birth_month );
	update_user_meta( $user_id, 'birth_day', $birth_day );
	update_user_meta( $user_id, 'sponsor', $sponsor );
	update_user_meta( $user_id, 'year_joined_kiwanis', $year_joined );
	update_user_meta( $user_id, 'enabled', $enabled );
	update_user_meta( $user_id, 'honorary_member', $honorary );
	update_user_meta( $user_id, 'life_member', $life );

	wp_safe_redirect( add_query_arg( array( 'profile' => 'success' ), $redirect_base ) );
	exit;
}

add_action( 'admin_post_kr_edit_profile', 'kr_process_profile_edit' );