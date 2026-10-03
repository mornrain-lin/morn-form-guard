<?php
/**
 * 反垃圾与限流。
 *
 * @package MornRain\MornFormGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 蜜罐、限流、黑名单、验证码与链接检测。
 */
class Morn_Form_Guard_Antispam {

	/**
	 * 蜜罐字段名。
	 */
	const HONEYPOT_FIELD = 'morn_hp';

	/**
	 * 限流 transient 前缀。
	 */
	const RATE_PREFIX = 'morn_fg_rate_';

	/**
	 * 执行全部反垃圾检查。
	 *
	 * @param array $raw 原始提交数据。
	 * @return array
	 */
	public static function check( $raw ) {
		$raw    = is_array( $raw ) ? $raw : array();
		$config = morn_form_guard_get_antispam_config();
		$ip     = morn_form_guard_get_ip();

		// 1. IP 黑名单。
		if ( self::is_ip_blocked( $ip ) ) {
			return self::result( false, __( '您的 IP 已被列入黑名单，无法提交。', 'morn-form-guard' ), 'ip_blacklist' );
		}

		// 2. 蜜罐字段必须为空。
		if ( ! empty( $config['honeypot'] ) ) {
			$honeypot = isset( $raw[ self::HONEYPOT_FIELD ] ) ? trim( (string) $raw[ self::HONEYPOT_FIELD ] ) : '';

			if ( '' !== $honeypot ) {
				// 记录并直接判定为垃圾。
				self::log( $ip, 'honeypot' );

				return self::result( false, __( '提交被判定为垃圾信息。', 'morn-form-guard' ), 'honeypot' );
			}
		}

		// 3. 提交时间间隔。
		$min_seconds = (int) $config['min_seconds'];

		if ( $min_seconds > 0 ) {
			$timestamp = isset( $raw['morn_ts'] ) ? absint( $raw['morn_ts'] ) : 0;

			if ( $timestamp <= 0 ) {
				return self::result( false, __( '表单已过期，请刷新页面重试。', 'morn-form-guard' ), 'timestamp' );
			}

			$elapsed = time() - $timestamp;

			if ( $elapsed < $min_seconds ) {
				return self::result( false, __( '提交速度过快，请稍候再试。', 'morn-form-guard' ), 'too_fast' );
			}

			// 时间戳过旧（超过 24 小时）视为无效。
			if ( $elapsed > DAY_IN_SECONDS ) {
				return self::result( false, __( '表单已过期，请刷新页面重试。', 'morn-form-guard' ), 'timestamp_expired' );
			}
		}

		// 4. 算术验证码。
		if ( ! empty( $config['captcha'] ) ) {
			$answer = isset( $raw['morn_captcha'] ) ? trim( (string) $raw['morn_captcha'] ) : '';
			$token  = isset( $raw['morn_captcha_token'] ) ? sanitize_text_field( (string) $raw['morn_captcha_token'] ) : '';

			if ( ! self::verify_captcha( $token, $answer ) ) {
				self::log( $ip, 'captcha' );

				return self::result( false, __( '验证码答案不正确。', 'morn-form-guard' ), 'captcha' );
			}
		}

		// 5. 关键词黑名单。
		$haystack = self::build_haystack( $raw );

		if ( ! empty( $config['keyword_filter'] ) ) {
			if ( self::has_blacklisted_keyword( $haystack ) ) {
				self::log( $ip, 'keyword' );

				return self::result( false, __( '内容包含不允许提交的关键词。', 'morn-form-guard' ), 'keyword' );
			}
		}

		// 6. 链接数量限制。
		$max_links = (int) $config['max_links'];

		if ( $max_links > 0 ) {
			$link_count = self::count_links( $haystack );

			if ( $link_count > $max_links ) {
				self::log( $ip, 'links' );

				return self::result(
					false,
					sprintf(
						/* translators: %d: 允许的最大链接数。 */
						__( '内容中包含过多链接（最多允许 %d 个），已被拒绝。', 'morn-form-guard' ),
						$max_links
					),
					'too_many_links'
				);
			}
		}

		// 7. 提交频率限制。
		if ( ! empty( $config['rate_limit'] ) ) {
			$max_count = (int) Morn_Form_Guard_Storage::get_setting( 'rate_limit_count', 3 );
			$window    = max( 60, (int) Morn_Form_Guard_Storage::get_setting( 'rate_limit_window', 600 ) );
			$transient = self::RATE_PREFIX . md5( $ip );
			$count     = (int) get_transient( $transient );

			if ( $count >= $max_count ) {
				return self::result(
					false,
					sprintf(
						/* translators: %d: 分钟数。 */
						__( '提交过于频繁，请在 %d 分钟后再试。', 'morn-form-guard' ),
						(int) ceil( $window / 60 )
					),
					'rate_limit'
				);
			}

			// 校验通过后才计数。
			set_transient( $transient, $count + 1, $window );
		}

		return self::result( true, '', '' );
	}

