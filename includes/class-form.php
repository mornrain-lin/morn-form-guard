<?php
/**
 * 前台表单渲染（短代码）。
 *
 * @package MornRain\MornFormGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 注册并渲染 [morn_form] 短代码。
 */
class Morn_Form_Guard_Form {

	/**
	 * 表单实例计数器，用于生成唯一 ID。
	 *
	 * @var int
	 */
	private static $instance = 0;

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'morn_form', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * 判断当前页面是否需要表单资源。
	 *
	 * @return bool
	 */
	private static function needs_assets() {
		global $post;

		if ( ! is_a( $post, 'WP_Post' ) ) {
			return false;
		}

		return has_shortcode( (string) $post->post_content, 'morn_form' );
	}

	/**
	 * 加载前台资源。
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( is_admin() || ! self::needs_assets() ) {
			return;
		}

		wp_enqueue_style(
			'morn-form-guard',
			MORN_FORM_GUARD_URL . 'assets/css/form.css',
			array(),
			MORN_FORM_GUARD_VERSION
		);

		wp_enqueue_script(
			'morn-form-guard',
			MORN_FORM_GUARD_URL . 'assets/js/form.js',
			array(),
			MORN_FORM_GUARD_VERSION,
			true
		);

		$config = array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'morn_form_guard_submit' ),
			'rules'    => Morn_Form_Guard_Validator::get_client_rules(),
			'messages' => array(
				'required' => __( '此字段为必填项。', 'morn-form-guard' ),
				'email'    => __( '请输入有效的邮箱地址。', 'morn-form-guard' ),
				'min'      => __( '内容太短。', 'morn-form-guard' ),
				'max'      => __( '内容太长。', 'morn-form-guard' ),
				'sending'  => __( '正在发送…', 'morn-form-guard' ),
				'network'  => __( '网络请求失败，请稍后重试。', 'morn-form-guard' ),
			),
		);

		$json = wp_json_encode( $config );

		if ( false === $json ) {
			return;
		}

		// wp_localize_script 会把嵌套值全部转为字符串，这里用内联 JSON 保留类型。
		wp_add_inline_script(
			'morn-form-guard',
			'window.mornFormGuard = ' . $json . ';',
			'before'
		);
	}

	/**
	 * 渲染短代码。
	 *
	 * @param array $atts 短代码属性。
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'       => __( '联系我们', 'morn-form-guard' ),
				'submit'      => __( '提交留言', 'morn-form-guard' ),
				'ajax'        => 1,
				'show_name'   => 1,
				'show_phone'  => 0,
				'show_company' => 0,
				'show_website' => 0,
				'layout'      => 'two-column',
			),
			$atts,
			'morn_form'
		);

		self::$instance++;

		$form_id = 'morn-form-' . self::$instance;
		$config  = morn_form_guard_get_antispam_config();
		$custom  = self::get_custom_fields();

		ob_start();
		?>
		<div class="morn-form-wrap" id="<?php echo esc_attr( $form_id ); ?>" data-ajax="<?php echo esc_attr( (int) $atts['ajax'] ); ?>" data-layout="<?php echo esc_attr( $atts['layout'] ); ?>">
			<?php if ( '' !== trim( (string) $atts['title'] ) ) : ?>
				<h3 class="morn-form-title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<form class="morn-form" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" novalidate>
				<?php wp_nonce_field( 'morn_form_guard_submit', 'morn_form_guard_nonce' ); ?>
				<input type="hidden" name="action" value="morn_form_guard_submit" />
				<input type="hidden" name="morn_ts" value="<?php echo esc_attr( (string) time() ); ?>" />
				<input type="hidden" name="morn_source" value="<?php echo esc_attr( self::get_source_url() ); ?>" />
				<?php // 由前端 JS 移除此字段，移除即代表无 JS 环境，走重定向降级。 ?>
				<input type="hidden" name="morn_no_js" value="1" class="morn-no-js-flag" />

				<?php if ( ! empty( $config['honeypot'] ) ) : ?>
					<div class="morn-hp" aria-hidden="true">
						<label for="<?php echo esc_attr( $form_id ); ?>-hp">
							<?php echo esc_html__( '请勿填写此项', 'morn-form-guard' ); ?>
						</label>
						<input type="text" id="<?php echo esc_attr( $form_id ); ?>-hp" name="<?php echo esc_attr( Morn_Form_Guard_Antispam::HONEYPOT_FIELD ); ?>" value="" tabindex="-1" autocomplete="off" />
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $config['captcha'] ) ) : ?>
					<?php $captcha = Morn_Form_Guard_Antispam::generate_captcha(); ?>
					<div class="morn-field morn-field-half">
						<label for="<?php echo esc_attr( $form_id ); ?>-captcha">
							<?php echo esc_html__( '请回答算术题', 'morn-form-guard' ); ?>
							<span class="morn-required">*</span>
						</label>
						<div class="morn-captcha-row">
							<span class="morn-captcha-question"><?php echo esc_html( $captcha['question'] ); ?></span>
							<input type="text" name="morn_captcha" class="morn-input" inputmode="numeric" autocomplete="off" required />
						</div>
						<input type="hidden" name="morn_captcha_token" value="<?php echo esc_attr( $captcha['token'] ); ?>" />
					</div>
				<?php endif; ?>

				<div class="morn-field <?php echo esc_attr( ! empty( $atts['show_name'] ) ? 'morn-field-half' : '' ); ?>">
					<label for="<?php echo esc_attr( $form_id ); ?>-name">
						<?php echo esc_html__( '姓名', 'morn-form-guard' ); ?>
						<span class="morn-required">*</span>
					</label>
					<input type="text" id="<?php echo esc_attr( $form_id ); ?>-name" name="name" class="morn-input" maxlength="60" autocomplete="name" required />
				</div>

				<div class="morn-field <?php echo esc_attr( ! empty( $atts['show_name'] ) ? 'morn-field-half' : '' ); ?>">
					<label for="<?php echo esc_attr( $form_id ); ?>-email">
						<?php echo esc_html__( '邮箱', 'morn-form-guard' ); ?>
						<span class="morn-required">*</span>
					</label>
					<input type="email" id="<?php echo esc_attr( $form_id ); ?>-email" name="email" class="morn-input" maxlength="100" autocomplete="email" required />
				</div>

				<?php if ( ! empty( $atts['show_phone'] ) ) : ?>
					<div class="morn-field">
						<label for="<?php echo esc_attr( $form_id ); ?>-phone"><?php echo esc_html__( '电话', 'morn-form-guard' ); ?></label>
						<input type="tel" id="<?php echo esc_attr( $form_id ); ?>-phone" name="phone" class="morn-input" maxlength="30" autocomplete="tel" />
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $atts['show_company'] ) ) : ?>
					<div class="morn-field">
						<label for="<?php echo esc_attr( $form_id ); ?>-company"><?php echo esc_html__( '公司', 'morn-form-guard' ); ?></label>
						<input type="text" id="<?php echo esc_attr( $form_id ); ?>-company" name="company" class="morn-input" maxlength="120" autocomplete="organization" />
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $atts['show_website'] ) ) : ?>
					<div class="morn-field">
						<label for="<?php echo esc_attr( $form_id ); ?>-website"><?php echo esc_html__( '网站', 'morn-form-guard' ); ?></label>
						<input type="url" id="<?php echo esc_attr( $form_id ); ?>-website" name="website" class="morn-input" maxlength="200" autocomplete="url" />
					</div>
				<?php endif; ?>

				<div class="morn-field">
					<label for="<?php echo esc_attr( $form_id ); ?>-subject">
						<?php echo esc_html__( '主题', 'morn-form-guard' ); ?>
						<span class="morn-required">*</span>
					</label>
					<input type="text" id="<?php echo esc_attr( $form_id ); ?>-subject" name="subject" class="morn-input" maxlength="200" required />
				</div>

				<div class="morn-field">
					<label for="<?php echo esc_attr( $form_id ); ?>-message">
						<?php echo esc_html__( '留言', 'morn-form-guard' ); ?>
						<span class="morn-required">*</span>
					</label>
					<textarea id="<?php echo esc_attr( $form_id ); ?>-message" name="message" class="morn-input" rows="6" maxlength="5000" required></textarea>
				</div>

				<?php if ( ! empty( $custom ) ) : ?>
					<div class="morn-custom-fields">
						<h4 class="morn-custom-title"><?php echo esc_html__( '补充信息', 'morn-form-guard' ); ?></h4>
						<?php foreach ( $custom as $field ) : ?>
							<?php
							$fname = 'custom[' . $field['name'] . ']';
							$fid   = $form_id . '-custom-' . $field['name'];
							?>
							<div class="morn-field <?php echo esc_attr( 'half' === $field['width'] ? 'morn-field-half' : '' ); ?>">
								<label for="<?php echo esc_attr( $fid ); ?>">
									<?php echo esc_html( $field['label'] ); ?>
									<?php if ( ! empty( $field['required'] ) ) : ?>
										<span class="morn-required">*</span>
									<?php endif; ?>
								</label>
								<?php if ( 'textarea' === $field['type'] ) : ?>
									<textarea id="<?php echo esc_attr( $fid ); ?>" name="<?php echo esc_attr( $fname ); ?>" class="morn-input" rows="3" maxlength="300" <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>></textarea>
								<?php else : ?>
									<input type="<?php echo esc_attr( 'email' === $field['type'] ? 'email' : 'text' ); ?>" id="<?php echo esc_attr( $fid ); ?>" name="<?php echo esc_attr( $fname ); ?>" class="morn-input" maxlength="300" <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?> />
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="morn-submit-row">
					<button type="submit" class="morn-submit"><?php echo esc_html( $atts['submit'] ); ?></button>
				</div>

				<div class="morn-response" role="status" aria-live="polite"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * 获取自定义字段配置。
	 *
	 * @return array
	 */
	private static function get_custom_fields() {
		$fields = get_option( 'morn_form_guard_custom_fields', array() );

		if ( ! is_array( $fields ) ) {
			return array();
		}

		$clean = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || empty( $field['name'] ) ) {
				continue;
			}

			$name = sanitize_key( $field['name'] );

			if ( '' === $name ) {
				continue;
			}

			$clean[] = array(
				'name'     => $name,
				'label'    => isset( $field['label'] ) && '' !== trim( (string) $field['label'] ) ? sanitize_text_field( $field['label'] ) : $name,
				'type'     => isset( $field['type'] ) && in_array( $field['type'], array( 'text', 'email', 'textarea' ), true ) ? $field['type'] : 'text',
				'required' => ! empty( $field['required'] ) ? 1 : 0,
				'width'    => isset( $field['width'] ) && 'half' === $field['width'] ? 'half' : 'full',
			);

			if ( count( $clean ) >= 20 ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * 获取来源页面 URL。
	 *
	 * @return string
	 */
	private static function get_source_url() {
		if ( is_singular() ) {
			$id = get_queried_object_id();

			if ( $id ) {
				$link = get_permalink( $id );

				if ( $link ) {
					return $link;
				}
			}
		}

		return home_url( add_query_arg( array() ) );
	}
}
