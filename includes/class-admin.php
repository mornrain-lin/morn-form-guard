<?php
/**
 * 后台设置页与留言管理。
 *
 * @package MornRain\MornFormGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 后台控制器。
 */
class Morn_Form_Guard_Admin {

	/**
	 * 设置分组名。
	 */
	const GROUP = 'morn_form_guard_group';

	/**
	 * 设置页 slug。
	 */
	const PAGE = 'morn-form-guard';

	/**
	 * 批量操作 nonce 动作。
	 */
	const BULK_ACTION = 'morn_form_guard_bulk';

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_morn_form_guard_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_morn_form_guard_bulk', array( __CLASS__, 'handle_bulk' ) );
		add_action( 'admin_post_morn_form_guard_prune', array( __CLASS__, 'handle_prune' ) );
		add_filter( 'manage_' . MORN_FORM_GUARD_CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . MORN_FORM_GUARD_CPT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
	}

	/**
	 * 注册菜单。
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=' . MORN_FORM_GUARD_CPT,
			__( '表单设置', 'morn-form-guard' ),
			__( '设置', 'morn-form-guard' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * 注册设置项。
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			self::GROUP,
			MORN_FORM_GUARD_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);

		add_settings_section( 'sec_mail', __( '邮件通知', 'morn-form-guard' ), array( __CLASS__, 'render_sec_mail' ), self::PAGE );
		self::field( 'recipient', __( '收件人邮箱', 'morn-form-guard' ), 'text', 'sec_mail' );
		self::field( 'email_subject', __( '邮件标题模板', 'morn-form-guard' ), 'text', 'sec_mail' );
		self::field( 'email_from_name', __( '发件人显示名', 'morn-form-guard' ), 'text', 'sec_mail' );
		self::field( 'email_reply_to', __( '允许回复到访客邮箱', 'morn-form-guard' ), 'checkbox', 'sec_mail' );
		self::field( 'mail_html', __( '使用 HTML 邮件模板', 'morn-form-guard' ), 'checkbox', 'sec_mail' );
		self::field( 'notify_enabled', __( '启用邮件通知', 'morn-form-guard' ), 'checkbox', 'sec_mail' );

		add_settings_section( 'sec_antispam', __( '防刷设置', 'morn-form-guard' ), array( __CLASS__, 'render_sec_antispam' ), self::PAGE );
		self::field( 'strength', __( '防刷强度', 'morn-form-guard' ), 'select_strength', 'sec_antispam' );
		self::field( 'enable_honeypot', __( '启用蜜罐字段', 'morn-form-guard' ), 'checkbox', 'sec_antispam' );
		self::field( 'enable_captcha', __( '启用算术验证码', 'morn-form-guard' ), 'checkbox', 'sec_antispam' );
		self::field( 'min_seconds', __( '最短提交耗时（秒）', 'morn-form-guard' ), 'number', 'sec_antispam' );
		self::field( 'max_links', __( '留言最多链接数', 'morn-form-guard' ), 'number', 'sec_antispam' );
		self::field( 'enable_keyword_filter', __( '启用关键词过滤', 'morn-form-guard' ), 'checkbox', 'sec_antispam' );
		self::field( 'keyword_blacklist', __( '关键词黑名单（每行一条）', 'morn-form-guard' ), 'textarea', 'sec_antispam' );
		self::field( 'enable_rate_limit', __( '启用提交频率限制', 'morn-form-guard' ), 'checkbox', 'sec_antispam' );
		self::field( 'rate_limit_count', __( '时间窗内最大提交数', 'morn-form-guard' ), 'number', 'sec_antispam' );
		self::field( 'rate_limit_window', __( '时间窗长度（秒）', 'morn-form-guard' ), 'number', 'sec_antispam' );
		self::field( 'ip_blacklist', __( 'IP 黑名单（每行一条）', 'morn-form-guard' ), 'textarea', 'sec_antispam' );

		add_settings_section( 'sec_storage', __( '数据存储', 'morn-form-guard' ), array( __CLASS__, 'render_sec_storage' ), self::PAGE );
		self::field( 'retention_limit', __( '保留留言上限', 'morn-form-guard' ), 'number', 'sec_storage' );
		self::field( 'keep_data', __( '卸载时保留数据', 'morn-form-guard' ), 'checkbox', 'sec_storage' );
		self::field( 'custom_fields', __( '自定义字段（每行：名称|标签|类型|必填）', 'morn-form-guard' ), 'textarea', 'sec_storage' );
	}

	/**
	 * 注册单个字段。
	 *
	 * @param string $key     设置键名。
	 * @param string $label   标签。
	 * @param string $type    控件类型。
	 * @param string $section 分组。
	 * @return void
	 */
	private static function field( $key, $label, $type, $section ) {
		add_settings_field(
			'morn_fg_' . $key,
			$label,
			array( __CLASS__, 'render_field' ),
			self::PAGE,
			$section,
			array(
				'key'  => $key,
				'type' => $type,
			)
		);
	}

