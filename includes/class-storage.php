<?php
/**
 * 数据存储与设置。
 *
 * @package MornRain\MornFormGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 管理 morn_inquiry 文章类型与插件设置。
 */
class Morn_Form_Guard_Storage {

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'protect_post_data' ), 10, 2 );
	}

	/**
	 * 默认设置。
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return array(
			// 收件与通知。
			'recipient'          => get_option( 'admin_email' ),
			'email_subject'      => __( '新表单留言：{subject}', 'morn-form-guard' ),
			'email_from_name'    => get_bloginfo( 'name' ),
			'email_reply_to'    => 1,
			'mail_html'          => 1,
			// 防刷。
			'strength'           => 'medium',
			'enable_honeypot'    => 1,
			'enable_rate_limit'  => 1,
			'enable_captcha'     => 0,
			'enable_keyword_filter' => 1,
			'min_seconds'        => 3,
			'max_links'          => 3,
			'rate_limit_count'   => 3,
			'rate_limit_window'  => 600,
			'keyword_blacklist'  => "viagra\ncasino\ncrypto\nforex\nporn\n Adult \n贷款\n博彩\n色情",
			'ip_blacklist'       => '',
			// 存储。
			'retention_limit'    => 500,
			'keep_data'          => 1,
			'notify_enabled'     => 1,
			// 自定义字段的原始多行配置；解析结果另存于 morn_form_guard_custom_fields。
			'custom_fields'      => '',
		);
	}

	/**
	 * 读取设置。
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( MORN_FORM_GUARD_OPTION, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		/**
		 * 过滤表单插件设置。
		 *
		 * @param array $settings 合并默认值后的设置。
		 */
		return apply_filters( 'morn_form_guard_settings', array_merge( self::get_default_settings(), $saved ) );
	}

	/**
	 * 读取单个设置项。
	 *
	 * @param string $key     设置键名。
	 * @param mixed  $default 默认值。
	 * @return mixed
	 */
	public static function get_setting( $key, $default = null ) {
		$settings = self::get_settings();

		if ( ! array_key_exists( $key, $settings ) ) {
			return $default;
		}

		return $settings[ $key ];
	}

	/**
	 * 注册自定义文章类型。
	 *
	 * @return void
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => __( '咨询留言', 'morn-form-guard' ),
			'singular_name'      => __( '咨询留言', 'morn-form-guard' ),
			'menu_name'          => __( '咨询留言', 'morn-form-guard' ),
			'add_new'            => __( '新增留言', 'morn-form-guard' ),
			'add_new_item'       => __( '新增留言', 'morn-form-guard' ),
			'edit_item'          => __( '编辑留言', 'morn-form-guard' ),
			'new_item'           => __( '新留言', 'morn-form-guard' ),
			'view_item'          => __( '查看留言', 'morn-form-guard' ),
			'search_items'       => __( '搜索留言', 'morn-form-guard' ),
			'not_found'          => __( '暂无留言', 'morn-form-guard' ),
			'not_found_in_trash' => __( '回收站中没有留言', 'morn-form-guard' ),
			'all_items'          => __( '全部留言', 'morn-form-guard' ),
		);

		register_post_type(
			MORN_FORM_GUARD_CPT,
			array(
				'labels'              => $labels,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_position'       => 26,
				'menu_icon'           => 'dashicons-email-alt',
				'query_var'           => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'has_archive'         => false,
				'hierarchical'        => false,
				'supports'            => array( 'title', 'editor' ),
				'exclude_from_search' => true,
			)
		);
	}

	/**
	 * 保护留言数据不被普通编辑流程破坏。
	 *
	 * @param array $data    待写入的文章数据。
	 * @param array $postarr 原始数据。
	 * @return array
	 */
	public static function protect_post_data( $data, $postarr ) {
		if ( empty( $data['post_type'] ) || MORN_FORM_GUARD_CPT !== $data['post_type'] ) {
			return $data;
		}

		// 自动草稿不处理。
		if ( isset( $data['post_status'] ) && 'auto-draft' === $data['post_status'] ) {
			return $data;
		}

		// 非必要场景不改动内容，仅确保不会被置为公开。
		if ( 'public' === (string) get_post_status( isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0 ) ) {
			$data['post_status'] = 'private';
		}

		return $data;
	}

	/**
	 * 存储一条留言。
	 *
	 * @param array $data     已验证的留言数据。
	 * @param array $meta     附加元信息（IP、UA、提交时间等）。
	 * @return int|WP_Error 文章 ID 或错误对象。
	 */
	public static function save_inquiry( $data, $meta = array() ) {
		$settings = self::get_settings();

		$title = isset( $data['subject'] ) && '' !== trim( (string) $data['subject'] )
			? $data['subject']
			: sprintf(
				/* translators: %s: 访客姓名或邮箱。 */
				__( '来自 %s 的留言', 'morn-form-guard' ),
				isset( $data['name'] ) && '' !== $data['name'] ? $data['name'] : ( isset( $data['email'] ) ? $data['email'] : __( '匿名访客', 'morn-form-guard' ) )
			);

		$content = self::build_content( $data, $meta );

		$post_id = wp_insert_post(
			array(
				'post_type'    => MORN_FORM_GUARD_CPT,
				'post_title'   => wp_strip_all_tags( $title ),
				'post_content' => $content,
				'post_status'  => 'private',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post_id = (int) $post_id;

		// 元信息统一以 _morn_ 前缀存储。
		$meta_fields = array(
			'_morn_name'     => isset( $data['name'] ) ? $data['name'] : '',
			'_morn_email'    => isset( $data['email'] ) ? $data['email'] : '',
			'_morn_phone'    => isset( $data['phone'] ) ? $data['phone'] : '',
			'_morn_company'  => isset( $data['company'] ) ? $data['company'] : '',
			'_morn_website'  => isset( $data['website'] ) ? $data['website'] : '',
			'_morn_subject'  => isset( $data['subject'] ) ? $data['subject'] : '',
			'_morn_message'  => isset( $data['message'] ) ? $data['message'] : '',
			'_morn_ip'       => isset( $meta['ip'] ) ? $meta['ip'] : '',
			'_morn_user_agent' => isset( $meta['user_agent'] ) ? $meta['user_agent'] : '',
			'_morn_source'   => isset( $meta['source'] ) ? $meta['source'] : '',
			'_morn_read'     => '0',
		);

		foreach ( $meta_fields as $key => $value ) {
			update_post_meta( $post_id, $key, (string) $value );
		}

		// 自定义字段。
		if ( ! empty( $data['custom'] ) && is_array( $data['custom'] ) ) {
			foreach ( $data['custom'] as $key => $value ) {
				$meta_key = '_morn_custom_' . sanitize_key( $key );
				update_post_meta( $post_id, $meta_key, (string) $value );
			}
		}

		// 超量清理。
		self::prune_old( (int) $settings['retention_limit'] );

		/**
		 * 留言保存成功后的动作。
		 *
		 * @param int   $post_id 留言 ID。
		 * @param array $data    留言数据。
		 * @param array $meta    元信息。
		 */
		do_action( 'morn_form_guard_inquiry_saved', $post_id, $data, $meta );

		return $post_id;
	}

	/**
	 * 构造留言正文。
	 *
	 * @param array $data 留言数据。
	 * @param array $meta 元信息。
	 * @return string
	 */
	private static function build_content( $data, $meta ) {
		$lines = array();

		$lines[] = '**' . esc_html__( '姓名', 'morn-form-guard' ) . ':** ' . ( isset( $data['name'] ) ? $data['name'] : '' );
		$lines[] = '**' . esc_html__( '邮箱', 'morn-form-guard' ) . ':** ' . ( isset( $data['email'] ) ? $data['email'] : '' );

		if ( ! empty( $data['phone'] ) ) {
			$lines[] = '**' . esc_html__( '电话', 'morn-form-guard' ) . ':** ' . $data['phone'];
		}

		if ( ! empty( $data['company'] ) ) {
			$lines[] = '**' . esc_html__( '公司', 'morn-form-guard' ) . ':** ' . $data['company'];
		}

		if ( ! empty( $data['website'] ) ) {
			$lines[] = '**' . esc_html__( '网站', 'morn-form-guard' ) . ':** ' . $data['website'];
		}

		$lines[] = '**' . esc_html__( '主题', 'morn-form-guard' ) . ':** ' . ( isset( $data['subject'] ) ? $data['subject'] : '' );

		$lines[] = '';
		$lines[] = '**' . esc_html__( '留言内容', 'morn-form-guard' ) . ':**';
		$lines[] = ( isset( $data['message'] ) ? $data['message'] : '' );

		if ( ! empty( $data['custom'] ) && is_array( $data['custom'] ) ) {
			$lines[] = '';
			$lines[] = '**' . esc_html__( '附加信息', 'morn-form-guard' ) . ':**';

			foreach ( $data['custom'] as $key => $value ) {
				$lines[] = '- ' . morn_form_guard_get_field_label( $key ) . ': ' . $value;
			}
		}

		$lines[] = '';
		$lines[] = '---';
		$lines[] = '**' . esc_html__( '技术信息', 'morn-form-guard' ) . ':**';
		$lines[] = '- ' . esc_html__( 'IP', 'morn-form-guard' ) . ': ' . ( isset( $meta['ip'] ) ? $meta['ip'] : '' );
		$lines[] = '- ' . esc_html__( '时间', 'morn-form-guard' ) . ': ' . morn_form_guard_format_time( gmdate( 'Y-m-d H:i:s' ) );
		$lines[] = '- ' . esc_html__( '来源页面', 'morn-form-guard' ) . ': ' . ( isset( $meta['source'] ) ? $meta['source'] : '' );
		$lines[] = '- ' . esc_html__( '浏览器', 'morn-form-guard' ) . ': ' . ( isset( $meta['user_agent'] ) ? $meta['user_agent'] : '' );

		return implode( "\n", $lines );
	}

	/**
	 * 清理超出保留上限的旧留言。
	 *
	 * @param int $limit 保留条数上限。
	 * @return int 删除条数。
	 */
	public static function prune_old( $limit ) {
		$limit = (int) $limit;

		if ( $limit <= 0 ) {
			return 0;
		}

		$query = new WP_Query(
			array(
				'post_type'              => MORN_FORM_GUARD_CPT,
				'post_status'            => array( 'private', 'publish', 'draft' ),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'offset'                 => $limit,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( ! $query->have_posts() ) {
			return 0;
		}

		$old_ids = $query->posts;
		$deleted = 0;

		foreach ( $old_ids as $old_id ) {
			if ( wp_delete_post( (int) $old_id, true ) ) {
				$deleted++;
			}
		}

		wp_reset_postdata();

		return $deleted;
	}

	/**
	 * 获取留言总数。
	 *
	 * @param string $status 状态筛选，空为全部。
	 * @return int
	 */
	public static function count_inquiries( $status = '' ) {
		$args = array(
			'post_type'      => MORN_FORM_GUARD_CPT,
			'post_status'    => array( 'private', 'publish', 'draft' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		);

		if ( '' !== $status ) {
			$args['post_status'] = $status;
		}

		$query = new WP_Query( $args );

		return (int) $query->found_posts;
	}
}
