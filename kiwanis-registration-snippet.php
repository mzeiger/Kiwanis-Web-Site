<?php
/**
 * Kiwanis-style front-end registration: shortcode + POST handler + AJAX username/email check.
 * Shortcode: [kiwanis_registration_form]
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

if ( ! function_exists( 'mhk_prepare_member_image_upload' ) ) {
	/**
	 * Validate a member photo upload before the user row exists.
	 *
	 * @param array $file $_FILES entry.
	 * @return array{tmp:string,ext:string}|null|WP_Error Null when no file was chosen.
	 */
	function mhk_prepare_member_image_upload( $file ) {
		if ( empty( $file ) || ! is_array( $file ) ) {
			return null;
		}
		$err = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_NO_FILE === $err ) {
			return null;
		}
		if ( UPLOAD_ERR_OK !== $err ) {
			return new WP_Error( 'upload', 'Photo upload failed. Try a smaller image.' );
		}
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'upload', 'No photo file was received.' );
		}
		if ( (int) $file['size'] > 5 * 1024 * 1024 ) {
			return new WP_Error( 'upload', 'Photo must be 5 MB or smaller.' );
		}

		$check   = wp_check_filetype_and_ext( $file['tmp_name'], isset( $file['name'] ) ? $file['name'] : '' );
		$allowed = array( 'jpg', 'jpeg', 'png', 'webp', 'gif' );
		$ext     = strtolower( (string) ( $check['ext'] ?? '' ) );
		if ( ! $ext || ! in_array( $ext, $allowed, true ) ) {
			return new WP_Error( 'upload', 'Use a JPG, PNG, WebP, or GIF image.' );
		}

		$info = @getimagesize( $file['tmp_name'] );
		if ( ! $info ) {
			return new WP_Error( 'upload', 'That file is not a valid image.' );
		}

		return array(
			'tmp' => $file['tmp_name'],
			'ext' => $ext,
		);
	}
}

if ( ! function_exists( 'mhk_commit_member_image_upload' ) ) {
	/**
	 * Move a previously validated upload into member_images and store user meta.
	 *
	 * @param int    $user_id New user ID.
	 * @param string $tmp     Uploaded temp path.
	 * @param string $ext     Safe extension.
	 * @return bool
	 */
	function mhk_commit_member_image_upload( $user_id, $tmp, $ext ) {
		$user_id = (int) $user_id;
		$ext     = strtolower( (string) $ext );
		$allowed = array( 'jpg', 'jpeg', 'png', 'webp', 'gif' );
		if ( $user_id <= 0 || ! in_array( $ext, $allowed, true ) || ! is_uploaded_file( $tmp ) ) {
			return false;
		}

		$dir = mhk_member_images_dir();
		if ( ! wp_mkdir_p( $dir ) || ! is_writable( $dir ) ) {
			return false;
		}

		$filename = $user_id . '.' . $ext;
		$dest     = $dir . '/' . $filename;
		if ( is_file( $dest ) ) {
			@unlink( $dest );
		}
		if ( ! move_uploaded_file( $tmp, $dest ) ) {
			return false;
		}
		@chmod( $dest, 0644 );
		update_user_meta( $user_id, 'member_image', $filename );
		return true;
	}
}

/**
 * AJAX: username / email availability (on blur from front end).
 */
function kr_ajax_check_registration_availability() {
	check_ajax_referer( 'kr_reg_ajax', 'nonce' );

	$field = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : '';
	$value = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';

	if ( 'username' === $field ) {
		$u = sanitize_user( $value, true );

		if ( '' === $u ) {
			wp_send_json(
				array(
					'available' => false,
					'message'   => 'Please enter a username.',
				)
			);
		}

		$valid = validate_username( $u );
		if ( is_wp_error( $valid ) ) {
			wp_send_json(
				array(
					'available' => false,
					'message'   => $valid->get_error_message(),
				)
			);
		}

		if ( username_exists( $u ) ) {
			wp_send_json(
				array(
					'available' => false,
					'message'   => 'That username is already taken.',
				)
			);
		}

		wp_send_json(
			array(
				'available' => true,
				'message'   => 'This username is available.',
			)
		);
	}

	if ( 'email' === $field ) {
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

		if ( email_exists( $e ) ) {
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
				'message'   => 'This email address is available.',
			)
		);
	}

	wp_send_json(
		array(
			'available' => false,
			'message'   => 'Invalid request.',
		)
	);
}

