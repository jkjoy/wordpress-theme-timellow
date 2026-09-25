# Timellow for WordPress

根据 [Typecho 版 Timellow](https://github.com/jkjoy/typecho-theme-timellow) 移植的 WordPress 主题。保留原主题的首页、文章、评论、归档、分类、标签、友链、说说、侧栏以及深浅色样式。

可选的 [Timellow Markdown Editor](plugins/timellow-markdown/README.md) 插件位于 `plugins/timellow-markdown/`。将其安装到 WordPress 后，可关闭古腾堡并用 Markdown 编辑文章、页面和说说；已有 HTML 内容保持原样。

## 安装

将本仓库放入 `wp-content/themes/timellow`，然后在 WordPress 后台的「外观 → 主题」启用。PHP 需为 8.0 或更新版本。

在「外观 → 自定义 → Timellow 设置」中可以配置副标题、单/双栏、侧栏模块、封面、字体、社交链接、备案信息、统计代码及列表翻页方式。导航菜单在「外观 → 菜单」分配到「主导航」；未分配时自动列出顶级页面。

文章封面依次使用特色图片、文章 `cover` 字段、正文第一张图片、主题默认封面、内置封面。编辑文章时可以使用侧面的「Timellow 封面」填写 URL。

## 独立页面

新建以下 slug 的页面会自动使用对应模板，也可以在页面编辑器中选择模板：

| slug | 模板 |
| --- | --- |
| `archives` | 文章归档 |
| `categories` | 全部分类 |
| `tags` | 全部标签 |
| `links` | 友情链接 |
| `moments` 或 `shuoshuo` | 说说 |

友情链接使用 WordPress 原生书签：在后台「链接 → 链接分类」创建分组，再在「链接 → 添加链接」填写名称、URL、描述和图片地址，友链页会按链接分类展示可见书签。页面正文只用作介绍；旧版写在 `timellow-links` 注释块里的友链会在管理员进入后台时导入书签，原内容保存在页面的 `_timellow_legacy_links_backup` 字段。

说说使用后台的「说说」内容类型，支持正文、特色图片、地点和来源。若说说页面使用自定义 slug，请选择「说说」页面模板，并在主题设置中填写该 slug。

## 本地演示

仓库中的 `.local/` 已安装 WordPress、SQLite 集成插件和 Timellow Markdown Editor，并被 Git 忽略。基础演示数据包含 8 篇文章、6 个页面、3 条说说、3 条书签、分类、标签和示例评论；本地还发布了一篇 [Markdown 写作示例](http://127.0.0.1:8098/markdown-writing-demo/) 供体验编辑器。运行：

```sh
php tools/seed-demo.php
php -S 127.0.0.1:8098 -t .local/wordpress .local/wordpress/router.php
```

浏览 `http://127.0.0.1:8098/`。后台账号信息在 `.local/demo-login.txt`。`seed-demo.php` 可重复运行，不会重复创建示例内容。若使用别的端口，需要同步修改 `.local/wordpress/wp-config.php` 中的 `WP_HOME` 与 `WP_SITEURL`。
