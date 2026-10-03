# Morn Form Guard

联系表单安全与防刷插件。用短代码 `[morn_form]` 即可在前台放置一个功能完整的安全联系表单，无需任何第三方表单插件。

七层服务端防护（nonce、蜜罐、时间戳、算术验证码、IP 黑名单、关键词过滤、链接检测、频率限制），留言存储为自定义文章类型，支持后台查看与 CSV 导出。

零外部资源，不引用任何 CDN、远程 API 或第三方脚本。

- 版本：1.0.0
- 需要 WordPress：6.0+
- 需要 PHP：7.4+
- 测试至：WordPress 6.6
- 许可证：MIT

## 特性

- **短代码即用**：`[morn_form]` 一行代码输出完整表单，支持多个实例共存。
- **七层防护**：全部在服务端强制执行，不依赖前端 JS。
- **智能验证码**：内置算术题（加减法，随机生成），答案一次性使用，10 分钟过期。
- **不打扰真人**：蜜罐命中时返回**成功**响应，机器人无法据此调整策略。
- **开放重定向防护**：无 JS 降级的跳转目标做同源校验。
- **可视化自定义字段**：后台按行配置，最多 20 个。
- **CSV 导出**：带 UTF-8 BOM，Excel 打开中文不乱码。
- **自动清理**：超出保留上限的旧留言自动删除。
- **未读气泡**：后台菜单显示未读留言数量。
- **无 JS 降级**：禁用 JavaScript 后表单仍可提交，功能不丢失。
- **前后端双重验证**：前端拦截明显错误，后端完整重做校验。

## 安装

1. 将 `morn-form-guard` 目录上传到 `wp-content/plugins/`。
2. 在后台「插件」中启用「Morn Form Guard」。
3. 进入「咨询留言 → 设置」配置收件邮箱与防刷强度。
4. 在任意文章或页面中插入短代码 `[morn_form]`。

## 短代码用法

```
[morn_form]
```

### 属性

| 属性 | 默认值 | 说明 |
| --- | --- | --- |
| `title` | 联系我们 | 表单顶部标题，留空则不显示。 |
| `submit` | 提交留言 | 提交按钮文字。 |
| `ajax` | `1` | 设为 `0` 关闭 AJAX，改用普通表单提交。 |
| `show_name` | `1` | 是否显示姓名字段。 |
| `show_phone` | `0` | 是否显示电话字段。 |
| `show_company` | `0` | 是否显示公司字段。 |
| `show_website` | `0` | 是否显示网站字段。 |
| `layout` | `two-column` | `two-column` 两列布局，`one-column` 单列。 |

### 示例

```text
默认表单
[morn_form]

单列布局 + 显示电话与公司
[morn_form layout="one-column" show_phone="1" show_company="1"]

自定义标题，无 AJAX 提交
[morn_form title="获取报价" ajax="0"]

同一页面两个表单
[morn_form title="销售咨询"]
[morn_form title="售后支持" layout="one-column"]
```

## 配置说明

后台路径：**咨询留言 → 设置**（`manage_options` 权限）。

设置页顶部工具栏显示留言总数，并提供「导出 CSV」「手动新增留言」「立即按上限清理」三个操作。下方是最近 10 条拦截记录（IP、时间、原因）。

### 邮件通知

| 设置项 | 默认值 | 说明 |
| --- | --- | --- |
| 收件人邮箱 | 站点管理员邮箱 | 可填多个邮箱，用**英文逗号**分隔。无效地址会被自动剔除。 |
| 邮件标题模板 | `新表单留言：{subject}` | 支持占位符 `{subject}` `{name}` `{email}` `{site}` `{date}`。 |
| 发件人显示名 | 站点标题 | From 头中显示的名称。 |
| 允许回复到访客邮箱 | 启用 | 勾选后设置 `Reply-To` 为访客邮箱，便于直接回复。 |
| 使用 HTML 邮件模板 | 启用 | 关闭则发送纯文本邮件。 |
| 启用邮件通知 | 启用 | 关闭后仍会正常存储留言，只是不发通知。 |

> **注意**：From 地址强制使用站点域名（`no-reply@你的域名`），这是 WordPress 主机判定邮件合法性的常见要求，**不可修改**。

> 邮件发送失败**不影响**留言保存，前台会提示「留言已成功保存，但通知邮件发送失败」。

### 防刷设置

