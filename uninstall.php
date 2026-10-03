<?php
/**
 * 卸载清理脚本。
 *
 * 默认保留所有留言数据（设置项「卸载时保留数据」默认为开启）。
 * 仅当用户在插件被删除前明确关闭该开关时，才会删除 morn_inquiry 文章。
 *
 * @package MornRain\MornFormGuard
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 读取设置，判断用户是否选择保留数据。
$morn_fg_settings = get_option( 'morn_form_guard_settings', array() );
$morn_fg_keep     = ! is_array( $morn_fg_settings ) || ! isset( $morn_fg_settings['keep_data'] ) || (int) $morn_fg_settings['keep_data'] === 1;

// 用户选择保留数据：仅清理插件自身配置，留言全部保留。
if ( $morn_fg_keep ) {
	delete_option( 'morn_form_guard_settings' );
	delete_option( 'morn_form_guard_custom_fields' );
	delete_option( 'morn_form_guard_blocked_log' );

	// 清理限流与验证码 transient。
	global $wpdb;

	$morn_fg_like    = $wpdb->esc_like( '_transient_morn_fg_' ) . '%';
	$morn_fg_timeout = $wpdb->esc_like( '_transient_timeout_morn_fg_' ) . '%';

	$morn_fg_rows = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$morn_fg_like,
			$morn_fg_timeout
		)
	);

	if ( is_array( $morn_fg_rows ) ) {
		foreach ( $morn_fg_rows as $morn_fg_option ) {
			delete_option( $morn_fg_option );
		}
	}

	return;
}

// 用户明确选择不保留数据：删除所有留言及其元数据。
delete_option( 'morn_form_guard_settings' );
delete_option( 'morn_form_guard_custom_fields' );
delete_option( 'morn_form_guard_blocked_log' );

global $wpdb;

$morn_fg_like    = $wpdb->esc_like( '_transient_morn_fg_' ) . '%';
$morn_fg_timeout = $wpdb->esc_like( '_transient_timeout_morn_fg_' ) . '%';

$morn_fg_rows = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$morn_fg_like,
		$morn_fg_timeout
	)
);

if ( is_array( $morn_fg_rows ) ) {
	foreach ( $morn_fg_rows as $morn_fg_option ) {
		delete_option( $morn_fg_option );
	}
}

$morn_fg_ids = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
		'morn_inquiry'
	)
);

if ( is_array( $morn_fg_ids ) ) {
	foreach ( $morn_fg_ids as $morn_fg_id ) {
		wp_delete_post( (int) $morn_fg_id, true );
	}
}

// 删除留言的全部元字段。
$morn_fg_meta_sql = $wpdb->prepare(
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
	$wpdb->esc_like( '_morn_' ) . '%'
);

$wpdb->query( $morn_fg_meta_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- 上面已 prepare。
