<?php
/**
 * Plugin Name: Morn Form Guard
 * Plugin URI: https://github.com/mornrain-lin/morn-form-guard
 * Description: 纯前端短代码联系表单，自带安全防护体系。含 nonce + 时间戳校验、Honeypot 蜜罐、IP 提交频率限制、IP 黑名单、关键词黑名单、Math Captcha 算术题、链接数量检测，存储为自定义文章类型并支持后台查看与 CSV 导出。零外部资源。
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 6.6
 * Author: MornRain
 * Author URI: https://github.com/mornrain-lin
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: morn-form-guard
 * Domain Path: /languages
 *
 * @package MornRain\MornFormGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 插件版本。
 */
define( 'MORN_FORM_GUARD_VERSION', '1.0.0' );

/**
 * 主文件路径。
 */
define( 'MORN_FORM_GUARD_FILE', __FILE__ );

/**
 * 插件目录路径。
 */
define( 'MORN_FORM_GUARD_DIR', plugin_dir_path( __FILE__ ) );

/**
 * 插件目录 URL。
 */
define( 'MORN_FORM_GUARD_URL', plugin_dir_url( __FILE__ ) );

/**
 * 设置选项名。
 */
define( 'MORN_FORM_GUARD_OPTION', 'morn_form_guard_settings' );

/**
 * 存储用的自定义文章类型。
 */
define( 'MORN_FORM_GUARD_CPT', 'morn_inquiry' );

require_once MORN_FORM_GUARD_DIR . 'includes/functions.php';
require_once MORN_FORM_GUARD_DIR . 'includes/class-storage.php';
require_once MORN_FORM_GUARD_DIR . 'includes/class-validator.php';
require_once MORN_FORM_GUARD_DIR . 'includes/class-antispam.php';
require_once MORN_FORM_GUARD_DIR . 'includes/class-mailer.php';
require_once MORN_FORM_GUARD_DIR . 'includes/class-form.php';
require_once MORN_FORM_GUARD_DIR . 'includes/class-ajax.php';
require_once MORN_FORM_GUARD_DIR . 'includes/class-admin.php';
require_once MORN_FORM_GUARD_DIR . 'includes/class-plugin.php';

/**
 * 启动插件。
 *
 * @return void
 */
function morn_form_guard_boot() {
	load_plugin_textdomain( 'morn-form-guard', false, dirname( plugin_basename( MORN_FORM_GUARD_FILE ) ) . '/languages' );

	Morn_Form_Guard_Storage::init();
	Morn_Form_Guard_Form::init();
	Morn_Form_Guard_Ajax::init();
	Morn_Form_Guard_Admin::init();
	Morn_Form_Guard_Plugin::init();
}
add_action( 'plugins_loaded', 'morn_form_guard_boot' );

/**
 * 激活钩子：注册文章类型并刷新重写。
 *
 * @return void
 */
function morn_form_guard_activate() {
	Morn_Form_Guard_Storage::register_post_type();
	flush_rewrite_rules();

	// 首次写入默认设置与保留上限。
	$settings = get_option( MORN_FORM_GUARD_OPTION, array() );

	if ( ! is_array( $settings ) || empty( $settings ) ) {
		add_option( MORN_FORM_GUARD_OPTION, Morn_Form_Guard_Storage::get_default_settings() );
	}
}
register_activation_hook( __FILE__, 'morn_form_guard_activate' );

/**
 * 停用钩子。
 *
 * @return void
 */
function morn_form_guard_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'morn_form_guard_deactivate' );
