<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal client for the rapidmail REST API v3 (HTTP Basic auth).
 */
class RapidmailClient {

	// https://developer.rapidmail.wiki/specs/public/Recipients_v1.json (host + basePath)
	public const BASE_URL = 'https://apiv3.emailsys.net/v1';

	public const CREATED = 'created';
	public const DUPLICATE = 'duplicate';

	public function __construct(
		private readonly string $user,
		private readonly string $password
	) {
	}

	/**
	 * Creates a recipient; with $sendActivation rapidmail sends the double-opt-in mail.
	 *
	 * @return string self::CREATED or self::DUPLICATE
	 * @throws RuntimeException on transport errors and non-2xx responses (except 409)
	 */
	public function createRecipient( array $data, bool $sendActivation = true ): string {
		$query = $sendActivation ? array( 'send_activationmail' => 'yes' ) : array();
		$code  = $this->request( 'POST', '/recipients', $query, $data )['code'];

		return $code === 409 ? self::DUPLICATE : self::CREATED;
	}

	/**
	 * Cheap credentials check, throws on failure.
	 */
	public function testConnection(): void {
		$this->request( 'GET', '/recipientlists' );
	}

	private function request( string $method, string $path, array $query = array(), ?array $body = null ): array {
		$url = self::BASE_URL . $path;
		if ( $query ) {
			$url = add_query_arg( $query, $url );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->user . ':' . $this->password ),
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			),
		);
		if ( $body !== null ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( 'rapidmail request failed: ' . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		// 409 = recipient already exists, 2xx = success (201/202 seen in the wild)
		if ( ( $code < 200 || $code >= 300 ) && ! ( $method === 'POST' && $code === 409 ) ) {
			throw new RuntimeException( sprintf( 'rapidmail API returned %d: %s', $code, wp_remote_retrieve_body( $response ) ) );
		}

		return array(
			'code' => $code,
			'body' => json_decode( wp_remote_retrieve_body( $response ), true ),
		);
	}
}
