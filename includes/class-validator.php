<?php
/**
 * 表单数据验证。
 *
 * @package MornRain\MornFormGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 前后端双重验证的规则实现。
 */
class Morn_Form_Guard_Validator {

	/**
	 * 验证结果。
	 *
	 * @var array
	 */
	private $errors = array();

	/**
	 * 清理后的数据。
	 *
	 * @var array
	 */
	private $data = array();

	/**
	 * 验证原始提交数据。
	 *
	 * @param array $raw 原始数据。
	 * @return array
	 */
	public function validate( $raw ) {
		$this->errors = array();
		$this->data   = array(
			'name'    => '',
			'email'   => '',
			'subject' => '',
			'message' => '',
			'phone'   => '',
			'company' => '',
			'website' => '',
			'custom'  => array(),
		);

		$raw = is_array( $raw ) ? $raw : array();

		$this->validate_name( $raw );
		$this->validate_email( $raw );
		$this->validate_subject( $raw );
		$this->validate_message( $raw );
		$this->validate_optional( $raw );
		$this->validate_custom( $raw );

		$result = array(
			'valid'  => empty( $this->errors ),
			'errors' => $this->errors,
			'data'   => $this->data,
		);

		/**
		 * 过滤验证结果。
		 *
		 * @param array $result 验证结果。
		 * @param array $raw    原始数据。
		 */
		return apply_filters( 'morn_form_guard_validation_result', $result, $raw );
	}

	/**
	 * 验证姓名。
	 *
	 * @param array $raw 原始数据。
	 * @return void
	 */
	private function validate_name( $raw ) {
		$name = isset( $raw['name'] ) ? sanitize_text_field( (string) $raw['name'] ) : '';

		if ( '' === $name ) {
			$this->add_error( 'name', __( '请填写姓名。', 'morn-form-guard' ) );

			return;
		}

		if ( self::strlen( $name ) > 60 ) {
			$this->add_error( 'name', __( '姓名不能超过 60 个字符。', 'morn-form-guard' ) );

			return;
		}

		$this->data['name'] = $name;
	}

	/**
	 * 验证邮箱。
	 *
	 * @param array $raw 原始数据。
	 * @return void
	 */
	private function validate_email( $raw ) {
		$email = isset( $raw['email'] ) ? sanitize_email( (string) $raw['email'] ) : '';

		if ( '' === $email ) {
			$this->add_error( 'email', __( '请填写邮箱。', 'morn-form-guard' ) );

			return;
		}

		if ( ! is_email( $email ) ) {
			$this->add_error( 'email', __( '邮箱格式不正确。', 'morn-form-guard' ) );

			return;
		}

		$this->data['email'] = $email;
	}

	/**
	 * 验证主题。
	 *
	 * @param array $raw 原始数据。
	 * @return void
	 */
	private function validate_subject( $raw ) {
		$subject = isset( $raw['subject'] ) ? sanitize_text_field( (string) $raw['subject'] ) : '';

		if ( '' === $subject ) {
			$this->add_error( 'subject', __( '请填写主题。', 'morn-form-guard' ) );

			return;
		}

		if ( self::strlen( $subject ) > 200 ) {
			$this->add_error( 'subject', __( '主题不能超过 200 个字符。', 'morn-form-guard' ) );

			return;
		}

		$this->data['subject'] = $subject;
	}

	/**
	 * 验证留言内容。
	 *
	 * @param array $raw 原始数据。
	 * @return void
	 */
	private function validate_message( $raw ) {
		$message = isset( $raw['message'] ) ? wp_kses_post( (string) $raw['message'] ) : '';
		$message = trim( $message );

		if ( '' === $message ) {
			$this->add_error( 'message', __( '请填写留言内容。', 'morn-form-guard' ) );

			return;
		}

		if ( self::strlen( $message ) < 5 ) {
			$this->add_error( 'message', __( '留言内容太短。', 'morn-form-guard' ) );

			return;
		}

		if ( self::strlen( $message ) > 5000 ) {
			$this->add_error( 'message', __( '留言内容不能超过 5000 个字符。', 'morn-form-guard' ) );

			return;
		}

		$this->data['message'] = $message;
	}

	/**
	 * 验证可选字段。
	 *
	 * @param array $raw 原始数据。
	 * @return void
	 */
	private function validate_optional( $raw ) {
		if ( isset( $raw['phone'] ) ) {
			$phone = sanitize_text_field( (string) $raw['phone'] );

			if ( '' !== $phone ) {
				// 允许数字、空格、连字符、括号与加号。
				$clean = preg_replace( '/[^0-9\s\-()+]/', '', $phone );

				if ( self::strlen( $clean ) > 30 ) {
					$this->add_error( 'phone', __( '电话号码过长。', 'morn-form-guard' ) );
				} else {
					$this->data['phone'] = $clean;
				}
			}
		}

		if ( isset( $raw['company'] ) ) {
			$company = sanitize_text_field( (string) $raw['company'] );

			if ( self::strlen( $company ) > 120 ) {
				$this->add_error( 'company', __( '公司名称过长。', 'morn-form-guard' ) );
			} else {
				$this->data['company'] = $company;
			}
		}

		if ( isset( $raw['website'] ) ) {
			$website = esc_url_raw( trim( (string) $raw['website'] ) );

			if ( '' !== $website ) {
				$this->data['website'] = $website;
			}
		}
	}

	/**
	 * 验证自定义字段。
	 *
	 * @param array $raw 原始数据。
	 * @return void
	 */
	private function validate_custom( $raw ) {
		if ( empty( $raw['custom'] ) || ! is_array( $raw['custom'] ) ) {
			return;
		}

		$count = 0;

		foreach ( $raw['custom'] as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key ) {
				continue;
			}

			$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';

			if ( '' === $value ) {
				continue;
			}

			if ( self::strlen( $value ) > 300 ) {
				$this->add_error( 'custom_' . $key, __( '自定义字段内容过长。', 'morn-form-guard' ) );
				continue;
			}

			$this->data['custom'][ $key ] = $value;
			$count++;

			if ( $count >= 20 ) {
				break;
			}
		}
	}

	/**
	 * 记录一条错误。
	 *
	 * @param string $field   字段名。
	 * @param string $message 错误信息。
	 * @return void
	 */
	private function add_error( $field, $message ) {
		if ( ! isset( $this->errors[ $field ] ) ) {
			$this->errors[ $field ] = $message;
		}
	}

	/**
	 * 多字节安全的字符串长度。
	 *
	 * @param string $value 字符串。
	 * @return int
	 */
	public static function strlen( $value ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( (string) $value, 'UTF-8' );
		}

		return strlen( (string) $value );
	}

	/**
	 * 获取客户端 HTML5 验证规则的 JSON 配置。
	 *
	 * @return array
	 */
	public static function get_client_rules() {
		$rules = array(
			'name'    => array(
				'required' => true,
				'min'      => 1,
				'max'      => 60,
			),
			'email'   => array(
				'required' => true,
			),
			'subject' => array(
				'required' => true,
				'min'      => 1,
				'max'      => 200,
			),
			'message' => array(
				'required' => true,
				'min'      => 5,
				'max'      => 5000,
			),
		);

		/**
		 * 过滤前端验证规则。
		 *
		 * @param array $rules 规则数组。
		 */
		return apply_filters( 'morn_form_guard_client_rules', $rules );
	}
}