	/**
	 * 构造统一的结果结构。
	 *
	 * @param bool   $passed  是否通过。
	 * @param string $message 提示信息。
	 * @param string $reason  失败原因代码。
	 * @return array
	 */
	private static function result( $passed, $message = '', $reason = '' ) {
		/**
		 * 过滤反垃圾检查结果。
		 *
		 * @param array  $result  结果数组。
		 * @param string $reason  原因代码。
		 * @param string $message 提示信息。
		 */
		return apply_filters(
			'morn_form_guard_antispam_result',
			array(
				'passed'  => (bool) $passed,
				'message' => $message,
				'reason'  => $reason,
			),
			$reason,
			$message
		);
	}

	/**
	 * 拼接待检测的文本。
	 *
	 * @param array $raw 原始数据。
	 * @return string
	 */
	private static function build_haystack( $raw ) {
		$parts = array();

		foreach ( array( 'name', 'email', 'subject', 'message', 'phone', 'company', 'website' ) as $key ) {
			if ( isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ) {
				$parts[] = (string) $raw[ $key ];
			}
		}

		if ( ! empty( $raw['custom'] ) && is_array( $raw['custom'] ) ) {
			foreach ( $raw['custom'] as $value ) {
				if ( is_scalar( $value ) ) {
					$parts[] = (string) $value;
				}
			}
		}

		return implode( "\n", $parts );
	}

	/**
	 * 判断是否包含黑名单关键词。
	 *
	 * @param string $text 待检测文本。
	 * @return bool
	 */
	private static function has_blacklisted_keyword( $text ) {
		$settings = Morn_Form_Guard_Storage::get_settings();
		$raw_list = isset( $settings['keyword_blacklist'] ) ? (string) $settings['keyword_blacklist'] : '';

		$keywords = preg_split( '/\r\n|\r|\n/', $raw_list );
		$found    = false;

		foreach ( (array) $keywords as $keyword ) {
			$keyword = trim( (string) $keyword );

			if ( '' === $keyword ) {
				continue;
			}

			if ( function_exists( 'mb_stripos' ) ) {
				if ( false !== mb_stripos( $text, $keyword, 0, 'UTF-8' ) ) {
					$found = true;
					break;
				}
				continue;
			}

			if ( false !== stripos( $text, $keyword ) ) {
				$found = true;
				break;
			}
		}

		/**
		 * 过滤关键词黑名单判定结果。
		 *
		 * @param bool   $found   是否命中。
		 * @param string $text    待检测文本。
		 * @param string $raw_list 原始黑名单配置。
		 */
		return (bool) apply_filters( 'morn_form_guard_keyword_match', $found, $text, $raw_list );
	}

	/**
	 * 统计文本中的链接数量。
	 *
	 * @param string $text 待检测文本。
	 * @return int
	 */
	public static function count_links( $text ) {
		$text = (string) $text;
		$count = 0;

		// http/https 链接。
		$count += preg_match_all( '#https?://#i', $text );

		// www. 开头的裸域名。
		$count += preg_match_all( '#\bwww\.#i', $text );

		// 常见 TLD 裸域名，如 example.com/path。
		$count += preg_match_all( '#\b[a-z0-9\-]+\.(com|net|org|cn|io|co|info|xyz|top|site|shop|club|online)\b#i', $text );

		return (int) $count;
	}