| 设置项 | 默认值 | 说明 |
| --- | --- | --- |
| 防刷强度 | 中（+ 时间戳 + 关键词过滤） | 提供一组推荐参数，见下表。**单项开关会覆盖强度默认值。** |
| 启用蜜罐字段 | 启用 | 隐藏输入框，机器人自动填充即拦截。 |
| 启用算术验证码 | 关闭 | 显示算术题。强度选「高」时建议开启。 |
| 最短提交耗时（秒） | `3` | 从页面渲染到提交的最小间隔。设为 `0` 关闭。范围 0~3600。 |
| 留言最多链接数 | `3` | 超过则判定为垃圾。设为 `0` 关闭。范围 0~50。 |
| 启用关键词过滤 | 启用 | 匹配黑名单关键词即拒绝。 |
| 关键词黑名单 | 若干示例 | 每行一条，不区分大小写，支持中文。最多 500 行，每行 200 字符。 |
| 启用提交频率限制 | 启用 | IP 维度计数。 |
| 时间窗内最大提交数 | `3` | 超出则拒绝。范围 1~100。 |
| 时间窗长度（秒） | `600` | 计数窗口。范围 60~86400。 |
| IP 黑名单 | 空 | 每行一条，支持 `192.168.1.*` 通配写法。无效条目自动剔除。 |

**防刷强度对照表**

| 强度 | 蜜罐 | 频率限制 | 验证码 | 最短耗时 | 最多链接 | 关键词过滤 |
| --- | --- | --- | --- | --- | --- | --- |
| 低 | ✓ | ✓ | ✗ | 0 秒 | 5 个 | ✗ |
| 中 | ✓ | ✓ | ✗ | 3 秒 | 3 个 | ✓ |
| 高 | ✓ | ✓ | ✓ | 5 秒 | 1 个 | ✓ |

> **关于 IP 识别**：本插件**只读取 `REMOTE_ADDR`**，不信任 `X-Forwarded-For` 等代理头。因为这些头可以被伪造，若采信则 IP 黑名单与频率限制都可被轻易绕过。如果你的站点在 CDN 或反向代理之后，需要用 `morn_form_guard_client_ip` 过滤器注入真实 IP。

### 数据存储

| 设置项 | 默认值 | 说明 |
| --- | --- | --- |
| 保留留言上限 | `500` | 超出后自动删除最旧的。`0` 表示不自动清理。范围 0~100000。 |
| 卸载时保留数据 | **启用** | 见下方「卸载说明」。 |
| 自定义字段 | 空 | 每行一条，格式：`名称|标签|类型|必填`。 |

**自定义字段格式示例**

```text
budget|预算范围|select|0
company_size|公司规模|text|1
project_desc|项目描述|textarea|1
```

- **名称**：英文小写 + 下划线/连字符，作为提交字段名。
- **标签**：显示给访客的文字，留空则使用名称。
- **类型**：`text` / `email` / `textarea`。
- **必填**：`1` 是，`0` 否。

最多 20 个自定义字段。

## 防护机制详解

### 检查顺序

提交按以下顺序检查，命中即中止：

1. **nonce 校验** — 失败返回 403
2. **IP 黑名单** — 精确或通配匹配
3. **Honeypot 蜜罐** — 命中返回**成功**响应
4. **时间戳校验** — 间隔过短或超过 24 小时均拒绝
5. **算术验证码** — 一次性使用
6. **关键词黑名单** — UTF-8 大小写不敏感
7. **链接数量限制** — 三类模式分别统计
8. **提交频率限制** — IP 维度 transient 计数

### 蜜罐的工作方式

表单中有一个视觉隐藏（`position:absolute; left:-9999px`）但**可被脚本填充**的输入框，标签为「请勿填写此项」。

- 真人用户看不到也填不了 → 字段为空 → 通过
- 机器人自动填所有输入框 → 字段有值 → 拦截

命中蜜罐时接口返回**成功**响应并显示「留言已发送」，让机器人以为策略成功，不会调整。同时该次提交**不会被存储**。

### 链接检测的三类模式

```text
http:// 或 https:// 开头的链接
www. 开头的裸域名
example.com 形式的裸域名（com/net/org/cn/io/co/info/xyz/top/site/shop/club/online）
```

每一处匹配都单独计数，因此 `https://a.com` 会被计为 2 次。

### 无 JS 降级

表单初始包含一个隐藏字段 `morn_no_js=1`。前端 JS 加载后立即移除该字段：

