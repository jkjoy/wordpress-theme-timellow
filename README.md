# Timellow for WordPress

根据 [Typecho 版 Timellow](https://github.com/jkjoy/typecho-theme-timellow) 移植的 WordPress 主题。保留原主题的首页、文章、评论、归档、分类、标签、友链、说说、侧栏以及深浅色样式。

配套的 [Timellow 插件](plugins/timellow/README.md) 位于 `plugins/timellow/`，已整合 Markdown 编辑器。在「设置 → Timellow」可以开关古腾堡、区块样式、Markdown 编辑器、评论者等级及评论环境，并配置前端资源精简、正文与侧栏缓存、SMTP 测试及评论通知、Gravatar 镜像。评论者等级由插件按邮箱统计已审核的普通评论，主题在评论昵称旁显示等级；评论环境显示系统、浏览器徽标及大致 IP 归属地。旧版独立 Markdown 插件保留作兼容，新插件启用后会在后台停用它。

## 安装

将本仓库放入 `wp-content/themes/timellow`，然后在 WordPress 后台的「外观 → 主题」启用。PHP 需为 8.0 或更新版本。

在「外观 → 自定义 → Timellow 设置」中可以配置副标题、单/双栏、侧栏模块、封面、字体、社交链接、备案信息、统计代码及列表翻页方式。导航菜单在「外观 → 菜单」分配到「主导航」；未分配时自动列出顶级页面。

「全站字体」内置霞鹜文楷和阿里妈妈方圆体 VF，选择后才会加载对应本地 WOFF2 文件。阿里妈妈方圆体的权利人和使用条件见 [字体授权说明](assets/fonts/README.md)。

侧边栏昵称使用站点中第一位管理员或编辑的显示名称，头像默认使用该用户邮箱对应的 Gravatar；设置「自定义侧边栏头像 URL」后会优先显示自定义图片。

评论中的系统和浏览器品牌徽标来自 [Font Awesome Free](https://fontawesome.com)，按 CC BY 4.0 许可使用，许可文本见 `assets/comment-icons/LICENSE.txt`；通用徽标由主题绘制。

「外观 → 小工具」使用 WordPress 经典小工具界面，可在「Timellow 侧边栏补充」区域添加原生小工具；侧栏仅在双栏布局下显示。

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

说说使用后台的「说说」内容类型，支持正文、特色图片、地点和来源。启用配套插件后，访客可在说说页面选择一种表情表态、切换或再次点击取消；同一浏览器对每条说说只保留一种表态。若说说页面使用自定义 slug，请选择「说说」页面模板，并在主题设置中填写该 slug。

评论中使用已登录账号发言的网站管理员或当前文章作者显示红色「管理员」标识，不显示评论等级；仅填写相同邮箱的访客不会获得该标识。

## 本地演示

仓库中的 `.local/` 已安装 WordPress、SQLite 集成插件和 Timellow 插件，并被 Git 忽略。基础演示数据包含 8 篇文章、6 个页面、3 条说说、3 条书签、分类、标签和示例评论；本地还发布了一篇 [Markdown 写作示例](http://127.0.0.1:8098/markdown-writing-demo/) 供体验编辑器。运行：

```sh
php tools/seed-demo.php
php -S 127.0.0.1:8098 -t .local/wordpress .local/wordpress/router.php
```

浏览 `http://127.0.0.1:8098/`。后台账号信息在 `.local/demo-login.txt`。`seed-demo.php` 可重复运行，不会重复创建示例内容。若使用别的端口，需要同步修改 `.local/wordpress/wp-config.php` 中的 `WP_HOME` 与 `WP_SITEURL`。
