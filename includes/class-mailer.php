<?php
/**
 * 邮件通知。
 *
 * @package MornRain\MornFormGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 使用 wp_mail 发送通知。
 */
class Morn_Form_Guard_Mailer {

	/**
	 * 发送留言通知。
	 *
	 * @param int   $post_id 留言 ID。
	 * @param array $data    留言数据。
	 * @return bool
	 */
	public static function send( $post_id, $data ) {
		$settings = Morn_Form_Guard_Storage::get_settings();

		if ( empty( $settings['notify_enabled'] ) ) {
			return false;
		}

		$recipient = isset( $settings['recipient'] ) ? trim( (string) $settings['recipient'] ) : '';

		if ( '' === $recipient || ! is_email( $recipient ) ) {
			$recipient = get_option( 'admin_email' );
		}

		if ( ! is_email( $recipient ) ) {
			return false;
		}

		$subject_template = isset( $settings['email_subject'] ) ? (string) $settings['email_subject'] : '';
		$subject = self::replace_placeholders( $subject_template, $data );

		if ( '' === trim( $subject ) ) {
			$subject = __( '新表单留言', 'morn-form-guard' );
		}

		$use_html = ! empty( $settings['mail_html'] );

		if ( $use_html ) {
			$message = self::build_html( $data, $post_id );
			$content_type = 'text/html';
			$headers      = array( 'Content-Type: text/html; charset=UTF-8' );
		} else {
			$message      = self::build_text( $data, $post_id );
			$content_type = 'text/plain';
			$headers      = array( 'Content-Type: text/plain; charset=UTF-8' );
		}

		$from_name = isset( $settings['email_from_name'] ) && '' !== trim( (string) $settings['email_from_name'] )
			? (string) $settings['email_from_name']
			: get_bloginfo( 'name' );

		// From 地址必须使用站点域名，否则多数主机判定为伪造。
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( empty( $host ) ) {
			$host = 'localhost';
		}

		$from_email = 'no-reply@' . $host;

		if ( ! is_email( $from_email ) ) {
			$from_email = get_option( 'admin_email' );
		}

		$from = $from_name . ' <' . $from_email . '>';

		if ( ! empty( $settings['email_reply_to'] ) && ! empty( $data['email'] ) && is_email( $data['email'] ) ) {
			$headers[] = 'Reply-To: ' . $data['email'];
		}

		/**
		 * 过滤通知邮件的最终参数。
		 *
		 * @param array  $args    邮件参数。
		 * @param int    $post_id 留言 ID。
		 * @param array  $data    留言数据。
		 * @param string $content_type 内容类型。
		 */
		$args = apply_filters(
			'morn_form_guard_mail_args',
			array(
				'to'          => $recipient,
				'subject'     => $subject,
				'body'        => $message,
				'headers'     => $headers,
				'attachments' => array(),
				'from'        => $from,
			),
			$post_id,
			$data,
			$content_type
		);

		$subject = self::replace_placeholders( isset( $args['subject'] ) ? (string) $args['subject'] : $subject, $data );
		$body    = isset( $args['body'] ) ? (string) $args['body'] : $message;

		$sent = wp_mail(
			$args['to'],
			$subject,
			$body,
			$args['headers'],
			$args['attachments']
		);

		/**
		 * 留言通知发送结果。
		 *
		 * @param bool   $sent    是否发送成功。
		 * @param int    $post_id 留言 ID。
		 * @param array  $data    留言数据。
		 */
		do_action( 'morn_form_guard_mail_sent', $sent, $post_id, $data );

		return (bool) $sent;
	}

	/**
	 * 替换邮件模板中的占位符。
	 *
	 * @param string $template 模板。
	 * @param array  $data     留言数据。
	 * @return string
	 */
	private static function replace_placeholders( $template, $data ) {
		$map = array(
			'{subject}' => isset( $data['subject'] ) ? (string) $data['subject'] : '',
			'{name}'    => isset( $data['name'] ) ? (string) $data['name'] : '',
			'{email}'   => isset( $data['email'] ) ? (string) $data['email'] : '',
			'{site}'    => get_bloginfo( 'name' ),
			'{date}'    => date_i18n( 'Y-m-d H:i' ),
		);

		return strtr( (string) $template, $map );
	}