- **有 JS**：字段不存在 → 服务端返回 JSON → 前端原地展示结果
- **无 JS**：字段保留 → 服务端重定向回来源页并附加 `?morn_form_sent=ok|error|expired` → 前端顶部展示提示

重定向目标做同源校验（对比 URL 主机名与 `home_url()`），防止开放重定向。

## 后台留言管理

### 列表页

路径：**咨询留言**。自定义列：

| 列 | 说明 |
| --- | --- |
| 联系人 | 留言人姓名 |
| 邮箱 | 可点击发送邮件 |
| 状态 | 未读显示「标记已读」链接 |
| IP | 提交来源 IP |

行操作包含「标记已读」与「导出」。菜单上显示未读数量气泡。

### CSV 导出

导出列：ID、提交时间、姓名、邮箱、电话、公司、网站、主题、留言内容、IP、已读状态。

自定义字段会作为额外行追加在同一 ID 下。文件写入 UTF-8 BOM，Excel 双击打开中文正常显示。

单条留言可在其编辑页通过「导出」行操作单独导出。

### 保留上限清理

每次成功保存留言后自动检查：若总数超出上限，按 ID 升序删除最旧的记录。也可在设置页点击「立即按上限清理」手动触发。

## Hook 列表

### 动作（do_action）

| Hook | 回调 | 优先级 | 用途 |
| --- | --- | --- | --- |
| `plugins_loaded` | `morn_form_guard_boot` | 10 | 加载文本域并启动插件。 |
| `init` | `Morn_Form_Guard_Storage::register_post_type` | 10 | 注册 `morn_inquiry` 文章类型。 |
| `wp_body_open` | `Morn_Form_Guard_Plugin::render_notice` | 10 | 无 JS 提交后展示结果提示（在正文开头输出，不干扰 `<head>`）。 |
| `wp_enqueue_scripts` | `Morn_Form_Guard_Form::enqueue` | 10 | 按需加载表单资源。 |
| `add_meta_boxes` | `Morn_Form_Guard_Admin::add_meta_boxes` | 10 | 注册留言详情元框。 |
| `admin_menu` | `Morn_Form_Guard_Admin::add_menu` | 10 | 注册设置子菜单。 |
| `admin_init` | `Morn_Form_Guard_Admin::register_settings` | 10 | 注册设置项。 |
| `admin_post_morn_form_guard_export` | `Morn_Form_Guard_Admin::handle_export` | — | CSV 导出。 |
| `admin_post_morn_form_guard_bulk` | `Morn_Form_Guard_Admin::handle_bulk` | — | 单条留言操作。 |
| `admin_post_morn_form_guard_prune` | `Morn_Form_Guard_Admin::handle_prune` | — | 手动清理超量留言。 |
| `wp_ajax_morn_form_guard_submit` | `Morn_Form_Guard_Ajax::handle` | — | 已登录用户提交。 |
| `wp_ajax_nopriv_morn_form_guard_submit` | `Morn_Form_Guard_Ajax::handle` | — | 访客提交。 |
| `morn_form_guard_inquiry_saved` | — | — | **留言保存成功后触发**，`do_action( 'morn_form_guard_inquiry_saved', int $post_id, array $data, array $meta )`。 |
| `morn_form_guard_submitted` | — | — | **完整提交流程结束后触发**，`do_action( 'morn_form_guard_submitted', int $post_id, array $data, bool $mail_sent )`。 |
| `morn_form_guard_mail_sent` | — | — | 邮件发送后触发，`do_action( 'morn_form_guard_mail_sent', bool $sent, int $post_id, array $data )`。 |

### 过滤器（apply_filters）

