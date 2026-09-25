# Timellow 插件

与 Timellow WordPress 主题配套的独立插件，需要 WordPress 6.5+ 和 PHP 8.0+。

将本目录放到 `wp-content/plugins/timellow`，在后台启用后进入「设置 → Timellow」。Markdown 编辑器和 Parsedown 已包含在此目录中，不再需要单独安装 `timellow-markdown`。若旧插件仍处于启用状态，Timellow 会在后台自动停用它；原有文章的 `_timellow_markdown_mode` 数据保持有效。

## 功能

- 可分别禁用古腾堡编辑器、WordPress 前台区块样式和 Emoji 外部脚本。区块样式开关默认开启；已有区块内容依赖这些样式时可关闭。主题自带资源继续使用本地文件，其他插件的资源不会被强制改写。
- 内置 Markdown 编辑器可单独开关，适用于文章、页面和说说，提供实时预览、媒体插入和编辑／分屏／预览视图。关闭编辑器后，已有 Markdown 内容仍按原模式解析。Markdown 使用 Parsedown 1.8.0，许可证见 `vendor/LICENSE.txt`。
- 文章、页面正文分别设置缓存分钟数；编辑每篇文章或页面时可覆盖全局值，填 `0` 关闭。登录用户、预览、密码保护内容和非 GET 请求不使用正文缓存。评论在正文外单独渲染，不会被缓存。
- 侧栏列表和友链书签分组使用短期缓存；更新文章、评论或书签时清理对应缓存。若正文包含按访客或时间变化的动态短代码，应为该篇关闭正文缓存。
- Gravatar 可选择原地址、Cravatar、gravatar.loli.net 或自定义 HTTPS 镜像。只改写 Gravatar 头像 URL，保留尺寸和默认头像参数。
- SMTP 支持 STARTTLS、SSL/TLS 和无加密连接，可设置身份验证、发件人及测试收件箱。密码使用 WordPress salt 加密并单独存储，留空保留原密码。WordPress 原生的新评论与待审核通知通过 `wp_mail()` 使用配置好的 SMTP，不额外发送重复邮件。

测试邮件需要先保存并启用 SMTP。建议使用与 SMTP 账号域名匹配的发件人邮箱，以满足邮件服务商的要求。