	/**
	 * 校验设置。
	 *
	 * @param mixed $input 原始输入。
	 * @return array
	 */
	public static function sanitize( $input ) {
		$old   = Morn_Form_Guard_Storage::get_settings();
		$input = is_array( $input ) ? $input : array();
		$clean = $old;

		$checkboxes = array(
			'email_reply_to',
			'mail_html',
			'notify_enabled',
			'enable_honeypot',
			'enable_captcha',
			'enable_keyword_filter',
			'enable_rate_limit',
			'keep_data',
		);

		foreach ( $checkboxes as $key ) {
			$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$levels = array( 'low', 'medium', 'high' );
		$clean['strength'] = isset( $input['strength'] ) && in_array( $input['strength'], $levels, true ) ? $input['strength'] : 'medium';

		if ( isset( $input['recipient'] ) ) {
			$email = sanitize_email( (string) $input['recipient'] );
			// 多个邮箱用逗号分隔。
			$emails = array_filter( array_map( 'trim', explode( ',', $email ) ) );
			$valid  = array();

			foreach ( $emails as $item ) {
				if ( is_email( $item ) ) {
					$valid[] = $item;
				}
			}

			$clean['recipient'] = implode( ',', $valid );
		}

		if ( isset( $input['email_subject'] ) ) {
			$clean['email_subject'] = substr( sanitize_text_field( (string) $input['email_subject'] ), 0, 200 );
		}

		if ( isset( $input['email_from_name'] ) ) {
			$clean['email_from_name'] = substr( sanitize_text_field( (string) $input['email_from_name'] ), 0, 100 );
		}

		$clean['min_seconds']     = isset( $input['min_seconds'] ) ? max( 0, min( 3600, absint( $input['min_seconds'] ) ) ) : 3;
		$clean['max_links']       = isset( $input['max_links'] ) ? max( 0, min( 50, absint( $input['max_links'] ) ) ) : 3;
		$clean['rate_limit_count'] = isset( $input['rate_limit_count'] ) ? max( 1, min( 100, absint( $input['rate_limit_count'] ) ) ) : 3;
		$clean['rate_limit_window'] = isset( $input['rate_limit_window'] ) ? max( 60, min( 86400, absint( $input['rate_limit_window'] ) ) ) : 600;
		$clean['retention_limit'] = isset( $input['retention_limit'] ) ? max( 0, min( 100000, absint( $input['retention_limit'] ) ) ) : 500;

		if ( isset( $input['keyword_blacklist'] ) ) {
			$clean['keyword_blacklist'] = self::sanitize_lines( $input['keyword_blacklist'], 500, 100 );
		}

		if ( isset( $input['ip_blacklist'] ) ) {
			$clean['ip_blacklist'] = self::sanitize_ip_list( $input['ip_blacklist'] );
		}

		if ( isset( $input['custom_fields'] ) ) {
			$clean['custom_fields'] = self::sanitize_lines( $input['custom_fields'], 200, 20 );
			update_option( 'morn_form_guard_custom_fields', self::parse_custom_fields( $clean['custom_fields'] ), false );
		}

		return $clean;
	}

	/**
	 * 通用多行文本清理。
	 *
	 * @param mixed $value    原始值。
	 * @param int   $max_len  单行最大长度。
	 * @param int   $max_rows 最大行数。
	 * @return string
	 */
	private static function sanitize_lines( $value, $max_len, $max_rows ) {
		$raw   = is_scalar( $value ) ? (string) $value : '';
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$out   = array();

		foreach ( (array) $lines as $line ) {
			$line = sanitize_text_field( trim( $line ) );

			if ( '' === $line ) {
				continue;
			}

			$out[] = substr( $line, 0, $max_len );

			if ( count( $out ) >= $max_rows ) {
				break;
			}
		}

		return implode( "\n", $out );
	}

	/**
	 * 清理 IP 黑名单。
	 *
	 * @param mixed $value 原始值。
	 * @return string
	 */
	private static function sanitize_ip_list( $value ) {
		$raw   = is_scalar( $value ) ? (string) $value : '';
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$out   = array();

		foreach ( (array) $lines as $line ) {
			$line = trim( (string) $line );

			if ( '' === $line ) {
				continue;
			}

			// 支持 1.2.3.* 通配写法。
			if ( substr( $line, -1 ) === '*' ) {
				$prefix = substr( $line, 0, -1 );
				$parts  = explode( '.', $prefix );
				$valid  = true;

				foreach ( $parts as $part ) {
					if ( '' !== $part && ! ctype_digit( $part ) ) {
						$valid = false;
						break;
					}
				}

				if ( $valid && count( $parts ) >= 2 ) {
					$out[] = substr( $prefix, 0, 40 ) . '*';
				}
			} elseif ( filter_var( $line, FILTER_VALIDATE_IP ) ) {
				$out[] = $line;
			}

			if ( count( $out ) >= 500 ) {
				break;
			}
		}

		return implode( "\n", $out );
	}

	/**
	 * 解析自定义字段配置。
	 *
	 * 格式：名称|标签|类型|必填（0/1）
	 *
	 * @param string $raw 多行原始文本。
	 * @return array
	 */
	public static function parse_custom_fields( $raw ) {
		$fields = array();
		$lines  = preg_split( '/\r\n|\r|\n/', (string) $raw );

		foreach ( (array) $lines as $line ) {
			$line = trim( (string) $line );

			if ( '' === $line ) {
				continue;
			}

			$parts = explode( '|', $line );

			$name = sanitize_key( isset( $parts[0] ) ? $parts[0] : '' );

			if ( '' === $name ) {
				continue;
			}

			$fields[] = array(
				'name'     => $name,
				'label'    => isset( $parts[1] ) && '' !== trim( $parts[1] ) ? sanitize_text_field( $parts[1] ) : $name,
				'type'     => isset( $parts[2] ) && in_array( $parts[2], array( 'text', 'email', 'textarea' ), true ) ? $parts[2] : 'text',
				'required' => isset( $parts[3] ) && '1' === trim( $parts[3] ) ? 1 : 0,
				'width'    => 'full',
			);

			if ( count( $fields ) >= 20 ) {
				break;
			}
		}

		return $fields;
	}

	/**
	 * 渲染设置页。
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '您没有权限访问此页面。', 'morn-form-guard' ) );
		}

		$blocked = Morn_Form_Guard_Antispam::get_blocked_log( 10 );
		?>
		<div class="wrap morn-fg-wrap">
			<h1><?php echo esc_html__( '表单设置', 'morn-form-guard' ); ?></h1>

			<div class="morn-fg-toolbar">
				<span class="morn-fg-count">
					<?php
					printf(
						/* translators: %s: 留言总数。 */
						esc_html__( '当前共 %s 条留言', 'morn-form-guard' ),
						esc_html( number_format_i18n( Morn_Form_Guard_Storage::count_inquiries() ) )
					);
					?>
				</span>
				<a class="button" href="<?php echo esc_url( self::action_url( 'morn_form_guard_export' ) ); ?>">
					<?php echo esc_html__( '导出 CSV', 'morn-form-guard' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . MORN_FORM_GUARD_CPT ) ); ?>">
					<?php echo esc_html__( '手动新增留言', 'morn-form-guard' ); ?>
				</a>
				<a class="button button-secondary" href="<?php echo esc_url( self::action_url( 'morn_form_guard_prune' ) ); ?>">
					<?php echo esc_html__( '立即按上限清理', 'morn-form-guard' ); ?>
				</a>
			</div>

			<?php
			// 操作结果提示。
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- 仅用于显示提示，无副作用。
			if ( isset( $_GET['morn_pruned'] ) ) {
				$pruned = absint( $_GET['morn_pruned'] );
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %d: 已删除条数。 */
							__( '已清理 %d 条超出上限的旧留言。', 'morn-form-guard' ),
							$pruned
						)
					)
				);
			}

			if ( isset( $_GET['morn_notice'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '操作已完成。', 'morn-form-guard' ) . '</p></div>';
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			?>

			<?php if ( ! empty( $blocked ) ) : ?>
				<h2><?php echo esc_html__( '最近拦截记录', 'morn-form-guard' ); ?></h2>
				<table class="widefat striped morn-fg-log">
					<thead>
						<tr>
							<th><?php echo esc_html__( '时间', 'morn-form-guard' ); ?></th>
							<th><?php echo esc_html__( 'IP', 'morn-form-guard' ); ?></th>
							<th><?php echo esc_html__( '原因', 'morn-form-guard' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_reverse( $blocked ) as $row ) : ?>
							<tr>
								<td><?php echo esc_html( date_i18n( 'Y-m-d H:i:s', (int) $row['time'] ) ); ?></td>
								<td><code><?php echo esc_html( $row['ip'] ); ?></code></td>
								<td><?php echo esc_html( self::reason_label( $row['reason'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * 拦截原因的中文标签。
	 *
	 * @param string $reason 原因代码。
	 * @return string
	 */
	private static function reason_label( $reason ) {
		$labels = array(
			'honeypot' => __( '命中蜜罐', 'morn-form-guard' ),
			'captcha'  => __( '验证码错误', 'morn-form-guard' ),
			'keyword'  => __( '命中关键词', 'morn-form-guard' ),
			'links'    => __( '链接过多', 'morn-form-guard' ),
		);

		return isset( $labels[ $reason ] ) ? $labels[ $reason ] : $reason;
	}

	/**
	 * 邮件分组说明。
	 *
	 * @return void
	 */
	public static function render_sec_mail() {
		echo '<p class="description">' . esc_html__( '收件人可填写多个邮箱，用英文逗号分隔。邮件标题支持占位符：{subject} {name} {email} {site} {date}。', 'morn-form-guard' ) . '</p>';
	}

	/**
	 * 防刷分组说明。
	 *
	 * @return void
	 */
	public static function render_sec_antispam() {
		echo '<p class="description">' . esc_html__( '防刷强度提供一组推荐参数，各单项开关会覆盖强度默认值。建议「中」或「高」。IP 黑名单支持 1.2.3.* 通配写法。', 'morn-form-guard' ) . '</p>';
	}

	/**
	 * 存储分组说明。
	 *
	 * @return void
	 */
	public static function render_sec_storage() {
		echo '<p class="description">' . esc_html__( '保留上限为 0 表示不自动清理。自定义字段每行格式：名称|标签|类型(text/email/textarea)|必填(0/1)。', 'morn-form-guard' ) . '</p>';
	}

	/**
	 * 渲染字段控件。
	 *
	 * @param array $args 字段参数。
	 * @return void
	 */
	public static function render_field( $args ) {
		$settings = Morn_Form_Guard_Storage::get_settings();
		$key      = $args['key'];
		$type     = $args['type'];
		$name     = MORN_FORM_GUARD_OPTION . '[' . $key . ']';
		$id       = 'morn-fg-' . $key;
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';

		switch ( $type ) {
			case 'checkbox':
				?>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( 1, (int) $value ); ?> />
					<?php echo esc_html__( '启用', 'morn-form-guard' ); ?>
				</label>
				<?php
				break;

			case 'select_strength':
				?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
					<?php foreach ( morn_form_guard_get_strength_levels() as $value_key => $config ) : ?>
						<option value="<?php echo esc_attr( $value_key ); ?>" <?php selected( $value_key, (string) $value ); ?>>
							<?php echo esc_html( $config['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php
				break;

			case 'textarea':
				?>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="8" class="large-text code"><?php echo esc_textarea( (string) $value ); ?></textarea>
				<?php
				break;

			case 'number':
				?>
				<input type="number" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" class="small-text" />
				<?php
				break;

			default:
				?>
				<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" class="regular-text" />
				<?php
				break;
		}
	}

	/**
	 * 生成带 nonce 的后台操作 URL。
	 *
	 * @param string $action 操作名。
	 * @return string
	 */
	private static function action_url( $action ) {
		return wp_nonce_url(
			add_query_arg( 'action', $action, admin_url( 'admin-post.php' ) ),
			$action
		);
	}

	/**
	 * 加载后台资源。
	 *
	 * @param string $hook 当前后台页。
	 * @return void
	 */
	public static function enqueue( $hook ) {
		$is_settings = ( 'form_page_' . self::PAGE === $hook );

		if ( ! $is_settings ) {
			return;
		}

		wp_enqueue_style(
			'morn-form-guard-admin',
			MORN_FORM_GUARD_URL . 'assets/css/admin.css',
			array(),
			MORN_FORM_GUARD_VERSION
		);
	}

	/**
	 * 留言列表列定义。
	 *
	 * @param array $columns 原有列。
	 * @return array
	 */
	public static function columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new['morn_sender']  = __( '联系人', 'morn-form-guard' );
				$new['morn_email']   = __( '邮箱', 'morn-form-guard' );
				$new['morn_read']    = __( '状态', 'morn-form-guard' );
				$new['morn_ip']      = __( 'IP', 'morn-form-guard' );
			}
		}

		return $new;
	}

	/**
	 * 渲染自定义列内容。
	 *
	 * @param string $column  列名。
	 * @param int    $post_id 文章 ID。
	 * @return void
	 */
	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'morn_sender':
				$name = get_post_meta( $post_id, '_morn_name', true );
				echo esc_html( '' !== $name ? $name : '—' );
				break;

			case 'morn_email':
				$email = get_post_meta( $post_id, '_morn_email', true );
				if ( $email && is_email( $email ) ) {
					printf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );
				} else {
					echo esc_html( '—' );
				}
				break;

			case 'morn_read':
				$is_read = get_post_meta( $post_id, '_morn_read', true );
				if ( '1' === $is_read ) {
					echo '<span class="morn-fg-read">' . esc_html__( '已读', 'morn-form-guard' ) . '</span>';
				} else {
					$url = self::action_url( 'morn_form_guard_bulk' ) . '&do=read&post=' . (int) $post_id;
					echo '<a href="' . esc_url( $url ) . '">' . esc_html__( '标记已读', 'morn-form-guard' ) . '</a>';
				}
				break;

			case 'morn_ip':
				$ip = get_post_meta( $post_id, '_morn_ip', true );
				echo esc_html( '' !== $ip ? $ip : '—' );
				break;
		}
	}

	/**
	 * 添加行操作链接。
	 *
	 * @param array   $actions 现有操作。
	 * @param WP_Post $post    文章对象。
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( ! $post instanceof WP_Post || MORN_FORM_GUARD_CPT !== $post->post_type ) {
			return $actions;
		}

		unset( $actions['inline hide-if-no-trash'], $actions['view'] );

		$is_read = get_post_meta( $post->ID, '_morn_read', true );

		if ( '1' !== $is_read ) {
			$url = self::action_url( 'morn_form_guard_bulk' ) . '&do=read&post=' . (int) $post->ID;
			$actions['morn_read'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( '标记已读', 'morn-form-guard' ) . '</a>';
		}

		$export = self::action_url( 'morn_form_guard_export' ) . '&post=' . (int) $post->ID;
		$actions['morn_export'] = '<a href="' . esc_url( $export ) . '">' . esc_html__( '导出', 'morn-form-guard' ) . '</a>';

		return $actions;
	}

	/**
	 * 处理单条留言操作。
	 *
	 * @return void
	 */
	public static function handle_bulk() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( '您没有权限执行此操作。', 'morn-form-guard' ) );
		}

		check_admin_referer( self::BULK_ACTION );

		$do   = isset( $_GET['do'] ) ? sanitize_text_field( wp_unslash( $_GET['do'] ) ) : '';
		$post = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		if ( 'read' === $do && $post > 0 ) {
			// 仅允许操作留言自身的文章，避免越权写入其它文章元数据。
			if ( ! current_user_can( 'edit_post', $post ) ) {
				wp_die( esc_html__( '您没有权限执行此操作。', 'morn-form-guard' ), 403 );
			}

			$target = get_post( $post );

			if ( $target instanceof WP_Post && MORN_FORM_GUARD_CPT === $target->post_type ) {
				update_post_meta( $post, '_morn_read', '1' );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => MORN_FORM_GUARD_CPT,
					'morn_notice' => 'updated',
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * 处理手动清理。
	 *
	 * @return void
	 */
	public static function handle_prune() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '您没有权限执行此操作。', 'morn-form-guard' ) );
		}

		check_admin_referer( 'morn_form_guard_prune' );

		$limit   = (int) Morn_Form_Guard_Storage::get_setting( 'retention_limit', 500 );
		$deleted = Morn_Form_Guard_Storage::prune_old( $limit );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'   => MORN_FORM_GUARD_CPT,
					'morn_pruned' => (int) $deleted,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * 导出 CSV。
	 *
	 * @return void
	 */
	public static function handle_export() {
		// 留言含联系方式等隐私数据，需要能编辑他人文章的角色。
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( '您没有权限执行此操作。', 'morn-form-guard' ) );
		}

		check_admin_referer( 'morn_form_guard_export' );

		$single = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		// 单条导出时必须是留言且当前用户有权编辑。
		if ( $single > 0 ) {
			$target = get_post( $single );

			if ( ! $target instanceof WP_Post || MORN_FORM_GUARD_CPT !== $target->post_type || ! current_user_can( 'edit_post', $single ) ) {
				wp_die( esc_html__( '您没有权限执行此操作。', 'morn-form-guard' ), 403 );
			}
		}

		$args = array(
			'post_type'      => MORN_FORM_GUARD_CPT,
			'post_status'    => array( 'private', 'publish', 'draft' ),
			'posts_per_page' => 5000,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		if ( $single > 0 ) {
			$args['post__in'] = array( $single );
			$args['posts_per_page'] = 1;
		}

		$query = new WP_Query( $args );

		$filename = 'morn-inquiries-' . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );

		if ( false === $output ) {
			exit;
		}

		// 写入 UTF-8 BOM，保证 Excel 正确识别中文。
		fwrite( $output, "\xEF\xBB\xBF" );

		fputcsv( $output, array( 'ID', '提交时间', '姓名', '邮箱', '电话', '公司', '网站', '主题', '留言内容', 'IP', '已读' ) );

		foreach ( $query->posts as $post ) {
			$custom = array();

			foreach ( (array) get_post_meta( $post->ID ) as $key => $values ) {
				if ( 0 === strpos( $key, '_morn_custom_' ) ) {
					$custom[ substr( $key, strlen( '_morn_custom_' ) ) ] = is_array( $values ) ? (string) reset( $values ) : (string) $values;
				}
			}

			$message = get_post_meta( $post->ID, '_morn_message', true );

			fputcsv(
				$output,
				array(
					$post->ID,
					morn_form_guard_format_time( $post->post_date_gmt ),
					get_post_meta( $post->ID, '_morn_name', true ),
					get_post_meta( $post->ID, '_morn_email', true ),
					get_post_meta( $post->ID, '_morn_phone', true ),
					get_post_meta( $post->ID, '_morn_company', true ),
					get_post_meta( $post->ID, '_morn_website', true ),
					get_post_meta( $post->ID, '_morn_subject', true ),
					is_string( $message ) ? wp_strip_all_tags( $message ) : '',
					get_post_meta( $post->ID, '_morn_ip', true ),
					'1' === get_post_meta( $post->ID, '_morn_read', true ) ? '是' : '否',
				)
			);

			foreach ( $custom as $name => $value ) {
				fputcsv( $output, array( $post->ID, '', morn_form_guard_get_field_label( $name ), '', '', '', '', '', $value, '', '' ) );
			}
		}

		fclose( $output );
		wp_reset_postdata();
		exit;
	}

	/**
	 * 注册留言详情元框。
	 *
	 * @return void
	 */
	public static function add_meta_boxes() {
		add_meta_box(
			'morn-form-guard-detail',
			__( '留言详情', 'morn-form-guard' ),
			array( __CLASS__, 'render_detail_box' ),
			MORN_FORM_GUARD_CPT,
			'normal',
			'high'
		);
	}

	/**
	 * 渲染留言详情元框。
	 *
	 * @param WP_Post $post 文章对象。
	 * @return void
	 */
	public static function render_detail_box( $post ) {
		$fields = array(
			'morn_name'      => __( '姓名', 'morn-form-guard' ),
			'morn_email'     => __( '邮箱', 'morn-form-guard' ),
			'morn_phone'     => __( '电话', 'morn-form-guard' ),
			'morn_company'   => __( '公司', 'morn-form-guard' ),
			'morn_website'   => __( '网站', 'morn-form-guard' ),
			'morn_subject'   => __( '主题', 'morn-form-guard' ),
			'morn_message'   => __( '留言内容', 'morn-form-guard' ),
			'morn_ip'        => __( 'IP 地址', 'morn-form-guard' ),
			'morn_user_agent' => __( '浏览器', 'morn-form-guard' ),
			'morn_source'    => __( '来源页面', 'morn-form-guard' ),
		);

		echo '<table class="form-table">';

		foreach ( $fields as $key => $label ) {
			$value = get_post_meta( $post->ID, '_' . $key, true );
			$value = is_string( $value ) ? $value : '';

			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';

			if ( 'morn_message' === $key ) {
				echo '<textarea rows="5" class="large-text" readonly>' . esc_textarea( $value ) . '</textarea>';
			} elseif ( 'morn_email' === $key && $value && is_email( $value ) ) {
				printf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $value ) );
			} else {
				echo nl2br( esc_html( '' !== $value ? $value : '—' ) );
			}

			echo '</td></tr>';
		}

		// 自定义字段。
		foreach ( (array) get_post_meta( $post->ID ) as $key => $values ) {
			if ( 0 !== strpos( $key, '_morn_custom_' ) ) {
				continue;
			}

			$name  = substr( $key, strlen( '_morn_custom_' ) );
			$value = is_array( $values ) ? (string) reset( $values ) : (string) $values;

			echo '<tr><th scope="row">' . esc_html( morn_form_guard_get_field_label( $name ) ) . '</th><td>';
			echo esc_html( '' !== $value ? $value : '—' );
			echo '</td></tr>';
		}

		echo '</table>';
	}
}