| Hook | 签名 | 说明 |
| --- | --- | --- |
| `morn_form_guard_settings` | `apply_filters( 'morn_form_guard_settings', array $settings )` | 过滤合并默认值后的设置。 |
| `morn_form_guard_client_ip` | `apply_filters( 'morn_form_guard_client_ip', string $ip )` | 覆盖访客 IP 识别（CDN 场景必用）。 |
| `morn_form_guard_antispam_result` | `apply_filters( 'morn_form_guard_antispam_result', array $result, string $reason, string $message )` | 覆盖反垃圾检查结果。 |
| `morn_form_guard_keyword_match` | `apply_filters( 'morn_form_guard_keyword_match', bool $found, string $text, string $raw_list )` | 覆盖关键词命中判定。 |
| `morn_form_guard_ip_blocked` | `apply_filters( 'morn_form_guard_ip_blocked', bool $found, string $ip, string $raw_list )` | 覆盖 IP 黑名单判定。 |
| `morn_form_guard_validation_result` | `apply_filters( 'morn_form_guard_validation_result', array $result, array $raw )` | 覆盖验证结果，可追加自定义校验。 |
| `morn_form_guard_client_rules` | `apply_filters( 'morn_form_guard_client_rules', array $rules )` | 过滤前端验证规则。 |
| `morn_form_guard_raw_input` | `apply_filters( 'morn_form_guard_raw_input', array $raw )` | 过滤清理后的原始输入。 |
| `morn_form_guard_redirect_url` | `apply_filters( 'morn_form_guard_redirect_url', string $url, string $status )` | 过滤无 JS 提交的重定向目标。 |
| `morn_form_guard_mail_args` | `apply_filters( 'morn_form_guard_mail_args', array $args, int $post_id, array $data, string $content_type )` | 覆盖 `wp_mail()` 参数。 |
| `morn_form_guard_mail_html` | `apply_filters( 'morn_form_guard_mail_html', string $html, array $rows, int $post_id )` | 过滤 HTML 邮件正文。 |
| `morn_form_guard_mail_rows` | `apply_filters( 'morn_form_guard_mail_rows', array $rows, array $data, int $post_id )` | 过滤邮件字段行。 |

### 使用示例

**CDN 场景注入真实 IP**（重要）

```php
add_filter( 'morn_form_guard_client_ip', function ( $ip ) {
    // 仅在你信任这些头时才使用。
    $headers = array(
        'HTTP_CF_CONNECTING_IP' => 'Cloudflare',
        'HTTP_X_REAL_IP'         => 'Nginx',
    );

    foreach ( $headers as $header => $source ) {
        if ( empty( $_SERVER[ $header ] ) ) {
            continue;
        }

        $candidate = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

        if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
            return $candidate;
        }
    }

    return $ip;
} );
```

**添加自定义校验**（如工单号必须为 6 位数字）

```php
add_filter( 'morn_form_guard_validation_result', function ( $result, $raw ) {
    if ( ! empty( $raw['ticket'] ) && ! preg_match( '/^\d{6}$/', $raw['ticket'] ) ) {
        $result['valid']  = false;
        $result['errors']['ticket'] = '工单号必须为 6 位数字。';
    }

    return $result;
}, 10, 2 );
```

**留言保存后推送到企业微信/钉钉 webhook**

```php
add_action( 'morn_form_guard_inquiry_saved', function ( $post_id, $data ) {
    // 这里可以调用你的内部通知逻辑。
    error_log( '新留言 #' . $post_id . '：' . $data['name'] );
}, 10, 2 );
```

**自定义邮件内容**

```php
add_filter( 'morn_form_guard_mail_rows', function ( $rows, $data ) {
    $rows['来源渠道'] = $data['source'] ?? '未知';

    return $rows;
}, 10, 2 );
```

## FAQ

**Q：为什么收不到邮件？**
A：按顺序排查：1) 后台「启用邮件通知」是否勾选；2) 收件邮箱是否正确；3) 站内「邮件」设置是否配置了 SMTP 插件（`wp_mail()` 依赖它）；4) 查看主机商的邮件发送日志。**注意：邮件失败不影响留言保存**，前台会明确提示。

**Q：前台提示「表单已过期」？**
A：页面缓存导致时间戳过期。把「最短提交耗时」调大不解决此问题——24 小时是硬性上限。方案：1) 缩短页面缓存 TTL；2) 关闭该站点的页面缓存插件；3) 引导访客刷新页面。

**Q：真人被误判为机器人？**
A：常见原因与对策：
- **「提交速度过快」**：用户用了自动填充。调低「最短提交耗时」或设为 0。
- **「验证码答案不正确」**：验证码 10 分钟过期，页面停留太久。刷新页面即可。
- **「内容包含过多链接」**：正常业务邮件常带签名链接。调高「留言最多链接数」。
- **「包含不允许提交的关键词」**：检查黑名单是否有过于宽泛的词。
- **「IP 已被列入黑名单」**：检查黑名单配置。

**Q：蜜罐会不会被误伤？**
A：正常使用不会。蜜罐依赖 `position:absolute; left:-9999px` 隐藏，真人看不到。但**浏览器自动填充功能有时会填入隐藏字段**。若遇到，可在 `morn_form_guard_antispam_result` 中放宽，或直接关闭蜜罐开关。

