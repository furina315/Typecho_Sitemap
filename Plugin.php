<?php
/**
 * Sitemap XML 生成插件
 * 
 * 为Typecho博客生成符合搜索引擎标准的Sitemap XML文件
 * 支持文章、页面、分类、标签的Sitemap生成
 * 
 * @package Sitemap
 * @author 喜多ちゃん
 * @version 1.1.0
 * @link https://github.com/furina315
 */

namespace TypechoPlugin\Sitemap;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Form\Element\Checkbox;
use Widget\Options;
use Widget\Contents\Post\Recent;
use Widget\Contents\Page\Rows;
use Widget\Metas\Category\Rows as CategoryRows;
use Widget\Metas\Tag\Rows as TagRows;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Plugin implements PluginInterface
{
    /**
     * 插件激活方法
     * 
     * @return string
     */
    public static function activate()
    {
        // 注册路由
        \Typecho\Plugin::factory('index.php')->begin = __CLASS__ . '::sitemapRoute';
        
        // 注册内容更新钩子，自动更新Sitemap
        \Typecho\Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = __CLASS__ . '::onContentChange';
        \Typecho\Plugin::factory('Widget_Contents_Page_Edit')->finishPublish = __CLASS__ . '::onContentChange';
        \Typecho\Plugin::factory('Widget_Contents_Post_Edit')->delete = __CLASS__ . '::onContentChange';
        \Typecho\Plugin::factory('Widget_Contents_Page_Edit')->delete = __CLASS__ . '::onContentChange';
        
        return _t('Sitemap插件已激活，访问 /sitemap.xml 查看站点地图');
    }

    /**
     * 插件禁用方法
     * 
     * @return string
     */
    public static function deactivate()
    {
        // 仅清理缓存文件，不重新生成（插件正在禁用）
        $cacheDir = __TYPECHO_ROOT_DIR__ . '/usr/cache/sitemap/';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '*.xml');
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        
        // 删除静态 sitemap 文件
        $sitemapFile = __TYPECHO_ROOT_DIR__ . '/sitemap.xml';
        if (file_exists($sitemapFile)) {
            @unlink($sitemapFile);
        }
        
        return _t('Sitemap插件已禁用');
    }

    /**
     * 插件配置面板
     * 
     * @param Form $form
     * @return void
     */
    public static function config(Form $form)
    {
        // 缓存时间设置
        $cacheTime = new Text('cacheTime', NULL, '24', _t('缓存时间'), _t('Sitemap缓存时间（小时），设置为0则不缓存'));
        $form->addInput($cacheTime);
        
        // 每页显示数量
        $pageSize = new Text('pageSize', NULL, '1000', _t('每页URL数量'), _t('单个Sitemap文件包含的最大URL数量（最大50000）'));
        $form->addInput($pageSize);
        
        // 包含内容类型
        $types = new Checkbox('types', array(
            'post' => _t('文章'),
            'page' => _t('独立页面'),
            'category' => _t('分类'),
            'tag' => _t('标签'),
            'author' => _t('作者页')
        ), array('post', 'page', 'category', 'tag'), _t('包含内容类型'));
        $form->addInput($types);
        
        // 文章更新频率
        $postChangefreq = new Radio('postChangefreq', array(
            'always' => _t('always (总是更新)'),
            'hourly' => _t('hourly (每小时)'),
            'daily' => _t('daily (每天)'),
            'weekly' => _t('weekly (每周)'),
            'monthly' => _t('monthly (每月)'),
            'yearly' => _t('yearly (每年)'),
            'never' => _t('never (从不)')
        ), 'weekly', _t('文章更新频率'));
        $form->addInput($postChangefreq);
        
        // 页面更新频率
        $pageChangefreq = new Radio('pageChangefreq', array(
            'always' => _t('always (总是更新)'),
            'hourly' => _t('hourly (每小时)'),
            'daily' => _t('daily (每天)'),
            'weekly' => _t('weekly (每周)'),
            'monthly' => _t('monthly (每月)'),
            'yearly' => _t('yearly (每年)'),
            'never' => _t('never (从不)')
        ), 'monthly', _t('页面更新频率'));
        $form->addInput($pageChangefreq);
        
        // 文章优先级
        $postPriority = new Text('postPriority', NULL, '0.8', _t('文章优先级'), _t('文章页面的优先级（0.0-1.0）'));
        $form->addInput($postPriority);
        
        // 页面优先级
        $pagePriority = new Text('pagePriority', NULL, '0.8', _t('页面优先级'), _t('独立页面的优先级（0.0-1.0）'));
        $form->addInput($pagePriority);
        
        // 首页优先级
        $indexPriority = new Text('indexPriority', NULL, '1.0', _t('首页优先级'), _t('首页的优先级（0.0-1.0）'));
        $form->addInput($indexPriority);
        
        // 分类优先级
        $categoryPriority = new Text('categoryPriority', NULL, '0.6', _t('分类优先级'), _t('分类页面的优先级（0.0-1.0）'));
        $form->addInput($categoryPriority);
        
        // 标签优先级
        $tagPriority = new Text('tagPriority', NULL, '0.5', _t('标签优先级'), _t('标签页面的优先级（0.0-1.0）'));
        $form->addInput($tagPriority);
        
        // 排除的分类
        $excludeCategory = new Text('excludeCategory', NULL, '', _t('排除分类'), _t('要排除的分类MID，多个用逗号分隔'));
        $form->addInput($excludeCategory);
        
        // 排除的文章
        $excludePost = new Text('excludePost', NULL, '', _t('排除文章'), _t('要排除的文章CID，多个用逗号分隔'));
        $form->addInput($excludePost);
        
        // 是否包含文章图片
        $includeImages = new Radio('includeImages', array(
            '1' => _t('是'),
            '0' => _t('否')
        ), '1', _t('包含文章图片'), _t('在Sitemap中包含文章中的图片（Google图片搜索优化）'));
        $form->addInput($includeImages);
        
        // 是否压缩输出
        $compression = new Radio('compression', array(
            '1' => _t('是'),
            '0' => _t('否')
        ), '0', _t('启用Gzip压缩'), _t('启用Gzip压缩可以减少传输大小'));
        $form->addInput($compression);
    }

    /**
     * 个人用户配置面板
     * 
     * @param Form $form
     * @return void
     */
    public static function personalConfig(Form $form)
    {
        // 个人配置（如果有需要）
    }

    /**
     * Sitemap路由处理
     * 
     * @return void
     */
    public static function sitemapRoute()
    {
        $request = \Typecho\Request::getInstance();
        $pathInfo = $request->getPathInfo();
        
        // 检查是否是Sitemap请求
        if (preg_match('/^\/sitemap(_\w+)?\.xml$/i', $pathInfo, $matches)) {
            // 清理所有输出缓冲
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // 设置响应头 - 使用正确的 Content-Type
            header('Content-Type: text/xml; charset=UTF-8');
            header('X-Robots-Tag: noindex, follow');
            
            // 生成Sitemap内容
            $output = self::generateSitemap($matches[1] ?? '');
            
            // 检查Gzip支持
            $options = Options::alloc()->plugin('Sitemap');
            $compression = $options->compression && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && 
                          strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false &&
                          function_exists('gzencode');
            
            if ($compression) {
                header('Content-Encoding: gzip');
                $output = gzencode($output);
            }
            
            header('Content-Length: ' . strlen($output));
            echo $output;
            exit;
        }
    }

    /**
     * 生成Sitemap XML
     * 
     * @param string $type Sitemap类型
     * @return string
     */
    public static function generateSitemap($type = '')
    {
        $options = Options::alloc();
        $pluginOptions = $options->plugin('Sitemap');
        
        // 检查缓存
        $cacheFile = self::getCacheFile($type);
        $cacheTime = intval($pluginOptions->cacheTime) * 3600;
        
        if ($cacheTime > 0 && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
            return file_get_contents($cacheFile);
        }
        
        // 生成新的Sitemap
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        
        // 判断是索引文件还是具体Sitemap
        if (empty($type)) {
            // 生成Sitemap索引
            $xml .= self::generateSitemapIndex($options, $pluginOptions);
        } else {
            // 生成具体Sitemap
            $type = ltrim($type, '_');
            $xml .= self::generateSpecificSitemap($type, $options, $pluginOptions);
        }
        
        // 保存缓存
        if ($cacheTime > 0) {
            @file_put_contents($cacheFile, $xml);
        }
        
        return $xml;
    }

    /**
     * 生成Sitemap索引文件
     * 
     * @param Options $options
     * @param object $pluginOptions
     * @return string
     */
    private static function generateSitemapIndex($options, $pluginOptions)
    {
        $types = $pluginOptions->types;
        $pageSize = intval($pluginOptions->pageSize) ?: 1000;
        
        $xml = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"';
        $xml .= ' xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/siteindex.xsd"';
        $xml .= '>' . "\n";
        
        // 首页Sitemap
        if (in_array('post', $types) || in_array('page', $types)) {
            $xml .= self::createSitemapEntry($options->index . '/sitemap_posts.xml');
        }
        
        // 分类Sitemap
        if (in_array('category', $types)) {
            $xml .= self::createSitemapEntry($options->index . '/sitemap_category.xml');
        }
        
        // 标签Sitemap
        if (in_array('tag', $types)) {
            $xml .= self::createSitemapEntry($options->index . '/sitemap_tag.xml');
        }
        
        // 作者Sitemap
        if (in_array('author', $types)) {
            $xml .= self::createSitemapEntry($options->index . '/sitemap_author.xml');
        }
        
        $xml .= '</sitemapindex>';
        
        return $xml;
    }

    /**
     * 创建Sitemap索引条目
     * 
     * @param string $url
     * @return string
     */
    private static function createSitemapEntry($url)
    {
        return "  <sitemap>\n" .
               "    <loc>" . htmlspecialchars($url) . "</loc>\n" .
               "    <lastmod>" . date('c') . "</lastmod>\n" .
               "  </sitemap>\n";
    }

    /**
     * 生成具体类型的Sitemap
     * 
     * @param string $type
     * @param Options $options
     * @param object $pluginOptions
     * @return string
     */
    private static function generateSpecificSitemap($type, $options, $pluginOptions)
    {
        $xml = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        
        // 如果需要包含图片，添加图片命名空间
        if ($pluginOptions->includeImages && $type == 'posts') {
            $xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        }
        
        // 添加XSI命名空间用于验证
        $xml .= ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"';
        $xml .= ' xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"';
        
        $xml .= '>' . "\n";
        
        switch ($type) {
            case 'posts':
                $xml .= self::generatePostsSitemap($options, $pluginOptions);
                break;
            case 'category':
                $xml .= self::generateCategorySitemap($options, $pluginOptions);
                break;
            case 'tag':
                $xml .= self::generateTagSitemap($options, $pluginOptions);
                break;
            case 'author':
                $xml .= self::generateAuthorSitemap($options, $pluginOptions);
                break;
            default:
                // 默认返回首页
                $xml .= self::generateHomepageEntry($options, $pluginOptions);
                break;
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    }

    /**
     * 生成文章Sitemap
     * 
     * @param Options $options
     * @param object $pluginOptions
     * @return string
     */
    private static function generatePostsSitemap($options, $pluginOptions)
    {
        $xml = '';
        $types = $pluginOptions->types;
        $excludePost = array_map('intval', array_filter(explode(',', $pluginOptions->excludePost)));
        $pageSize = min(intval($pluginOptions->pageSize) ?: 1000, 50000);
        
        // 添加首页
        if (in_array('post', $types) || in_array('page', $types)) {
            $xml .= self::generateHomepageEntry($options, $pluginOptions);
        }
        
        // 添加文章
        if (in_array('post', $types)) {
            $posts = Recent::alloc('pageSize=' . $pageSize);
            while ($posts->next()) {
                if (in_array(intval($posts->cid), $excludePost, true)) {
                    continue;
                }
                
                $xml .= self::createUrlEntry(
                    $posts->permalink,
                    date('c', $posts->modified),
                    $pluginOptions->postChangefreq,
                    $pluginOptions->postPriority,
                    $pluginOptions->includeImages ? $posts : null
                );
            }
        }
        
        // 添加独立页面
        if (in_array('page', $types)) {
            $pages = Rows::alloc();
            while ($pages->next()) {
                if (in_array(intval($pages->cid), $excludePost, true)) {
                    continue;
                }
                
                $xml .= self::createUrlEntry(
                    $pages->permalink,
                    date('c', $pages->modified),
                    $pluginOptions->pageChangefreq,
                    $pluginOptions->pagePriority
                );
            }
        }
        
        return $xml;
    }

    /**
     * 生成分类Sitemap
     * 
     * @param Options $options
     * @param object $pluginOptions
     * @return string
     */
    private static function generateCategorySitemap($options, $pluginOptions)
    {
        $xml = '';
        $excludeCategory = array_map('intval', array_filter(explode(',', $pluginOptions->excludeCategory)));
        
        $categories = CategoryRows::alloc();
        while ($categories->next()) {
            if (in_array(intval($categories->mid), $excludeCategory, true)) {
                continue;
            }
            
            $xml .= self::createUrlEntry(
                $categories->permalink,
                date('c'),
                'daily',
                $pluginOptions->categoryPriority
            );
        }
        
        return $xml;
    }

    /**
     * 生成标签Sitemap
     * 
     * @param Options $options
     * @param object $pluginOptions
     * @return string
     */
    private static function generateTagSitemap($options, $pluginOptions)
    {
        $xml = '';
        
        $tags = TagRows::alloc();
        while ($tags->next()) {
            $xml .= self::createUrlEntry(
                $tags->permalink,
                date('c'),
                'weekly',
                $pluginOptions->tagPriority
            );
        }
        
        return $xml;
    }

    /**
     * 生成作者Sitemap
     * 
     * @param Options $options
     * @param object $pluginOptions
     * @return string
     */
    private static function generateAuthorSitemap($options, $pluginOptions)
    {
        $xml = '';
        $db = \Typecho\Db::get();
        
        $authors = $db->fetchAll($db->select('uid', 'screenName')->from('table.users'));
        foreach ($authors as $author) {
            $xml .= self::createUrlEntry(
                rtrim($options->index, '/') . '/author/' . $author['uid'] . '/',
                date('c'),
                'weekly',
                '0.5'
            );
        }
        
        return $xml;
    }

    /**
     * 生成首页条目
     * 
     * @param Options $options
     * @param object $pluginOptions
     * @return string
     */
    private static function generateHomepageEntry($options, $pluginOptions)
    {
        return self::createUrlEntry(
            $options->siteUrl,
            date('c'),
            'daily',
            $pluginOptions->indexPriority
        );
    }

    /**
     * 创建URL条目
     * 
     * @param string $loc
     * @param string $lastmod
     * @param string $changefreq
     * @param string $priority
     * @param object $content 内容对象（用于提取图片）
     * @return string
     */
    private static function createUrlEntry($loc, $lastmod, $changefreq, $priority, $content = null)
    {
        $xml = "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
        $xml .= "    <lastmod>" . $lastmod . "</lastmod>\n";
        $xml .= "    <changefreq>" . $changefreq . "</changefreq>\n";
        $xml .= "    <priority>" . $priority . "</priority>\n";
        
        // 添加图片信息
        if ($content && !empty($content->content)) {
            $images = self::extractImages($content->content);
            foreach ($images as $image) {
                $xml .= "    <image:image>\n";
                $xml .= "      <image:loc>" . htmlspecialchars($image) . "</image:loc>\n";
                if (!empty($content->title)) {
                    $xml .= "      <image:title>" . htmlspecialchars($content->title) . "</image:title>\n";
                }
                $xml .= "    </image:image>\n";
            }
        }
        
        $xml .= "  </url>\n";
        
        return $xml;
    }

    /**
     * 从内容中提取图片URL
     * 
     * @param string $content
     * @return array
     */
    private static function extractImages($content, $siteUrl = null)
    {
        $images = array();
        
        // 匹配Markdown图片语法 ![alt](url)
        preg_match_all('/!\[.*?\]\((.*?)(?:\s+["\'].*?["\'])?\)/', $content, $matches);
        if (!empty($matches[1])) {
            $images = array_merge($images, $matches[1]);
        }
        
        // 匹配HTML img标签
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches);
        if (!empty($matches[1])) {
            $images = array_merge($images, $matches[1]);
        }
        
        // 过滤并转换相对URL
        if ($siteUrl === null) {
            $siteUrl = Options::alloc()->siteUrl;
        }
        $result = array();
        foreach ($images as $image) {
            $image = trim($image);
            if (empty($image)) continue;
            
            // 跳过 data URI
            if (strpos($image, 'data:') === 0) continue;
            
            // 转换相对URL为绝对URL
            if (strpos($image, 'http') !== 0 && strpos($image, '//') !== 0) {
                $image = rtrim($siteUrl, '/') . '/' . ltrim($image, '/');
            }
            
            $result[] = $image;
        }
        
        // 去重并限制数量
        $result = array_unique($result);
        $result = array_slice($result, 0, 10); // 最多10张图片
        
        return $result;
    }

    /**
     * 获取缓存文件路径
     * 
     * @param string $type
     * @return string
     */
    private static function getCacheFile($type = '')
    {
        $cacheDir = __TYPECHO_ROOT_DIR__ . '/usr/cache/sitemap/';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        
        $filename = empty($type) ? 'sitemap_index.xml' : 'sitemap_' . ltrim($type, '_') . '.xml';
        return $cacheDir . $filename;
    }

    /**
     * 清除缓存
     * 
     * @return void
     */
    /**
     * 清除缓存并重新生成静态 Sitemap
     * 
     * 当内容发生变化时调用（发布/删除文章或页面）
     * 
     * @return void
     */
    public static function onContentChange()
    {
        self::clearCache();
        self::generateStaticSitemap();
    }

    /**
     * 清除缓存
     * 
     * @return void
     */
    public static function clearCache()
    {
        $cacheDir = __TYPECHO_ROOT_DIR__ . '/usr/cache/sitemap/';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '*.xml');
            if ($files) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
        }
    }
    
    /**
     * 生成静态 sitemap.xml 文件到网站根目录
     * 这样可以直接通过文件访问，更容易被搜索引擎抓取
     * 
     * @return void
     */
    public static function generateStaticSitemap()
    {
        try {
            $options = Options::alloc();
            $pluginOptions = $options->plugin('Sitemap');
        } catch (\Exception $e) {
            // 插件配置不可用时（如正在禁用），跳过生成
            return;
        }
        
        $pageSize = min(intval($pluginOptions->pageSize) ?: 1000, 50000);
        $excludePost = array_map('intval', array_filter(explode(',', $pluginOptions->excludePost)));
        
        // 生成主 sitemap.xml（合并所有内容）
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        
        if ($pluginOptions->includeImages) {
            $xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        }
        
        $xml .= ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"';
        $xml .= ' xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"';
        $xml .= '>' . "\n";
        
        $types = $pluginOptions->types;
        
        // 添加首页
        $xml .= self::generateHomepageEntry($options, $pluginOptions);
        
        // 添加文章
        if (in_array('post', $types)) {
            $posts = Recent::alloc('pageSize=' . $pageSize);
            while ($posts->next()) {
                if (in_array(intval($posts->cid), $excludePost, true)) continue;
                $xml .= self::createUrlEntry(
                    $posts->permalink,
                    date('c', $posts->modified),
                    $pluginOptions->postChangefreq,
                    $pluginOptions->postPriority,
                    $pluginOptions->includeImages ? $posts : null
                );
            }
        }
        
        // 添加独立页面
        if (in_array('page', $types)) {
            $pages = Rows::alloc();
            while ($pages->next()) {
                if (in_array(intval($pages->cid), $excludePost, true)) continue;
                $xml .= self::createUrlEntry(
                    $pages->permalink,
                    date('c', $pages->modified),
                    $pluginOptions->pageChangefreq,
                    $pluginOptions->pagePriority
                );
            }
        }
        
        // 添加分类
        if (in_array('category', $types)) {
            $excludeCategory = array_map('intval', array_filter(explode(',', $pluginOptions->excludeCategory)));
            $categories = CategoryRows::alloc();
            while ($categories->next()) {
                if (in_array(intval($categories->mid), $excludeCategory, true)) continue;
                $xml .= self::createUrlEntry(
                    $categories->permalink,
                    date('c'),
                    'daily',
                    $pluginOptions->categoryPriority
                );
            }
        }
        
        // 添加标签
        if (in_array('tag', $types)) {
            $tags = TagRows::alloc();
            while ($tags->next()) {
                $xml .= self::createUrlEntry(
                    $tags->permalink,
                    date('c'),
                    'weekly',
                    $pluginOptions->tagPriority
                );
            }
        }
        
        $xml .= '</urlset>';
        
        // 保存到网站根目录，确保使用 UTF-8 编码
        $sitemapFile = __TYPECHO_ROOT_DIR__ . '/sitemap.xml';
        
        // 确保目录可写
        if (is_writable(dirname($sitemapFile))) {
            $result = @file_put_contents($sitemapFile, $xml, LOCK_EX);
            if ($result === false) {
                error_log('Sitemap: 无法写入静态 sitemap.xml 文件');
            }
        }
    }

}
