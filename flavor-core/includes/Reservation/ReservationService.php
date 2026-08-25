<?php
/**
 * Book / confirm reservations and fire SMS.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Reservation;

use FlavorCore\Customer\OtpAuth;
use FlavorCore\SMS\SmsManager;
use FlavorCore\Support\Iran;
use FlavorCore\Support\Jalali;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReservationService
 */
class ReservationService {

	/**
	 * Create from a public or staff payload.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function book( array $payload ) {
		$branch_id = (int) ( $payload['branch_id'] ?? 0 );
		$party     = max( 1, (int) ( $payload['party_size'] ?? 2 ) );
		$section   = sanitize_key( (string) ( $payload['section'] ?? '' ) );
		$mobile    = Iran::normalize_mobile( (string) ( $payload['mobile'] ?? '' ) );
		$name      = sanitize_text_field( (string) ( $payload['name'] ?? '' ) );

		if ( $branch_id <= 0 || '' === $mobile || '' === $name ) {
			return new \WP_Error( 'flavor_res_fields', __( 'شعبه، نام و موبایل لازم است.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		$date = sanitize_text_field( (string) ( $payload['date'] ?? '' ) );
		if ( preg_match( '/^14\d{2}/', $date ) ) {
			$date = Jalali::jalali_iso_to_gregorian( $date );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new \WP_Error( 'flavor_res_date', __( 'تاریخ رزرو نامعتبر است.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		$time = sanitize_text_field( (string) ( $payload['time'] ?? '' ) );
		if ( preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			$time .= ':00';
		}
		if ( ! preg_match( '/^\d{2}:\d{2}:\d{2}$/', $time ) ) {
			return new \WP_Error( 'flavor_res_time', __( 'ساعت رزرو نامعتبر است.', 'flavor-core' ), array( 'status' => 400 ) );
		}

		$source = sanitize_key( (string) ( $payload['source'] ?? 'online' ) );
		$skip   = ! empty( $payload['force'] ) && current_user_can( 'flavor_manage_reservations' );
		if ( ! $skip && ! SlotCalculator::can_book( $branch_id, $date, $time, $party, $section ) ) {
			return new \WP_Error( 'flavor_res_full', __( 'این ساعت ظرفیت ندارد. ساعت دیگری انتخاب کنید.', 'flavor-core' ), array( 'status' => 409 ) );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			$found = OtpAuth::find_or_create( $mobile, $name );
			if ( ! is_wp_error( $found ) ) {
				$user_id = $found->ID;
			}
		}

		$status = in_array( $source, array( 'walk_in', 'phone' ), true ) ? 'confirmed' : 'pending';
		$id     = ReservationRepository::create(
			array(
				'branch_id'        => $branch_id,
				'section'          => $section,
				'reservation_date' => $date,
				'reservation_time' => $time,
				'party_size'       => $party,
				'customer_id'      => $user_id ?: null,
				'customer_name'    => $name,
				'customer_mobile'  => $mobile,
				'status'           => $status,
				'special_requests' => (string) ( $payload['requests'] ?? '' ),
				'source'           => $source ?: 'online',
			)
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$row = ReservationRepository::find( $id );
		if ( $row && 'confirmed' === $status ) {
			self::sms_confirm( $row );
		}

		return array(
			'ok'           => true,
			'id'           => $id,
			'status'       => $status,
			'date'         => $date,
			'jalali'       => $row['jalali'] ?? Jalali::parse_gregorian( $date ),
			'jalali_label' => $row['jalali_label'] ?? Jalali::format( $date, true ),
			'time'         => substr( $time, 0, 5 ),
			'no_shows'     => ReservationRepository::no_show_count( $mobile ),
		);
	}

	/**
	 * Confirm SMS.
	 *
	 * @param array<string, mixed> $row Row.
	 */
	public static function sms_confirm( array $row ): void {
		SmsManager::send_event(
			'reservation_confirm',
			(string) $row['customer_mobile'],
			array(
				'branch' => get_the_title( (int) $row['branch_id'] ),
				'date'   => (string) ( $row['jalali_label'] ?? $row['reservation_date'] ),
				'time'   => substr( (string) $row['reservation_time'], 0, 5 ),
			),
			array(
				'related_type' => 'reservation',
				'related_id'   => (int) $row['id'],
			)
		);
	}

	/**
	 * Hourly reminder pass.
	 */
	public static function send_reminders(): void {
		foreach ( ReservationRepository::due_reminders() as $row ) {
			SmsManager::send_event(
				'reservation_reminder',
				(string) $row['customer_mobile'],
				array(
					'branch' => get_the_title( (int) $row['branch_id'] ),
					'date'   => (string) ( $row['jalali_label'] ?? '' ),
					'time'   => substr( (string) $row['reservation_time'], 0, 5 ),
				),
				array(
					'related_type' => 'reservation',
					'related_id'   => (int) $row['id'],
				)
			);
			ReservationRepository::mark_reminded( (int) $row['id'] );
		}
	}
}