	/**
	 * 生成算术验证码。
	 *
	 * 答案存于 transient，10 分钟内有效。
	 *
	 * @return array
	 */
	public static function generate_captcha() {
		$a = wp_rand( 1, 9 );
		$b = wp_rand( 1, 9 );

		// 交替加法与减法，保证结果非负。
		$use_add = (bool) wp_rand( 0, 1 );

		if ( $use_add ) {
			$question = sprintf( '%d + %d = ?', $a, $b );
			$answer   = $a + $b;
		} else {
			$high = max( $a, $b );
			$low  = min( $a, $b );
			$question = sprintf( '%d − %d = ?', $high, $low );
			$answer   = $high - $low;
		}

		$token = wp_generate_password( 12, false, false );

		set_transient( self::RATE_PREFIX . 'cap_' . md5( $token ), (string) $answer, 10 * MINUTE_IN_SECONDS );

		return array(
			'token'   => $token,
			'question' => $question,
		);
	}

	/**
	 * 验证算术验证码。
	 *
	 * @param string $token  验证码令牌。
	 * @param string $answer 用户答案。
	 * @return bool
	 */
	public static function verify_captcha( $token, $answer ) {
		$token = sanitize_text_field( (string) $token );

		if ( '' === $token ) {
			return false;
		}

		$key    = self::RATE_PREFIX . 'cap_' . md5( $token );
		$stored = get_transient( $key );

		if ( false === $stored || '' === $stored ) {
			return false;
		}

		// 一次性使用，无论成功与否都清除。
		delete_transient( $key );

		$answer = trim( (string) $answer );

		return (string) (int) $answer === (string) (int) $stored;
	}

	/**
	 * 判断 IP 是否在黑名单中。
	 *
	 * @param string $ip IP 地址。
	 * @return bool
	 */
	public static function is_ip_blocked( $ip ) {
		$settings = Morn_Form_Guard_Storage::get_settings();
		$raw_list = isset( $settings['ip_blacklist'] ) ? (string) $settings['ip_blacklist'] : '';

		$ips = preg_split( '/\r\n|\r|\n/', $raw_list );
		$found = false;

		foreach ( (array) $ips as $entry ) {
			$entry = trim( (string) $entry );

			if ( '' === $entry ) {
				continue;
			}

			// 支持 192.168.1.* 通配。
			if ( substr( $entry, -1 ) === '*' ) {
				$prefix = substr( $entry, 0, -1 );

				if ( 0 === strpos( $ip, $prefix ) ) {
					$found = true;
					break;
				}
				continue;
			}

			if ( $entry === $ip ) {
				$found = true;
				break;
			}
		}

		/**
		 * 过滤 IP 黑名单判定结果。
		 *
		 * @param bool   $found 是否命中。
		 * @param string $ip    IP 地址。
		 * @param string $raw_list 原始黑名单配置。
		 */
		return (bool) apply_filters( 'morn_form_guard_ip_blocked', $found, $ip, $raw_list );
	}

	/**
	 * 记录一次垃圾提交尝试。
	 *
	 * @param string $ip     IP 地址。
	 * @param string $reason 原因。
	 * @return void
	 */
	private static function log( $ip, $reason ) {
		$log = get_option( 'morn_form_guard_blocked_log', array() );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$log[] = array(
			'time'   => time(),
			'ip'     => $ip,
			'reason' => $reason,
		);

		// 只保留最近 200 条。
		if ( count( $log ) > 200 ) {
			$log = array_slice( $log, -200 );
		}

		update_option( 'morn_form_guard_blocked_log', $log, false );
	}

	/**
	 * 获取被拦截的日志。
	 *
	 * @param int $limit 返回条数。
	 * @return array
	 */
	public static function get_blocked_log( $limit = 50 ) {
		$log = get_option( 'morn_form_guard_blocked_log', array() );

		if ( ! is_array( $log ) ) {
			return array();
		}

		return array_slice( $log, -1 * max( 1, (int) $limit ) );
	}
}
