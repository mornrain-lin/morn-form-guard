<?php
/**
 * AJAX 提交处理。
 *
 * @package MornRain\MornFormGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 处理表单提交请求。
 */
class Morn_Form_Guard_Ajax {

	/**
	 * nonce 动作名。
	 */
	const NONCE_ACTION = 'morn_form_guard_submit';

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_morn_form_guard_submit', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_morn_form_guard_submit', array( __CLASS__, 'handle' ) );
	}

	/**
	 * 主处理流程。
	 *
	 * @return void
	 */
	public static function handle() {
		// 无 JS 降级：表单直接 POST 过来时重定向回来源页，而不是输出 JSON。
		$no_js = isset( $_POST['morn_no_js'] ) && '1' === (string) $_POST['morn_no_js']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce 紧随其后校验。

		// 1. nonce 校验。
		$nonce = isset( $_POST['morn_form_guard_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['morn_form_guard_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			self::respond( $no_js, false, __( '表单已过期或校验失败，请刷新页面重试。', 'morn-form-guard' ), 'invalid_nonce', 403 );
		}

		// 2. 反垃圾检查。
		$raw   = self::collect_input();
		$check = Morn_Form_Guard_Antispam::check( $raw );

		if ( empty( $check['passed'] ) ) {
			// 蜜罐命中时返回成功，迷惑机器人。
			if ( 'honeypot' === $check['reason'] ) {
				self::respond( $no_js, true, __( '留言已发送，感谢您的反馈！', 'morn-form-guard' ), 'ok', 200 );
			}

			self::respond( $no_js, false, $check['message'], $check['reason'], 400 );
		}

		// 3. 数据验证。
		$validator = new Morn_Form_Guard_Validator();
		$result    = $validator->validate( $raw );

		if ( empty( $result['valid'] ) ) {
			self::respond(
				$no_js,
				false,
				__( '请检查表单中的错误。', 'morn-form-guard' ),
				'validation_failed',
				400,
				array( 'errors' => $result['errors'] )
			);
		}

		// 4. 存储。
		$meta = array(
			'ip'         => morn_form_guard_get_ip(),
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] )
				? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 300 )
				: '',
			'source'     => isset( $raw['morn_source'] ) ? esc_url_raw( (string) $raw['morn_source'] ) : '',
		);

		$post_id = Morn_Form_Guard_Storage::save_inquiry( $result['data'], $meta );

		if ( is_wp_error( $post_id ) ) {
			self::respond( $no_js, false, __( '保存失败，请稍后重试。', 'morn-form-guard' ), 'save_failed', 500 );
		}

		// 5. 邮件通知（失败不影响留言已保存的事实）。
		$mail_sent = Morn_Form_Guard_Mailer::send( (int) $post_id, $result['data'] );

		/**
		 * 表单提交成功后的动作。
		 *
		 * @param int   $post_id  留言 ID。
		 * @param array $data     留言数据。
		 * @param bool  $mail_sent 是否邮件发送成功。
		 */
		do_action( 'morn_form_guard_submitted', (int) $post_id, $result['data'], $mail_sent );

		$message = __( '留言已发送，感谢您的反馈！', 'morn-form-guard' );

		if ( ! $mail_sent ) {
			$message .= ' ' . __( '（通知邮件发送失败，但您的留言已成功保存）', 'morn-form-guard' );
		}

		self::respond( $no_js, true, $message, 'ok', 200, array( 'postId' => (int) $post_id ) );
	}

	/**
	 * 统一输出响应：无 JS 时重定向，否则返回 JSON。
	 *
	 * @param bool   $no_js   是否为无 JS 降级提交。
	 * @param bool   $success 是否成功。
	 * @param string $message 提示信息。
	 * @param string $code    状态代码。
	 * @param int    $status  HTTP 状态码。
	 * @param array  $extra   附加数据。
	 * @return void
	 */
	private static function respond( $no_js, $success, $message, $code, $status, $extra = array() ) {
		if ( $no_js ) {
			$target = self::get_redirect_target( $success ? 'ok' : ( 'invalid_nonce' === $code ? 'expired' : 'error' ) );
			wp_safe_redirect( $target );
			exit;
		}

		$payload = array_merge(
			array(
				'message' => $message,
				'code'    => $code,
			),
			$extra
		);

		if ( $success ) {
			wp_send_json_success( $payload, $status );
		}

		wp_send_json_error( $payload, $status );
	}

	/**
	 * 获取无 JS 提交的重定向目标。
	 *
	 * @param string $status 状态标记。
	 * @return string
	 */
	private static function get_redirect_target( $status ) {
		$source = '';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce 已在 handle() 开头校验，且此处只做跳转目标白名单检查。
		if ( isset( $_POST['morn_source'] ) ) {
			$source = esc_url_raw( wp_unslash( $_POST['morn_source'] ) );
		}

		// 只允许跳回本站，防止开放重定向。
		$host = wp_parse_url( $source, PHP_URL_HOST );
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( empty( $source ) || $host !== $home_host ) {
			$source = home_url( add_query_arg( array() ) );
		}

		/**
		 * 过滤无 JS 提交后的重定向 URL。
		 *
		 * @param string $source 重定向 URL。
		 * @param string $status 状态标记。
		 */
		$source = apply_filters( 'morn_form_guard_redirect_url', $source, $status );

		return add_query_arg( 'morn_form_sent', sanitize_key( $status ), $source );
	}

	/**
	 * 收集并清理原始输入。
	 *
	 * @return array
	 */
	private static function collect_input() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce 已在 handle() 中校验。
		$raw = array(
			'name'               => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
			'email'              => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
			'subject'            => isset( $_POST['subject'] ) ? wp_unslash( $_POST['subject'] ) : '',
			'message'            => isset( $_POST['message'] ) ? wp_unslash( $_POST['message'] ) : '',
			'phone'              => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '',
			'company'            => isset( $_POST['company'] ) ? wp_unslash( $_POST['company'] ) : '',
			'website'            => isset( $_POST['website'] ) ? wp_unslash( $_POST['website'] ) : '',
			'morn_ts'            => isset( $_POST['morn_ts'] ) ? wp_unslash( $_POST['morn_ts'] ) : 0,
			'morn_source'        => isset( $_POST['morn_source'] ) ? wp_unslash( $_POST['morn_source'] ) : '',
			'morn_captcha'       => isset( $_POST['morn_captcha'] ) ? wp_unslash( $_POST['morn_captcha'] ) : '',
			'morn_captcha_token' => isset( $_POST['morn_captcha_token'] ) ? wp_unslash( $_POST['morn_captcha_token'] ) : '',
			Morn_Form_Guard_Antispam::HONEYPOT_FIELD => isset( $_POST[ Morn_Form_Guard_Antispam::HONEYPOT_FIELD ] )
				? wp_unslash( $_POST[ Morn_Form_Guard_Antispam::HONEYPOT_FIELD ] )
				: '',
			'custom'             => array(),
		);

		if ( isset( $_POST['custom'] ) && is_array( $_POST['custom'] ) ) {
			foreach ( wp_unslash( $_POST['custom'] ) as $key => $value ) {
				// 数组值不参与后续处理，丢弃。
				if ( ! is_scalar( $value ) ) {
					continue;
				}

				$raw['custom'][ sanitize_key( (string) $key ) ] = (string) $value;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		/**
		 * 过滤清理后的原始输入。
		 *
		 * @param array $raw 原始输入。
		 */
		return apply_filters( 'morn_form_guard_raw_input', $raw );
	}
}
