<?php
/**
 * REST response envelopes (uniform contract: data + meta).
 *
 * @package WP_Heart
 */

namespace WPHeart\Rest;

/**
 * REST response envelopes (uniform contract: data + meta).
 */
class Presenter {
	/**
	 * @param mixed $data Payload.
	 * @param array $meta Metadata (freshness, pagination, accuracy...).
	 * @param int   $status HTTP status.
	 * @return \WP_REST_Response
	 */
	public static function ok( $data, array $meta = array(), $status = 200 ) {
		$response = new \WP_REST_Response(
			array(
				'data' => $data,
				'meta' => $meta,
			),
			$status
		);
		return $response;
	}
}
