<?php
/**
 * Sitemap HTML 模板
 * 
 * 提供用户可读的HTML格式Sitemap
 * 
 * @package Sitemap
 * @author 喜多ちゃん
 * @version 1.1.0
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 加载必要的类
require_once __DIR__ . '/Widget.php';

use TypechoPlugin\Sitemap\SitemapWidget;
use Widget\Options;

// 创建Widget实例
$widget = new SitemapWidget(\Typecho\Request::getInstance(), \Typecho\Response::getInstance());
$options = Options::alloc();

// 设置页面标题
$pageTitle = '站点地图 - ' . $options->title;

// 获取内容
$categories = $widget->getCategoryTree();
$tags = $widget->getTagCloud(50);
$recentPosts = $widget->getRecentContents(50);
$pages = $widget->getDb()->fetchAll($widget->getDb()->select()
    ->from('table.contents')
    ->where('type = ?', 'page')
    ->where('status = ?', 'publish')
    ->order('order', \Typecho\Db::SORT_ASC));

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="robots" content="index, follow">
    <meta name="description" content="<?php echo htmlspecialchars($options->description); ?>">
    <link rel="stylesheet" href="<?php echo $options->pluginUrl; ?>/Sitemap/sitemap.css">
    <style>
        /* 基础样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: #fff;
            padding: 30px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        header p {
            color: #7f8c8d;
        }
        
        .sitemap-nav {
            background-color: #fff;
            padding: 15px 30px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .sitemap-nav ul {
            list-style: none;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
        }
        
        .sitemap-nav a {
            color: #3498db;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        
        .sitemap-nav a:hover {
            background-color: #ecf0f1;
        }
        
        .sitemap-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .sitemap-section {
            margin-bottom: 40px;
        }
        
        .sitemap-section:last-child {
            margin-bottom: 0;
        }
        
        .sitemap-section h2 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .sitemap-list {
            list-style: none;
        }
        
        .sitemap-list li {
            padding: 10px 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .sitemap-list li:last-child {
            border-bottom: none;
        }
        
        .sitemap-list a {
            color: #2980b9;
            text-decoration: none;
            font-size: 1.05em;
        }
        
        .sitemap-list a:hover {
            color: #e74c3c;
            text-decoration: underline;
        }
        
        .sitemap-list .date {
            color: #95a5a6;
            font-size: 0.85em;
            margin-left: 10px;
        }
        
        .sitemap-list .count {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-left: 5px;
        }
        
        /* 分类树样式 */
        .category-tree ul {
            margin-left: 20px;
            margin-top: 10px;
        }
        
        .category-tree li {
            padding: 5px 0;
        }
        
        /* 标签云样式 */
        .tag-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .tag-cloud a {
            display: inline-block;
            padding: 8px 16px;
            background-color: #ecf0f1;
            border-radius: 20px;
            color: #34495e;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .tag-cloud a:hover {
            background-color: #3498db;
            color: #fff;
        }
        
        footer {
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
            margin-top: 20px;
        }
        
        footer a {
            color: #3498db;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            header, .sitemap-nav, .sitemap-content {
                padding: 20px;
            }
            
            .sitemap-nav ul {
                flex-direction: column;
                gap: 10px;
            }
            
            .category-tree ul {
                margin-left: 15px;
            }
        }
        
        /* 返回顶部按钮 */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 40px;
            height: 40px;
            background-color: #3498db;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s;
            text-decoration: none;
            font-size: 1.2em;
        }
        
        .back-to-top.visible {
            opacity: 1;
        }
        
        .back-to-top:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo htmlspecialchars($options->title); ?> - 站点地图</h1>
            <p><?php echo htmlspecialchars($options->description); ?></p>
        </header>
        
        <nav class="sitemap-nav">
            <ul>
                <li><a href="#home">首页</a></li>
                <li><a href="#pages">页面</a></li>
                <li><a href="#categories">分类</a></li>
                <li><a href="#tags">标签</a></li>
                <li><a href="#posts">文章</a></li>
                <li><a href="<?php echo $options->index; ?>/sitemap.xml" target="_blank">XML Sitemap</a></li>
            </ul>
        </nav>
        
        <div class="sitemap-content">
            <!-- 首页 -->
            <section class="sitemap-section" id="home">
                <h2>首页</h2>
                <ul class="sitemap-list">
                    <li>
                        <a href="<?php echo $options->siteUrl; ?>"><?php echo htmlspecialchars($options->title); ?></a>
                        <span class="date">- 网站首页</span>
                    </li>
                </ul>
            </section>
            
            <!-- 页面 -->
            <?php if (!empty($pages)): ?>
            <section class="sitemap-section" id="pages">
                <h2>独立页面</h2>
                <ul class="sitemap-list">
                    <?php foreach ($pages as $page): ?>
                    <li>
                        <a href="<?php echo $options->index . '/' . $page['slug'] . '/'; ?>">
                            <?php echo htmlspecialchars($page['title']); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>
            
            <!-- 分类 -->
            <?php if (!empty($categories)): ?>
            <section class="sitemap-section" id="categories">
                <h2>文章分类</h2>
                <div class="category-tree">
                    <?php
                    $renderCategories = function($categories, $options) use (&$renderCategories) {
                        echo '<ul class="sitemap-list">';
                        foreach ($categories as $category) {
                            echo '<li>';
                            echo '<a href="' . $options->index . '/category/' . urlencode($category['slug']) . '/">';
                            echo htmlspecialchars($category['name']);
                            echo '</a>';
                            echo '<span class="count">(' . intval($category['count']) . ')</span>';
                            
                            if (!empty($category['children'])) {
                                $renderCategories($category['children'], $options);
                            }
                            
                            echo '</li>';
                        }
                        echo '</ul>';
                    };
                    $renderCategories($categories, $options);
                    ?>
                </div>
            </section>
            <?php endif; ?>
            
            <!-- 标签 -->
            <?php if (!empty($tags)): ?>
            <section class="sitemap-section" id="tags">
                <h2>热门标签</h2>
                <div class="tag-cloud">
                    <?php foreach ($tags as $tag): ?>
                    <a href="<?php echo $options->index . '/tag/' . urlencode($tag['slug']) . '/'; ?>">
                        <?php echo htmlspecialchars($tag['name']); ?>
                        <span class="count">(<?php echo $tag['count']; ?>)</span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            
            <!-- 最近文章 -->
            <?php if (!empty($recentPosts)): ?>
            <section class="sitemap-section" id="posts">
                <h2>最近文章</h2>
                <ul class="sitemap-list">
                    <?php foreach ($recentPosts as $post): ?>
                    <li>
                        <a href="<?php echo $options->index . '/' . $post['slug'] . '.html'; ?>">
                            <?php echo htmlspecialchars($post['title']); ?>
                        </a>
                        <span class="date"><?php echo date('Y-m-d', $post['created']); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>
        </div>
        
        <footer>
            <p>
                <a href="<?php echo $options->siteUrl; ?>"><?php echo htmlspecialchars($options->title); ?></a> | 
                <a href="<?php echo $options->index; ?>/sitemap.xml" target="_blank">XML Sitemap</a>
            </p>
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($options->title); ?></p>
        </footer>
    </div>
    
    <a href="#" class="back-to-top" id="backToTop">&uarr;</a>
    
    <script>
        // 返回顶部功能
        document.addEventListener('DOMContentLoaded', function() {
            var backToTop = document.getElementById('backToTop');
            
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    backToTop.classList.add('visible');
                } else {
                    backToTop.classList.remove('visible');
                }
            });
            
            backToTop.addEventListener('click', function(e) {
                e.preventDefault();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            // 平滑滚动到锚点
            document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
                anchor.addEventListener('click', function(e) {
                    var targetId = this.getAttribute('href');
                    if (targetId !== '#') {
                        var targetElement = document.querySelector(targetId);
                        if (targetElement) {
                            e.preventDefault();
                            targetElement.scrollIntoView({
                                behavior: 'smooth'
                            });
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