**Q：为什么不用 $_SERVER['HTTP_X_FORWARDED_IP']？**
A：因为该头可被任意伪造。若采信，攻击者每次换头就能绕过 IP 黑名单与频率限制。默认只信任 `REMOTE_ADDR`；若你在 CDN 之后，请通过 `morn_form_guard_client_ip` 过滤器显式注入。

**Q：能接收附件吗？**
A：当前版本不支持文件上传，这是有意的设计——文件上传是插件最主要的攻击面。确有需要时建议使用 WordPress 原生媒体表单（`wp_editor` + 媒体选择器）。

**Q：能对接 CRM 或 Webhook 吗？**
A：可以，用 `morn_form_guard_inquiry_saved` 或 `morn_form_guard_submitted` 动作挂载你自己的集成逻辑。

**Q：CSV 里的中文在 Excel 中乱码？**
A：导出文件已写入 UTF-8 BOM，正常情况下 Excel 双击即可正确识别。若仍乱码，请用「数据 → 从文本/CSV」导入并手动选择 UTF-8 编码。

**Q：可以放多个表单吗？**
A：可以，任意多个。每个表单有独立 ID，前端状态互不干扰。

**Q：与 Contact Form 7 等插件冲突吗？**
A：本插件不依赖也不修改其它插件，但**不建议同时使用两个联系表单方案**——样式与用户体验会不一致。若主题已集成 CF7，请不要同时启用本插件的短代码。

## 目录说明

```
morn-form-guard/
├── morn-form-guard.php   # 主文件：插件头、启动、激活/停用钩子
├── uninstall.php          # 卸载清理（默认保留留言数据）
├── README.md
├── LICENSE                # MIT
├── CHANGELOG.md
├── .gitignore
├── .gitattributes
├── assets/
│   ├── css/
│   │   ├── form.css       # 前台表单样式（CSS 变量可被主题覆盖）
│   │   └── admin.css      # 后台设置页样式
│   └── js/form.js         # 前台表单脚本（原生，前后端双重验证）
└── includes/
    ├── functions.php          # IP 识别、强度配置展开、时间格式化
    ├── class-plugin.php       # 协调层：未读气泡、无 JS 提示
    ├── class-storage.php      # morn_inquiry 文章类型、留言存储、超量清理
    ├── class-validator.php    # 字段验证（前后端共用规则）
    ├── class-antispam.php     # 蜜罐、限流、黑名单、验证码、链接检测
    ├── class-mailer.php       # wp_mail 通知与模板
    ├── class-form.php         # 短代码渲染与前台资源按需加载
    ├── class-ajax.php         # AJAX/无 JS 统一提交处理
    └── class-admin.php         # 设置页、留言列、CSV 导出、批量操作
```

**存储的选项**

| 选项名 | 类型 | 说明 |
| --- | --- | --- |
| `morn_form_guard_settings` | array | 插件全部设置。 |
| `morn_form_guard_custom_fields` | array | 解析后的自定义字段结构。 |
| `morn_form_guard_blocked_log` | array | 拦截记录，保留最近 200 条。 |

**自定义文章类型**：`morn_inquiry`（非公开，`post_status = private`）

**文章元字段**（均以 `_morn_` 为前缀）

`_morn_name`、`_morn_email`、`_morn_phone`、`_morn_company`、`_morn_website`、`_morn_subject`、`_morn_message`、`_morn_ip`、`_morn_user_agent`、`_morn_source`、`_morn_read`、`_morn_custom_{字段名}`

**Transient 前缀**：`_transient_morn_fg_`（限流计数与验证码答案）

## 卸载说明

在后台「插件」中点击「删除」并确认卸载时，`uninstall.php` 会读取「卸载时保留数据」开关：

**默认（保留数据，开启）**

- 删除选项 `morn_form_guard_settings`、`morn_form_guard_custom_fields`、`morn_form_guard_blocked_log`
- 清理所有 `_transient_morn_fg_*` 限流与验证码缓存
- **完整保留所有 `morn_inquiry` 留言及其元数据**

**关闭该开关后**

- 除上述清理外，**额外删除所有 `morn_inquiry` 文章及其全部 `_morn_` 元数据**

> 建议在卸载前先「导出 CSV」备份。

**任何情况下都不会删除**：文章、页面、媒体附件、用户、评论或其它插件的数据。

## License

MIT License
Copyright (c) 2026 MornRain

详见 [LICENSE](LICENSE)。
