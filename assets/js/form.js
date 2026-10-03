/**
 * Morn Form Guard 前台表单脚本。
 *
 * 前后端双重验证：前端先做基础校验拦截明显错误，
 * 后端仍会完整重新校验，前端校验仅用于提升体验。
 */
( function () {
	'use strict';

	var config = window.mornFormGuard || {};
	var messages = config.messages || {};

	/**
	 * 去除字符串首尾空白。
	 *
	 * @param {string} value 字符串。
	 * @return {string} 处理后的字符串。
	 */
	function trim( value ) {
		return String( value || '' ).replace( /^\s+|\s+$/g, '' );
	}

	/**
	 * 判断字段当前值长度（按码点计）。
	 *
	 * @param {string} value 字符串。
	 * @return {number} 长度。
	 */
	function charLength( value ) {
		return Array.from( value || '' ).length;
	}

	/**
	 * 验证单个字段。
	 *
	 * @param {HTMLElement} field 字段元素。
	 * @return {string} 错误信息，空字符串表示通过。
	 */
	function validateField( field ) {
		var name = field.getAttribute( 'name' );
		var rules = ( config.rules || {} )[ name ];

		clearFieldError( field );

		if ( ! rules ) {
			return '';
		}

		var value = trim( field.value );

		if ( ! field.value.trim() ) {
			if ( rules.required ) {
				return messages.required || '此字段为必填项。';
			}

			return '';
		}

		if ( name === 'email' && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) {
			return messages.email || '请输入有效的邮箱地址。';
		}

		var length = charLength( field.value );

		if ( rules.min && length < rules.min ) {
			return messages.min || '内容太短。';
		}

		if ( rules.max && length > rules.max ) {
			return messages.max || '内容太长。';
		}

		return '';
	}

	/**
	 * 在字段下方显示错误信息。
	 *
	 * @param {HTMLElement} field   字段元素。
	 * @param {string}      message 错误信息。
	 * @return {void}
	 */
	function showFieldError( field, message ) {
		field.classList.add( 'has-error' );
		field.setAttribute( 'aria-invalid', 'true' );

		var error = document.createElement( 'p' );
		error.className = 'morn-field-error';
		error.textContent = message;

		field.parentNode.appendChild( error );
	}

	/**
	 * 清除字段错误状态。
	 *
	 * @param {HTMLElement} field 字段元素。
	 * @return {void}
	 */
	function clearFieldError( field ) {
		field.classList.remove( 'has-error' );
		field.removeAttribute( 'aria-invalid' );

		var parent = field.parentNode;
		var existing = parent ? parent.querySelectorAll( '.morn-field-error' ) : [];

		Array.prototype.forEach.call( existing, function ( node ) {
			node.parentNode.removeChild( node );
		} );
	}

	/**
	 * 初始化单个表单。
	 *
	 * @param {HTMLElement} wrap 表单容器。
	 * @return {void}
	 */
	function initForm( wrap ) {
		var form = wrap.querySelector( '.morn-form' );

		if ( ! form ) {
			return;
		}

		var response = form.querySelector( '.morn-response' );
		var submit = form.querySelector( '.morn-submit' );
		var noJsFlag = form.querySelector( '.morn-no-js-flag' );
		var isAjax = '1' === wrap.getAttribute( 'data-ajax' );

		// JS 可用时移除降级标记。
		if ( noJsFlag ) {
			noJsFlag.parentNode.removeChild( noJsFlag );
		}

		// 失焦时做单字段校验。
		Array.prototype.forEach.call( form.querySelectorAll( '.morn-input' ), function ( field ) {
			field.addEventListener( 'blur', function () {
				var message = validateField( field );

				if ( message ) {
					showFieldError( field, message );
				}
			} );

			field.addEventListener( 'input', function () {
				if ( field.classList.contains( 'has-error' ) ) {
					clearFieldError( field );
				}
			} );
		} );

		form.addEventListener( 'submit', function ( event ) {
			// 无 AJAX 能力时让浏览器原生提交。
			if ( ! isAjax || ! window.fetch || ! window.FormData ) {
				return;
			}

			event.preventDefault();

			var firstInvalid = null;

			Array.prototype.forEach.call( form.querySelectorAll( '.morn-input' ), function ( field ) {
				var message = validateField( field );

				if ( message ) {
					showFieldError( field, message );

					if ( ! firstInvalid ) {
						firstInvalid = field;
					}
				}
			} );

			if ( firstInvalid ) {
				firstInvalid.focus();
				return;
			}

			var formData = new FormData( form );

			// 补上 AJAX 标记。
			formData.append( 'action', 'morn_form_guard_submit' );

			setBusy( submit, true );
			clearResponse( response );

			window.fetch( config.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			} )
				.then( function ( res ) {
					return res.json().then( function ( payload ) {
						return { ok: res.ok && payload.success, payload: payload };
					} );
				} )
				.then( function ( result ) {
					var payload = result.payload || {};
					var data = payload.data || {};

					if ( result.ok && data.code === 'ok' ) {
						showResponse( response, 'success', data.message || '' );
						form.reset();
						resetNoJsFlag( form );
						return;
					}

					showResponse( response, 'error', data.message || messages.network || '' );

					// 后端返回的字段级错误。
					if ( data.errors ) {
						Object.keys( data.errors ).forEach( function ( key ) {
							var field = form.querySelector( '[name="' + key + '"]' );

							if ( field ) {
								showFieldError( field, data.errors[ key ] );
							}
						} );
					}
				} )
				.catch( function () {
					showResponse( response, 'error', messages.network || '网络请求失败，请稍后重试。' );
				} )
				.then( function () {
					setBusy( submit, false );
				} );
		} );
	}

	/**
	 * 重置降级标记字段。
	 *
	 * @param {HTMLFormElement} form 表单。
	 * @return {void}
	 */
	function resetNoJsFlag( form ) {
		var flag = document.createElement( 'input' );
		flag.type = 'hidden';
		flag.name = 'morn_no_js';
		flag.value = '1';
		flag.className = 'morn-no-js-flag';
		form.appendChild( flag );
	}

	/**
	 * 切换指定表单的提交按钮状态。
	 *
	 * @param {HTMLElement} button 提交按钮。
	 * @param {boolean}     busy   是否忙碌。
	 * @return {void}
	 */
	function setBusy( button, busy ) {
		if ( ! button ) {
			return;
		}

		button.disabled = busy;

		if ( busy ) {
			button.dataset.label = button.textContent;
			button.textContent = messages.sending || '正在发送…';
		} else if ( button.dataset.label ) {
			button.textContent = button.dataset.label;
		}
	}

	/**
	 * 显示整体响应信息。
	 *
	 * @param {HTMLElement} node    响应容器。
	 * @param {string}      type    类型：success 或 error。
	 * @param {string}      message 文本。
	 * @return {void}
	 */
	function showResponse( node, type, message ) {
		node.className = 'morn-response is-' + type;
		node.textContent = message;
	}

	/**
	 * 清空响应信息。
	 *
	 * @param {HTMLElement} node 响应容器。
	 * @return {void}
	 */
	function clearResponse( node ) {
		node.className = 'morn-response';
		node.textContent = '';
	}

	function init() {
		if ( ! config.ajaxUrl ) {
			return;
		}

		var wraps = document.querySelectorAll( '.morn-form-wrap' );

		Array.prototype.forEach.call( wraps, initForm );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
