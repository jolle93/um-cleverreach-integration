<?php

/**
 * Plugin Name:     FACT Ultimate Member - rapidmail Integration
 * Description:     Extension to Ultimate Member to extend the standard register formular with a newsletter registration (rapidmail, double opt-in)
 * Version:         2.0.0
 * Requires PHP:    8.1
 * Author:          Julian Paul
 * License:         Apache-2.0
 * License URI:     https://www.apache.org/licenses/LICENSE-2.0
 * Author URI:      https://github.com/jolle93/um-cleverreach-integration
 * Plugin URI:      https://github.com/jolle93/um-cleverreach-integration
 * Update URI:      https://github.com/jolle93/um-cleverreach-integration
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.9.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'UM' ) ) {
	return;
}

require_once __DIR__ . '/RapidmailClient.php';

class UmCrIntegration {

	private const OPTION_GROUP = 'umcr_settings';
	private const OPTION_USER = 'umcr_rapidmail_user';
	private const OPTION_PASSWORD = 'umcr_rapidmail_password';
	private const OPTION_LIST_ID = 'umcr_rapidmail_list_id';
	private const PAGE_SLUG = 'umcr-rapidmail';

	public function __construct() {
		add_action( 'um_submit_form_errors_hook', array( $this, 'checkForNLregister' ), 10, 1 );
		add_action( 'admin_menu', array( $this, 'addSettingsPage' ) );
		add_action( 'admin_init', array( $this, 'registerSettings' ) );
	}

	public function checkForNLregister( $submitted_data ): void {
		if ( empty( $submitted_data ) || ! isset( $submitted_data['register_for_nl'] ) ) {
			return;
		}

		$user   = (string) get_option( self::OPTION_USER, '' );
		$pass   = (string) get_option( self::OPTION_PASSWORD, '' );
		$listId = (int) get_option( self::OPTION_LIST_ID, 0 );
		if ( $user === '' || $pass === '' || $listId <= 0 ) {
			error_log( 'UmCrIntegration: rapidmail settings incomplete, skipping newsletter registration' );
			return;
		}

		try {
			( new RapidmailClient( $user, $pass ) )->createRecipient(
				$this->createNewRecipient( $submitted_data, $listId )
			);
		} catch ( Throwable $e ) {
			// never break the UM registration because of the newsletter
			error_log( 'UmCrIntegration: ' . $e->getMessage() );
		}
	}

	private function createNewRecipient( array $submitted_data, int $listId ): array {
		return array(
			'recipientlist_id' => $listId,
			'status'           => 'new', // activation mail is only sent for status "new"
			'email'          => sanitize_email( $submitted_data['user_email'] ?? '' ),
			'firstname'        => sanitize_text_field( $submitted_data['first_name'] ?? '' ),
			'lastname'         => sanitize_text_field( $submitted_data['last_name'] ?? '' ),
		);
	}

	public function addSettingsPage(): void {
		add_options_page( 'UM rapidmail', 'UM rapidmail', 'manage_options', self::PAGE_SLUG, array( $this, 'renderSettingsPage' ) );
	}

	public function registerSettings(): void {
		register_setting( self::OPTION_GROUP, self::OPTION_USER, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( self::OPTION_GROUP, self::OPTION_PASSWORD, array( 'sanitize_callback' => array( $this, 'sanitizePassword' ) ) );
		register_setting( self::OPTION_GROUP, self::OPTION_LIST_ID, array( 'sanitize_callback' => 'absint' ) );
	}

	/**
	 * The password field is never rendered, so an empty submit keeps the stored value.
	 */
	public function sanitizePassword( $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';

		return $value !== '' ? $value : (string) get_option( self::OPTION_PASSWORD, '' );
	}

	public function renderSettingsPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$hasPassword = (string) get_option( self::OPTION_PASSWORD, '' ) !== '';
		?>
		<div class="wrap">
			<h1>UM rapidmail</h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::OPTION_USER ); ?>">API-Username</label></th>
						<td><input type="text" class="regular-text" id="<?php echo esc_attr( self::OPTION_USER ); ?>"
						           name="<?php echo esc_attr( self::OPTION_USER ); ?>"
						           value="<?php echo esc_attr( get_option( self::OPTION_USER, '' ) ); ?>"
						           autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::OPTION_PASSWORD ); ?>">API-Passwort</label></th>
						<td><input type="password" class="regular-text" id="<?php echo esc_attr( self::OPTION_PASSWORD ); ?>"
						           name="<?php echo esc_attr( self::OPTION_PASSWORD ); ?>" value=""
						           autocomplete="new-password"
						           placeholder="<?php echo $hasPassword ? '••••••••' : ''; ?>">
							<?php if ( $hasPassword ) : ?><p class="description">Gespeichert. Leer lassen, um es beizubehalten.</p><?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::OPTION_LIST_ID ); ?>">Empfängerliste (ID)</label></th>
						<td><input type="number" class="regular-text" id="<?php echo esc_attr( self::OPTION_LIST_ID ); ?>"
						           name="<?php echo esc_attr( self::OPTION_LIST_ID ); ?>"
						           value="<?php echo esc_attr( (string) get_option( self::OPTION_LIST_ID, '' ) ); ?>"></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

new UmCrIntegration();
