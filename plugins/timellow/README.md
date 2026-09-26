# Timellow 插件

与 Timellow WordPress 主题配套的独立插件，需要 WordPress 6.5+ 和 PHP 8.0+。

将本目录放到 `wp-content/plugins/timellow`，在后台启用后进入「设置 → Timellow」。Markdown 编辑器和 Parsedown 已包含在此目录中，不再需要单独安装 `timellow-markdown`。若旧插件仍处于启用状态，Timellow 会在后台自动停用它；原有文章的 `_timellow_markdown_mode` 数据保持有效。

## 功能

- 可分别禁用古腾堡编辑器、WordPress 前台区块样式和 Emoji 外部脚本。区块样式开关默认开启；已有区块内容依赖这些样式时可关闭。主题自带资源继续使用本地文件，其他插件的资源不会被强制改写。
- 默认隐藏网站前台的 WordPress 工具栏，可在「设置 → Timellow」关闭此选项；后台工具栏不受影响。
- 内置 Markdown 编辑器可单独开关，适用于文章、页面和说说，提供实时预览、媒体插入和编辑／分屏／预览视图。关闭编辑器后，已有 Markdown 内容仍按原模式解析。Markdown 使用 Parsedown 1.8.0，许可证见 `vendor/LICENSE.txt`。
- 编辑 HTML 文章时，「转换为 Markdown」按钮位于发布／更新按钮左侧。转换会更新当前编辑框与预览，不会自动保存；确认效果后再发布或更新。HTML 转换使用本地打包的 Turndown 7.2.4 与 GFM 扩展 1.0.2，MIT 许可证见 `vendor/TURNDOWN-LICENSE.txt`。复杂嵌入内容转换后应检查预览。
- 缓存总开关默认开启；关闭后正文、侧栏、书签和评论等级缓存均不再读写，原有缓存时间及单篇设置保留。说说表态限流和 SMTP 错误提示所需的临时状态仍正常工作。
- 文章、页面正文分别设置缓存分钟数；编辑每篇文章或页面时可覆盖全局值，填 `0` 关闭。登录用户、预览、密码保护内容和非 GET 请求不使用正文缓存。评论在正文外单独渲染，不会被缓存。
- 侧栏列表和友链书签分组使用短期缓存；更新文章、评论或书签时清理对应缓存。若正文包含按访客或时间变化的动态短代码，应为该篇关闭正文缓存。
- 可关闭的评论者等级：按邮箱累计全站已审核的普通评论，1／5／20／50／100／200 条分别达到 Lv.1–Lv.6。当前评论页的邮箱一次批量统计并缓存；评论变化后自动失效。等级仅表示评论活跃度，不验证邮箱归属。
- 可关闭的评论环境：从 WordPress 保存的评论 User-Agent 识别系统和浏览器，按 IP 显示大致归属地，不公开完整 IP。归属地通过内置 ip2region IPv4/IPv6 数据库在服务器本地查询，不会向第三方发送评论者 IP；没有记录或无法识别时隐藏相应信息。归属地数据随插件版本更新，可能与实际所在地不同。
- 说说表态：提供 👍、❤️、😂、😮、🔥、👏 六种表情。每个已登录账号或访客浏览器对一条说说保留一种表态，重复点击可取消；计数在本地数据库保存，不依赖第三方服务。访客使用一年有效的 HttpOnly Cookie 记住选择；清除 Cookie 后会被视为新访客。
- Gravatar 可选择原地址、Cravatar、gravatar.loli.net 或自定义 HTTPS 镜像。只改写 Gravatar 头像 URL，保留尺寸和默认头像参数。
- SMTP 支持 STARTTLS、SSL/TLS 和无加密连接，可设置身份验证、发件人及测试收件箱。密码使用 WordPress salt 加密并单独存储，留空保留原密码。WordPress 原生的新评论与待审核通知通过 `wp_mail()` 使用配置好的 SMTP，不额外发送重复邮件。

测试邮件需要先保存并启用 SMTP。建议使用与 SMTP 账号域名匹配的发件人邮箱，以满足邮件服务商的要求。

评论归属地数据库和 PHP 查询器来自 [ip2region](https://github.com/lionsoul2014/ip2region)，按 Apache-2.0 或 MIT 许可分发，许可文本见 `vendor/ip2region/LICENSE.md`。主题中的评论系统／浏览器品牌徽标来自 Font Awesome Free，按 CC BY 4.0 许可分发，许可文本见主题的 `assets/comment-icons/LICENSE.txt`；通用系统和浏览器徽标为主题自绘。