add_action( 'wp_ajax_nopriv_kr_check_registration_availability', 'kr_ajax_check_registration_availability' );
add_action( 'wp_ajax_kr_check_registration_availability', 'kr_ajax_check_registration_availability' );

function kr_render_registration_form() {
	$notice = '';
	if ( isset( $_GET['reg'] ) ) {
		if ( $_GET['reg'] === 'success' ) {
			$notice = '<div class="kr-notice kr-notice-success">Registration successful. Check your email for a link to set your password, then you can log in.</div>';
		} elseif ( $_GET['reg'] === 'error' && ! empty( $_GET['kr_err'] ) ) {
			$notice = '<div class="kr-notice kr-notice-error">' . esc_html( rawurldecode( (string) $_GET['kr_err'] ) ) . '</div>';
		}
	}

	ob_start();
	$uid    = 'kr-' . wp_unique_id();
	$nonce  = wp_create_nonce( 'kr_reg_ajax' );
	$ajax   = esc_url( admin_url( 'admin-ajax.php' ) );

	echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	$action_url = esc_url( admin_url( 'admin-post.php' ) );
	?>
	<div
		class="kr-reg-wrap"
		id="<?php echo esc_attr( $uid ); ?>"
		data-nonce="<?php echo esc_attr( $nonce ); ?>"
		data-ajaxurl="<?php echo esc_attr( $ajax ); ?>"
	>
	<form class="kiwanis-registration-form" method="post" action="<?php echo $action_url; ?>" enctype="multipart/form-data">
		<input type="hidden" name="action" value="kr_user_registration" />
		<?php wp_nonce_field( 'kr_user_registration', 'kr_registration_nonce' ); ?>
		<?php wp_referer_field( true ); ?>
		<input type="hidden" name="enabled" value="1" />

		<fieldset>
			<legend>Account</legend>
			<p>
				<label for="kr_user_login">Username <span class="req">*</span></label><br />
				<input type="text" name="user_login" id="kr_user_login" required autocomplete="username" />
				<span class="kr-field-status" id="kr_user_login_status" aria-live="polite"></span>
			</p>
			<p>
				<label for="kr_user_email">Email <span class="req">*</span></label><br />
				<input type="email" name="user_email" id="kr_user_email" required autocomplete="email" />
				<span class="kr-field-status" id="kr_user_email_status" aria-live="polite"></span>
			</p>
			<p class="kr-field-hint">You will set your password from a link in the email sent after you register.</p>
		</fieldset>

		<fieldset>
			<legend>Name</legend>
			<p>
				<label for="kr_first_name">First name <span class="req">*</span></label><br />
				<input type="text" name="first_name" id="kr_first_name" required />
			</p>
			<p>
				<label for="kr_last_name">Last name <span class="req">*</span></label><br />
				<input type="text" name="last_name" id="kr_last_name" required />
			</p>
		</fieldset>

		<fieldset>
			<legend>Contact</legend>
			<p class="kr-phone-hint">Provide at least one phone number <span class="req">*</span></p>
			<p>
				<label for="kr_home_phone">Home phone (###-###-####)</label><br />
				<input type="text" name="home_phone" id="kr_home_phone" inputmode="numeric" placeholder="555-123-4567" maxlength="12" autocomplete="tel" />
			</p>
			<p>
				<label for="kr_mobile_phone">Mobile phone (###-###-####)</label><br />
				<input type="text" name="mobile_phone" id="kr_mobile_phone" inputmode="numeric" placeholder="555-987-6543" maxlength="12" autocomplete="tel" />
			</p>
			<p>
				<label for="kr_street_address">Street address <span class="req">*</span></label><br />
				<input type="text" name="street_address" id="kr_street_address" required />
			</p>
			<p>
				<label for="kr_city">City <span class="req">*</span></label><br />
				<input type="text" name="city" id="kr_city" required />
			</p>
			<p>
				<label for="kr_state">State (2 letters) <span class="req">*</span></label><br />
				<input type="text" name="state" id="kr_state" maxlength="2" style="text-transform:uppercase" autocomplete="address-level1" required />
			</p>
			<p>
				<label for="kr_zip">ZIP (#####) <span class="req">*</span></label><br />
				<input type="text" name="zip" id="kr_zip" maxlength="5" inputmode="numeric" required />
			</p>
		</fieldset>

		<fieldset>
			<legend>Profile</legend>
			<p>
				<label for="kr_spouse">Spouse</label><br />
				<input type="text" name="spouse" id="kr_spouse" />
			</p>

			<p>
				<label for="kr_birth_month">Birth month <span class="req">*</span></label><br />
				<select name="birth_month" id="kr_birth_month" required>
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
						printf( '<option value="%d">%s</option>', esc_attr( (string) $num ), esc_html( $label ) );
					}
					?>
				</select>
			</p>
			<p>
				<label for="kr_birth_day">Birth day <span class="req">*</span></label><br />
				<select name="birth_day" id="kr_birth_day" required disabled>
					<option value="">— Select month first —</option>
				</select>
			</p>

			<p>
				<label for="kr_sponsor">Sponsor</label><br />
				<input type="text" name="sponsor" id="kr_sponsor" />
			</p>
			<p>
				<label for="kr_year_joined_kiwanis">Year joined Kiwanis (####) <span class="req">*</span></label><br />
				<input type="text" name="year_joined_kiwanis" id="kr_year_joined_kiwanis" maxlength="4" inputmode="numeric" required />
			</p>

			<fieldset class="kr-radio-group">
				<legend>Honorary member <span class="req">*</span></legend>
				<label><input type="radio" name="honorary_member" value="yes" required /> Yes</label>
				<label><input type="radio" name="honorary_member" value="no" checked /> No</label>
			</fieldset>

			<fieldset class="kr-radio-group">
				<legend>Life member <span class="req">*</span></legend>
				<label><input type="radio" name="life_member" value="yes" required /> Yes</label>
				<label><input type="radio" name="life_member" value="no" checked /> No</label>
			</fieldset>
		</fieldset>

		<fieldset class="kr-photo-box">
			<legend>Member photo</legend>
			<p class="kr-field-hint">Optional. You can click the button or drag a photo onto the box. It is saved with this new member when you click Register.</p>
			<div class="kr-photo-row kr-photo-drop" id="kr-reg-photo-drop">
				<img id="kr-reg-photo-preview" class="kr-photo-preview" src="<?php echo esc_url( mhk_member_images_url() . 'unknown.webp' ); ?>" alt="Member photo preview">
				<div class="kr-photo-actions">
					<input type="file" name="member_image" id="kr_member_image" accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif" hidden>
					<button type="button" class="kr-photo-btn" id="kr-reg-photo-btn">Choose photo</button>
					<p class="kr-photo-hint">Or drag a photo onto this box</p>
					<div id="kr-reg-photo-status" class="kr-photo-status" aria-live="polite"></div>
				</div>
			</div>
		</fieldset>

		<p><button type="submit" class="kr-submit">Register</button></p>
	</form>

	<style>
		.oxy-shortcode.my-register-shortcode {
			width: 100%;
			max-width: 100%;
			margin-left: auto;
			margin-right: auto;
			box-sizing: border-box;
		}
		.kr-reg-wrap {
			width: 100%;
			max-width: 560px;
			margin-left: auto;
			margin-right: auto;
			padding-left: 0.75rem;
			padding-right: 0.75rem;
			box-sizing: border-box;
		}
		.kr-reg-wrap .kiwanis-registration-form {
			width: 100%;
			max-width: 100%;
			margin-left: auto;
			margin-right: auto;
			box-sizing: border-box;
		}
		.kr-reg-wrap fieldset {
			min-width: 0;
			max-width: 100%;
			margin: 1rem 0;
			padding: 1rem;
			border: 1px solid #ccc;
			box-sizing: border-box;
		}
		.kr-reg-wrap input[type="text"],
		.kr-reg-wrap input[type="email"],
		.kr-reg-wrap select {
			width: 100%;
			max-width: 100%;
			box-sizing: border-box;
		}
		.kr-reg-wrap legend { font-weight: 600; padding: 0 .35rem; }
		.kr-reg-wrap .kr-radio-group label { margin-right: 1rem; }
		.kr-reg-wrap .req { color: #b91c1c; }
		.kr-reg-wrap .kr-phone-hint { font-size: 0.9rem; color: #334155; margin-bottom: 0.25rem; }
		.kr-notice { padding: .75rem 1rem; margin: 1rem 0; border-radius: 6px; }
		.kr-notice-success { background: #ecfdf5; border: 1px solid #6ee7b7; }
		.kr-notice-error { background: #fef2f2; border: 1px solid #fca5a5; }
		.kr-submit { padding: .5rem 1rem; }

		.kr-reg-wrap .kr-field-status {
			display: block;
			font-size: 0.85rem;
			margin-top: 0.25rem;
			min-height: 1.2em;
		}
		.kr-reg-wrap .kr-field-status.kr-ok { color: #15803d; }
		.kr-reg-wrap .kr-field-status.kr-bad { color: #b91c1c; }
		.kr-reg-wrap .kr-field-status.kr-info { color: #64748b; }

		.kr-reg-wrap .kr-field-hint { display: block; font-size: 0.85rem; color: #64748b; margin: 0 0 0.75rem; }
		.kr-reg-wrap .kr-photo-row { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; }
		.kr-reg-wrap .kr-photo-drop {
			width: 100%;
			box-sizing: border-box;
			padding: 0.75rem 0.9rem;
			border: 2px dashed #93c5fd;
			border-radius: 12px;
			background: #f8fbff;
			transition: border-color .15s ease, background .15s ease, box-shadow .15s ease;
		}
		.kr-reg-wrap .kr-photo-drop.is-dragover {
			border-color: #1d4ed8;
			background: #eef2ff;
			box-shadow: 0 0 0 4px rgba(29,78,216,.12);
		}
		.kr-reg-wrap .kr-photo-preview { width: 148px; height: 148px; object-fit: cover; border-radius: 12px; border: 1px solid #cbd5e1; background: #fff; }
		.kr-reg-wrap .kr-photo-actions { display: flex; flex-direction: column; align-items: flex-start; gap: 0.5rem; }
		.kr-reg-wrap .kr-photo-btn {
			border: 0;
			border-radius: 8px;
			cursor: pointer;
			background: #1d4ed8;
			padding: 0.6rem 1rem;
			color: #fff;
			font-weight: 700;
		}
		.kr-reg-wrap .kr-photo-btn:hover { filter: brightness(1.06); }
		.kr-reg-wrap .kr-photo-hint { margin: 0; font-size: 0.85rem; color: #64748b; line-height: 1.35; }
		.kr-reg-wrap .kr-photo-status { min-height: 1.2em; font-size: 0.9rem; color: #334155; }
		.kr-reg-wrap .kr-photo-status.is-error { color: #b91c1c; }
		.kr-reg-wrap .kr-photo-status.is-ok { color: #15803d; }
	</style>

	<script>
	(function () {
		var root = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
		if (!root) return;

		var ajaxUrl = root.getAttribute('data-ajaxurl');
		var nonce = root.getAttribute('data-nonce');

		var photoBtn = document.getElementById('kr-reg-photo-btn');
		var photoFile = document.getElementById('kr_member_image');
		var photoPreview = document.getElementById('kr-reg-photo-preview');
		var photoStatus = document.getElementById('kr-reg-photo-status');
		var photoDrop = document.getElementById('kr-reg-photo-drop');
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
		function applyChosenPhoto(file) {
			if (!file) return;
			if (!isAllowedPhoto(file)) {
				photoFile.value = '';
				setPhotoStatus('error', 'Use a JPG, PNG, WebP, or GIF image.');
				return;
			}
			if (file.size > 5 * 1024 * 1024) {
				photoFile.value = '';
				setPhotoStatus('error', 'Photo must be 5 MB or smaller.');
				return;
			}
			if (!photoFile.files || photoFile.files[0] !== file) {
				if (typeof DataTransfer === 'undefined') {
					setPhotoStatus('error', 'Drag-and-drop is not supported in this browser. Use Choose photo.');
					return;
				}
				var dt = new DataTransfer();
				dt.items.add(file);
				photoFile.files = dt.files;
			}
			if (photoPreview) {
				photoPreview.src = URL.createObjectURL(file);
			}
			setPhotoStatus('ok', 'Photo selected. It will be saved when you click Register.');
		}
		if (photoBtn && photoFile) {
			photoBtn.addEventListener('click', function () { photoFile.click(); });
			photoFile.addEventListener('change', function () {
				if (!photoFile.files || !photoFile.files[0]) return;
				applyChosenPhoto(photoFile.files[0]);
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
					applyChosenPhoto(files[0]);
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

		function setStatus(el, type, text) {
			el.textContent = text || '';
			el.className = 'kr-field-status';
			if (!text) return;
			if (type === 'ok') el.classList.add('kr-ok');
			else if (type === 'bad') el.classList.add('kr-bad');
			else el.classList.add('kr-info');
		}

		function checkAvailability(field, input, statusEl) {
			var raw = (input.value || '').trim();
			setStatus(statusEl, '', '');
			if (!raw) return;

			if (field === 'email') {
				// simple format check before hitting server
				if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(raw)) {
					setStatus(statusEl, 'bad', 'Please enter a valid email address.');
					return;
				}
			}

			setStatus(statusEl, 'info', 'Checking…');

			var fd = new FormData();
			fd.append('action', 'kr_check_registration_availability');
			fd.append('nonce', nonce);
			fd.append('field', field);
			fd.append('value', raw);

			fetch(ajaxUrl, {
				method: 'POST',
				body: fd,
				credentials: 'same-origin'
			})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (!data || typeof data.available === 'undefined') {
					setStatus(statusEl, 'bad', 'Could not verify. Try again.');
					return;
				}
				if (data.available) {
					setStatus(statusEl, 'ok', data.message || 'Available.');
				} else {
					setStatus(statusEl, 'bad', data.message || 'Not available.');
				}
			})
			.catch(function () {
				setStatus(statusEl, 'bad', 'Could not verify. Check your connection.');
			});
		}

		var loginInput = root.querySelector('#kr_user_login');
		var loginStatus = root.querySelector('#kr_user_login_status');
		if (loginInput && loginStatus) {
			loginInput.addEventListener('blur', function () {
				checkAvailability('username', loginInput, loginStatus);
			});
			loginInput.addEventListener('input', function () {
				setStatus(loginStatus, '', '');
			});
		}

		var emailInput = root.querySelector('#kr_user_email');
		var emailStatus = root.querySelector('#kr_user_email_status');
		if (emailInput && emailStatus) {
			emailInput.addEventListener('blur', function () {
				checkAvailability('email', emailInput, emailStatus);
			});
			emailInput.addEventListener('input', function () {
				setStatus(emailStatus, '', '');
			});
		}

		var monthEl = root.querySelector('#kr_birth_month');
		var dayEl = root.querySelector('#kr_birth_day');
		function daysInMonth(m) {
			m = parseInt(m, 10);
			if (m === 2) return 29;
			if ([4, 6, 9, 11].indexOf(m) !== -1) return 30;
			return 31;
		}
		function rebuildDays() {
			var m = monthEl.value;
			var previous = dayEl.value;
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
			if (previous && parseInt(previous, 10) <= max) dayEl.value = previous;
		}
		monthEl.addEventListener('change', rebuildDays);
		rebuildDays();

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
		wirePhone('kr_home_phone');
		wirePhone('kr_mobile_phone');

		var stateEl = root.querySelector('#kr_state');
		stateEl.addEventListener('input', function () {
			stateEl.value = stateEl.value.replace(/[^a-zA-Z]/g, '').slice(0, 2).toUpperCase();
		});

		var zipEl = root.querySelector('#kr_zip');
		zipEl.addEventListener('input', function () {
			zipEl.value = zipEl.value.replace(/\D/g, '').slice(0, 5);
		});

		var yearEl = root.querySelector('#kr_year_joined_kiwanis');
		yearEl.addEventListener('input', function () {
			yearEl.value = yearEl.value.replace(/\D/g, '').slice(0, 4);
		});

		root.querySelector('.kiwanis-registration-form').addEventListener('submit', function (e) {
			var hp = digitsOnly(root.querySelector('#kr_home_phone').value);
			var mp = digitsOnly(root.querySelector('#kr_mobile_phone').value);
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
add_shortcode( 'kiwanis_registration_form', 'kr_render_registration_form' );

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

/**
 * Replace one custom letter tag, including any placeholder text inside it.
 *
 * @param string $html
 * @param string $tag
 * @param string $replacement Already-safe HTML.
 * @return string
 */
function kr_replace_letter_tag( $html, $tag, $replacement ) {
	$quoted = preg_quote( $tag, '#' );
	return preg_replace( '#<' . $quoted . '\b[^>]*>.*?</' . $quoted . '\s*>#is', $replacement, $html );
}

/**
 * Email the published page admin-registration-letter with its custom tags filled in.
 *
 * @param int    $user_id
 * @param string $first_name
 * @param string $last_name
 * @return bool
 */
function kr_send_registration_letter( $user_id, $first_name, $last_name ) {
	$user = get_userdata( $user_id );
	if ( ! $user || ! is_email( $user->user_email ) ) {
		return false;
	}

	$pages = get_posts(
		array(
			'name'             => 'admin-registration-letter',
			'post_type'        => 'page',
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);
	if ( empty( $pages ) || empty( $pages[0]->post_content ) ) {
		return false;
	}
	$page = $pages[0];

	$previous_post   = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
	$GLOBALS['post'] = $page;
	setup_postdata( $page );
	$html = apply_filters( 'the_content', $page->post_content );
	wp_reset_postdata();
	if ( null !== $previous_post ) {
		$GLOBALS['post'] = $previous_post;
	}

	if ( trim( wp_strip_all_tags( $html ) ) === '' ) {
		return false;
	}

	$key = get_password_reset_key( $user );
	if ( is_wp_error( $key ) ) {
		return false;
	}

	$reset_url = network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' );

	$first = esc_html( $first_name );
	$last  = esc_html( $last_name );
	$date  = esc_html( function_exists( 'wp_date' ) ? wp_date( 'F j, Y' ) : date_i18n( 'F j, Y' ) );
	$link  = '<a href="' . esc_url( $reset_url ) . '">' . esc_html( $reset_url ) . '</a>';

	$html = preg_replace(
		'#<first-name\b[^>]*>\s*</first-name>\s*<last-name\b[^>]*>\s*</last-name>#i',
		$first . ' ' . $last,
		$html
	);
	$html = kr_replace_letter_tag( $html, 'first-name', $first );
	$html = kr_replace_letter_tag( $html, 'last-name', $last );
	$html = kr_replace_letter_tag( $html, 'print-date', $date );
	$html = kr_replace_letter_tag( $html, 'password-link', $link );

	$styles = '';
	if ( preg_match_all( '#<style\b[^>]*>.*?</style>#is', $html, $style_matches ) ) {
		$styles = implode( "\n", $style_matches[0] );
		$html   = preg_replace( '#<style\b[^>]*>.*?</style>#is', '', $html );
	}

	$body    = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' . $styles . '</head><body>' . $html . '</body></html>';
	$subject = sprintf( 'Welcome to %s', wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) );
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );

	return (bool) wp_mail( $user->user_email, $subject, $body, $headers );
}

function kr_process_registration() {
	if ( ! isset( $_POST['action'] ) || 'kr_user_registration' !== $_POST['action'] ) {
		return;
	}

	if ( ! isset( $_POST['kr_registration_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kr_registration_nonce'] ) ), 'kr_user_registration' ) ) {
		kr_registration_redirect_error( 'Security check failed. Please try again.' );
	}

	$redirect_base = ! empty( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : home_url( '/' );

	$user_login = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ), true ) : '';
	$user_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';

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

	if ( $user_login === '' || $user_email === '' || $first_name === '' || $last_name === '' ) {
		kr_registration_redirect_error( 'Please fill in all required fields.', $redirect_base );
	}
	if ( ! is_email( $user_email ) ) {
		kr_registration_redirect_error( 'Invalid email address.', $redirect_base );
	}
	if ( email_exists( $user_email ) ) {
		kr_registration_redirect_error( 'That email is already registered.', $redirect_base );
	}
	if ( username_exists( $user_login ) ) {
		kr_registration_redirect_error( 'That username is already taken.', $redirect_base );
	}

	$home_stripped   = preg_replace( '/\D+/', '', (string) $home_phone );
	$mobile_stripped = preg_replace( '/\D+/', '', (string) $mobile_phone );
	if ( $home_stripped === '' && $mobile_stripped === '' ) {
		kr_registration_redirect_error( 'Please provide at least one phone number (home or mobile).', $redirect_base );
	}

	$hp = kr_validate_phone_field( $home_phone );
	if ( is_wp_error( $hp ) ) {
		kr_registration_redirect_error( $hp->get_error_message(), $redirect_base );
	}
	$mp = kr_validate_phone_field( $mobile_phone );
	if ( is_wp_error( $mp ) ) {
		kr_registration_redirect_error( $mp->get_error_message(), $redirect_base );
	}

	if ( $street === '' || $city === '' ) {
		kr_registration_redirect_error( 'Please complete all required address and profile fields.', $redirect_base );
	}

	if ( ! preg_match( '/^[A-Z]{2}$/', $state_raw ) ) {
		kr_registration_redirect_error( 'State must be two letters (e.g., NC).', $redirect_base );
	}
	if ( ! preg_match( '/^\d{5}$/', $zip_raw ) ) {
		kr_registration_redirect_error( 'ZIP must be exactly five digits.', $redirect_base );
	}

	if ( $birth_month < 1 || $birth_month > 12 ) {
		kr_registration_redirect_error( 'Please choose a valid birth month.', $redirect_base );
	}
	$dim = 31;
	if ( $birth_month === 2 ) {
		$dim = 29;
	} elseif ( in_array( $birth_month, array( 4, 6, 9, 11 ), true ) ) {
		$dim = 30;
	}
	if ( $birth_day < 1 || $birth_day > $dim ) {
		kr_registration_redirect_error( 'Please choose a valid birth day for the selected month.', $redirect_base );
	}

	if ( ! preg_match( '/^\d{4}$/', $year_joined ) ) {
		kr_registration_redirect_error( 'Year joined must be four digits.', $redirect_base );
	}

	if ( ! in_array( $honorary, array( 'yes', 'no' ), true ) || ! in_array( $life, array( 'yes', 'no' ), true ) ) {
		kr_registration_redirect_error( 'Please answer honorary and life member questions.', $redirect_base );
	}

	$photo = mhk_prepare_member_image_upload( isset( $_FILES['member_image'] ) ? $_FILES['member_image'] : array() );
	if ( is_wp_error( $photo ) ) {
		kr_registration_redirect_error( $photo->get_error_message(), $redirect_base );
	}

	$user_id = wp_insert_user(
		array(
			'user_login' => $user_login,
			'user_pass'  => wp_generate_password( 24, true, true ),
			'user_email' => $user_email,
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'role'       => 'subscriber',
		)
	);

	if ( is_wp_error( $user_id ) ) {
		kr_registration_redirect_error( $user_id->get_error_message(), $redirect_base );
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

	if ( is_array( $photo ) ) {
		mhk_commit_member_image_upload( (int) $user_id, $photo['tmp'], $photo['ext'] );
	}

	update_user_option( $user_id, 'default_password_nag', true, true );
	if ( ! kr_send_registration_letter( $user_id, $first_name, $last_name ) && function_exists( 'wp_send_new_user_notifications' ) ) {
		wp_send_new_user_notifications( $user_id, 'user' );
	}

	wp_safe_redirect( add_query_arg( array( 'reg' => 'success' ), $redirect_base ) );
	exit;
}

function kr_registration_redirect_error( $message, $redirect_base = '' ) {
	$base = $redirect_base ? $redirect_base : home_url( '/' );
	wp_safe_redirect(
		add_query_arg(
			array(
				'reg'    => 'error',
				'kr_err' => rawurlencode( $message ),
			),
			$base
		)
	);
	exit;
}

add_action( 'admin_post_nopriv_kr_user_registration', 'kr_process_registration' );
add_action( 'admin_post_kr_user_registration', 'kr_process_registration' );