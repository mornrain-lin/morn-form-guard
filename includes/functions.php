<?php
/**
 * 插件通用辅助函数。
 *
 * @package MornRain\MornFormGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 获取访客 IP 地址。
 *
 * 仅在非 CLI 环境下读取 REMOTE_ADDR，不信任任何可伪造的代理头，
 * 避免攻击者通过伪造 X-Forwarded-For 绕过 IP 黑名单。
 *
 * @return string
 */
function morn_form_guard_get_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	$valid = filter_var( $ip, FILTER_VALIDATE_IP );

	if ( false === $valid ) {
		return '0.0.0.0';
	}

	/**
	 * 过滤识别到的访客 IP。
	 *
	 * @param string $ip IP 地址。
	 */
	return apply_filters( 'morn_form_guard_client_ip', $valid );
}

/**
 * 生成表单字段定义。
 *
 * @return array
 */
function morn_form_guard_get_default_fields() {
	return array(
		array(
			'name'     => 'name',
			'label'    => __( '姓名', 'morn-form-guard' ),
			'type'     => 'text',
			'required' => 1,
			'width'    => 'half',
		),
		array(
			'name'     => 'email',
			'label'    => __( '邮箱', 'morn-form-guard' ),
			'type'     => 'email',
			'required' => 1,
			'width'    => 'half',
		),
		array(
			'name'     => 'subject',
			'label'    => __( '主题', 'morn-form-guard' ),
			'type'     => 'text',
			'required' => 1,
			'width'    => 'full',
		),
		array(
			'name'     => 'message',
			'label'    => __( '留言', 'morn-form-guard' ),
			'type'     => 'textarea',
			'required' => 1,
			'width'    => 'full',
		),
	);
}

/**
 * 获取内置字段的标签。
 *
 * @param string $name 字段名。
 * @return string
 */
function morn_form_guard_get_field_label( $name ) {
	$builtin = array(
		'name'    => __( '姓名', 'morn-form-guard' ),
		'email'   => __( '邮箱', 'morn-form-guard' ),
		'subject' => __( '主题', 'morn-form-guard' ),
		'message' => __( '留言', 'morn-form-guard' ),
		'phone'   => __( '电话', 'morn-form-guard' ),
		'company' => __( '公司', 'morn-form-guard' ),
		'website' => __( '网站', 'morn-form-guard' ),
	);

	return isset( $builtin[ $name ] ) ? $builtin[ $name ] : ucfirst( str_replace( '_', ' ', (string) $name ) );
}

/**
 * 格式化时间戳为本地时间字符串。
 *
 * @param string $gmt_time GMT 时间。
 * @return string
 */
function morn_form_guard_format_time( $gmt_time ) {
	$timestamp = strtotime( $gmt_time . ' UTC' );

	if ( ! $timestamp ) {
		return '';
	}

	return date_i18n( 'Y-m-d H:i:s', $timestamp + ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
}

/**
 * 获取防刷强度等级的配置。
 *
 * @return array
 */
function morn_form_guard_get_strength_levels() {
	return array(
		'low'    => array(
			'label'           => __( '低（仅蜜罐 + 频率限制）', 'morn-form-guard' ),
			'honeypot'         => 1,
			'rate_limit'       => 1,
			'captcha'          => 0,
			'min_seconds'      => 0,
			'max_links'        => 5,
			'keyword_filter'   => 0,
		),
		'medium' => array(
			'label'           => __( '中（+ 时间戳 + 关键词过滤）', 'morn-form-guard' ),
			'honeypot'         => 1,
			'rate_limit'       => 1,
			'captcha'          => 0,
			'min_seconds'      => 3,
			'max_links'        => 3,
			'keyword_filter'   => 1,
		),
		'high'   => array(
			'label'           => __( '高（+ 算术验证码 + 严格链接限制）', 'morn-form-guard' ),
			'honeypot'         => 1,
			'rate_limit'       => 1,
			'captcha'          => 1,
			'min_seconds'      => 5,
			'max_links'        => 1,
			'keyword_filter'   => 1,
		),
	);
}

/**
 * 根据强度等级展开防刷配置。
 *
 * @return array
 */
function morn_form_guard_get_antispam_config() {
	$settings = Morn_Form_Guard_Storage::get_settings();
	$levels   = morn_form_guard_get_strength_levels();
	$level    = isset( $settings['strength'] ) ? $settings['strength'] : 'medium';

	if ( ! isset( $levels[ $level ] ) ) {
		$level = 'medium';
	}

	$config = $levels[ $level ];

	// 单项开关可覆盖等级默认值。
	$config['honeypot']       = ! empty( $settings['enable_honeypot'] ) ? 1 : 0;
	$config['rate_limit']     = ! empty( $settings['enable_rate_limit'] ) ? 1 : 0;
	$config['captcha']        = ! empty( $settings['enable_captcha'] ) ? 1 : 0;
	$config['keyword_filter'] = ! empty( $settings['enable_keyword_filter'] ) ? 1 : 0;
	$config['min_seconds']    = isset( $settings['min_seconds'] ) ? (int) $settings['min_seconds'] : $config['min_seconds'];
	$config['max_links']      = isset( $settings['max_links'] ) ? (int) $settings['max_links'] : $config['max_links'];

	return $config;
}
