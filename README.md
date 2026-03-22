# Typecho Sitemap 插件

一个为Typecho博客生成符合搜索引擎标准的Sitemap XML文件的插件。

## 功能特性

- **完整的Sitemap支持**：生成符合 [sitemaps.org](https://www.sitemaps.org/) 标准的XML站点地图
- **多种内容类型**：支持文章、独立页面、分类、标签、作者页
- **Sitemap索引**：自动管理大型站点的多Sitemap文件
- **图片Sitemap**：支持Google图片搜索优化，自动提取文章中的图片
- **智能缓存**：可配置的缓存机制，提高性能
- **自动更新**：内容更新时自动清除缓存
- **灵活配置**：丰富的后台配置选项
- **SEO优化**：支持优先级、更新频率等SEO参数设置
- **Gzip压缩**：可选的Gzip压缩输出

## 安装方法

1. 下载插件文件
2. 将插件文件夹重命名为 `Sitemap`
3. 上传到 Typecho 的 `usr/plugins/` 目录
4. 登录后台，在"控制台" -> "插件"中启用 Sitemap 插件
5. 点击"设置"配置插件选项

## 使用方法

启用插件后，Sitemap 将自动在以下地址可用：

- **Sitemap 索引**：`https://yourblog.com/sitemap.xml`
- **文章Sitemap**：`https://yourblog.com/sitemap_posts.xml`
- **分类Sitemap**：`https://yourblog.com/sitemap_category.xml`
- **标签Sitemap**：`https://yourblog.com/sitemap_tag.xml`
- **作者Sitemap**：`https://yourblog.com/sitemap_author.xml`

## 配置选项

### 基础设置

- **缓存时间**：Sitemap缓存时间（小时），设置为0则不缓存
- **每页URL数量**：单个Sitemap文件包含的最大URL数量（最大50000）

### 内容类型

选择要在Sitemap中包含的内容类型：
- 文章
- 独立页面
- 分类
- 标签
- 作者页

### SEO设置

- **更新频率**：设置不同内容类型的更新频率
- **优先级**：设置不同页面的优先级（0.0-1.0）
- **包含文章图片**：在Sitemap中包含文章中的图片

### 排除设置

- **排除分类**：要排除的分类MID，多个用逗号分隔
- **排除文章**：要排除的文章CID，多个用逗号分隔

## 提交到搜索引擎

### Google Search Console

1. 访问 [Google Search Console](https://search.google.com/search-console)
2. 添加并验证您的网站
3. 在"索引" -> "站点地图"中提交：`sitemap.xml`

### Bing Webmaster Tools

1. 访问 [Bing Webmaster Tools](https://www.bing.com/webmasters)
2. 添加并验证您的网站
3. 在"站点地图"中提交：`sitemap.xml`

### 百度站长平台

1. 访问 [百度搜索资源平台](https://ziyuan.baidu.com/)
2. 添加并验证您的网站
3. 在"资源提交" -> "Sitemap"中提交：`sitemap.xml`

## robots.txt 配置

建议在网站的 `robots.txt` 文件中添加以下行：

```
Sitemap: https://yourblog.com/sitemap.xml
```

## 技术要求

- Typecho 1.2.0 或更高版本
- PHP 7.4 或更高版本
- 支持命名空间（PHP 5.3+）

## 更新日志

### v1.1.0 (2026-03-22)

- 修复静态 sitemap.xml 中重复 `</urlset>` 闭合标签导致 XML 无效的严重 Bug
- 修复标签统计查询 type 值错误（`tags` → `tag`）
- 添加 Action 管理员权限验证，防止未授权访问
- 移除无效的 `checkCache` 钩子，提升内容渲染性能
- 修复插件禁用时 `deactivate()` 调用 `generateStaticSitemap()` 可能报错的问题
- 优化排除列表类型比较（`intval` + 严格模式）
- 使用配置的 `pageSize` 替代硬编码值
- `sendPing()` 添加 curl 可用性检查，提供 `file_get_contents` 回退方案
- `extractImages()` 支持 data URI 跳过和协议相对 URL
- `getPopularPosts()` 修复使用不存在的 `views` 字段
- `template.php` 使用 `__DIR__` 和闭包替代全局函数
- 添加 `SitemapWidget::getDb()` 公共方法

### v1.0.0 (2024-01-01)

- 初始版本发布
- 支持文章、页面、分类、标签、作者页Sitemap
- 实现Sitemap索引功能
- 添加图片Sitemap支持
- 实现缓存机制
- 添加后台配置面板

## 常见问题

### Q: Sitemap 返回404错误？

A: 请确保：
1. 插件已正确启用
2. Typecho的伪静态规则已配置
3. 插件文件夹名称为 `Sitemap`（区分大小写）

### Q: Google 无法抓取 Sitemap？

A: 如果 Google Search Console 显示无法抓取 Sitemap，请检查以下几点：

1. **验证 Sitemap 可访问性**：
   - 直接在浏览器访问 `https://yourblog.com/sitemap.xml`
   - 确保返回的是 XML 格式，不是 HTML 错误页面
   - 检查页面源代码，确保以 `<?xml version="1.0" encoding="UTF-8"?>` 开头

2. **检查 HTTP 状态码**：
   - 使用 curl 或在线工具检查返回状态是否为 200
   - 确保没有 301/302 重定向问题

3. **XML 格式验证**：
   - 使用 [XML Sitemap Validator](https://www.xml-sitemaps.com/validate-xml-sitemap.html) 验证
   - 确保所有 URL 都是绝对路径（以 http:// 或 https:// 开头）
   - 确保特殊字符已正确转义

4. **robots.txt 检查**：
   - 确保 `robots.txt` 没有阻止 Sitemap 访问
   - 添加 `Sitemap: https://yourblog.com/sitemap.xml` 到 robots.txt

5. **静态文件方案**（推荐）：
   - 插件现在会在网站根目录生成静态 `sitemap.xml` 文件
   - 发布/更新文章时会自动更新此文件
   - 这比普通路由方式更容易被搜索引擎抓取

6. **其他可能原因**：
   - 服务器防火墙阻止 Google 爬虫
   - CDN 缓存问题（尝试清除 CDN 缓存）
   - 网站需要登录才能访问（确保 Sitemap 是公开的）

### Q: 如何手动更新Sitemap？

A: 插件会在内容更新时自动清除缓存。您也可以：
1. 在后台重新保存插件设置
2. 手动删除 `usr/cache/sitemap/` 目录下的缓存文件
3. 删除网站根目录的 `sitemap.xml` 文件（会自动重新生成）

### Q: Sitemap文件大小有限制吗？

A: 根据Sitemap协议，单个Sitemap文件：
- 最多包含 50,000 个URL
- 文件大小不超过 50MB（未压缩）

本插件会自动处理大型站点的Sitemap分割。

## 许可证

MIT License

## 作者

[喜多ちゃん](https://github.com/furina315)

## 相关链接

- [GitHub 仓库](https://github.com/furina315/Typecho_Sitemap)
- [Typecho 官方网站](https://typecho.org/)
- [Sitemap 协议](https://www.sitemaps.org/protocol.html)
- [Google Sitemap 指南](https://developers.google.com/search/docs/advanced/sitemaps/overview)