	/**
	 * 构造 HTML 邮件正文。
	 *
	 * @param array $data    留言数据。
	 * @param int   $post_id 留言 ID。
	 * @return string
	 */
	private static function build_html( $data, $post_id ) {
		$rows = self::collect_rows( $data, $post_id );

		$html  = '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif;max-width:640px;margin:0 auto;padding:20px;">';
		$html .= '<h2 style="margin:0 0 16px;font-size:18px;color:#1d2327;">';
		$html .= esc_html( sprintf( /* translators: %s: 站点名。 */ __( '%s 收到一条新留言', 'morn-form-guard' ), get_bloginfo( 'name' ) ) );
		$html .= '</h2>';
		$html .= '<table cellpadding="8" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;font-size:14px;color:#1d2327;">';

		foreach ( $rows as $label => $value ) {
			$html .= '<tr>';
			$html .= '<th align="left" width="120" style="background:#f6f7f7;border:1px solid #dcdcde;font-weight:600;">' . esc_html( $label ) . '</th>';
			$html .= '<td style="border:1px solid #dcdcde;word-break:break-word;">' . nl2br( esc_html( $value ) ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</table>';

		$edit_link = get_edit_post_link( $post_id, 'raw' );

		if ( $edit_link ) {
			$html .= '<p style="margin:16px 0 0;font-size:13px;">';
			$html .= '<a href="' . esc_url( $edit_link ) . '" style="color:#2271b1;">' . esc_html__( '在后台查看此留言', 'morn-form-guard' ) . '</a>';
			$html .= '</p>';
		}

		$html .= '</div>';

		/**
		 * 过滤 HTML 邮件正文。
		 *
		 * @param string $html    HTML 内容。
		 * @param array  $rows    字段行数组。
		 * @param int    $post_id 留言 ID。
		 */
		return apply_filters( 'morn_form_guard_mail_html', $html, $rows, $post_id );
	}

	/**
	 * 构造纯文本邮件正文。
	 *
	 * @param array $data    留言数据。
	 * @param int   $post_id 留言 ID。
	 * @return string
	 */
	private static function build_text( $data, $post_id ) {
		$rows   = self::collect_rows( $data, $post_id );
		$output = '';

		foreach ( $rows as $label => $value ) {
			$output .= $label . ': ' . $value . "\n";
		}

		$edit_link = get_edit_post_link( $post_id, 'raw' );

		if ( $edit_link ) {
			$output .= "\n" . $edit_link;
		}

		return $output;
	}

	/**
	 * 收集邮件中的字段行。
	 *
	 * @param array $data    留言数据。
	 * @param int   $post_id 留言 ID。
	 * @return array
	 */
	private static function collect_rows( $data, $post_id ) {
		$rows = array(
			__( '姓名', 'morn-form-guard' )   => isset( $data['name'] ) ? $data['name'] : '',
			__( '邮箱', 'morn-form-guard' )   => isset( $data['email'] ) ? $data['email'] : '',
			__( '主题', 'morn-form-guard' )   => isset( $data['subject'] ) ? $data['subject'] : '',
		);

		if ( ! empty( $data['phone'] ) ) {
			$rows[ __( '电话', 'morn-form-guard' ) ] = $data['phone'];
		}

		if ( ! empty( $data['company'] ) ) {
			$rows[ __( '公司', 'morn-form-guard' ) ] = $data['company'];
		}

		if ( ! empty( $data['website'] ) ) {
			$rows[ __( '网站', 'morn-form-guard' ) ] = $data['website'];
		}

		$rows[ __( '留言内容', 'morn-form-guard' ) ] = isset( $data['message'] ) ? wp_strip_all_tags( $data['message'] ) : '';

		if ( ! empty( $data['custom'] ) && is_array( $data['custom'] ) ) {
			foreach ( $data['custom'] as $key => $value ) {
				$rows[ morn_form_guard_get_field_label( $key ) ] = $value;
			}
		}

		$rows[ __( 'IP 地址', 'morn-form-guard' ) ] = morn_form_guard_get_ip();
		$rows[ __( '提交时间', 'morn-form-guard' ) ] = date_i18n( 'Y-m-d H:i:s' );

		/**
		 * 过滤邮件字段行。
		 *
		 * @param array $rows    字段行数组。
		 * @param array $data    留言数据。
		 * @param int   $post_id 留言 ID。
		 */
		return apply_filters( 'morn_form_guard_mail_rows', $rows, $data, $post_id );
	}
}
