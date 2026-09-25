# Timellow Markdown Editor

此独立插件已并入 [Timellow 插件](../timellow/README.md)。现有站点可以直接启用 Timellow；旧插件会在后台自动停用，已有 Markdown 内容继续正常解析。本目录仅供旧版独立安装兼容使用。

独立的 WordPress Markdown 编辑器插件，适用于文章、页面和 Timellow 的「说说」。禁用这些内容类型的古腾堡编辑器，保留 WordPress 原生的发布、草稿、修订、分类和媒体库。

## 安装

将 `timellow-markdown` 文件夹放入 `wp-content/plugins/`，然后在后台「插件」中启用。插件要求 WordPress 6.5+ 和 PHP 8.0+，无需安装 Composer 包。

新建内容默认使用 Markdown。安装前已有的内容保持 HTML 模式和原样展示；编辑页面顶部可切换内容格式，切换不会自动转换正文。Markdown 原文保存在 `post_content`，格式记录在 `_timellow_markdown_mode`。

编辑器提供格式按钮、媒体库图片插入、编辑/分屏/预览视图及全屏模式。预览与前台使用同一 PHP 解析器。Markdown 采用 [Parsedown 1.8.0](https://github.com/erusev/parsedown/releases/tag/1.8.0)，启用安全模式，并经过 WordPress HTML 过滤。解析库的 MIT 许可证见 `vendor/LICENSE.txt`。

Markdown 模式下的内嵌 HTML 会显示为文本；需要保留旧内容中的 HTML 标签时，使用 HTML 模式。

插件会处理正文、摘要和「说说」内容；停用插件后，Markdown 原文仍保留在数据库中，但前台不再自动转换为 HTML。
