<?php
/**
 * 插件协调层。
 *
 * @package MornRain\MornFormGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 挂载全局钩子并处理无 AJAX 降级提交。
 */
class Morn_Form_Guard_Plugin {

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public static function init() {
		// 未读留言数量提示。
		add_filter( 'add_menu_classes', array( __CLASS__, 'menu_bubble' ) );

		// 无 JS 提交成功后展示结果提示。
		add_action( 'wp_body_open', array( __CLASS__, 'render_notice' ) );
	}

	/**
	 * 无 JS 提交成功后展示提示信息。
	 *
	 * 前端脚本会把响应状态写入 URL 查询参数；无 JS 时表单直接 POST 到
	 * admin-ajax.php，由 Morn_Form_Guard_Ajax 重定向回本页并附带此参数。
	 *
	 * @return void
	 */
	public static function render_notice() {
		if ( ! isset( $_GET['morn_form_sent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 仅用于显示提示。
			return;
		}

		$status = sanitize_key( wp_unslash( $_GET['morn_form_sent'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$notices = array(
			'ok'      => array( 'success', __( '留言已发送，感谢您的反馈！', 'morn-form-guard' ) ),
			'error'   => array( 'error', __( '留言提交失败，请稍后重试。', 'morn-form-guard' ) ),
			'expired' => array( 'error', __( '表单已过期，请刷新页面后重试。', 'morn-form-guard' ) ),
		);

		if ( ! isset( $notices[ $status ] ) ) {
			return;
		}

		printf(
			'<div class="morn-form-notice %1$s" role="status">%2$s</div>',
			esc_attr( 'morn-form-notice-' . $status ),
			esc_html( $notices[ $status ][1] )
		);
	}

	/**
	 * 在后台菜单上显示未读留言数量。
	 *
	 * @param array $menu 菜单数组。
	 * @return array
	 */
	public static function menu_bubble( $menu ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $menu;
		}

		$unread = self::count_unread();

		if ( $unread <= 0 ) {
			return $menu;
		}

		foreach ( $menu as $key => $item ) {
			if ( isset( $item[2] ) && 'edit.php?post_type=' . MORN_FORM_GUARD_CPT === $item[2] ) {
				$menu[ $key ][0] .= sprintf(
					' <span class="awaiting-mod"><span class="pending-count">%d</span></span>',
					$unread
				);
				break;
			}
		}

		return $menu;
	}

	/**
	 * 统计未读留言数量。
	 *
	 * @return int
	 */
	public static function count_unread() {
		$query = new WP_Query(
			array(
				'post_type'      => MORN_FORM_GUARD_CPT,
				'post_status'    => array( 'private', 'publish', 'draft' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- 留言量级小，且需要按元值筛选。
					array(
						'key'     => '_morn_read',
						'value'   => '1',
						'compare' => '!=',
					),
				),
			)
		);

		return (int) $query->found_posts;
	}
}
