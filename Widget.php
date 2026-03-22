<?php
/**
 * Sitemap Widget 类
 * 
 * 提供Sitemap相关的Widget功能
 * 
 * @package Sitemap
 * @author 喜多ちゃん
 * @version 1.1.0
 */

namespace TypechoPlugin\Sitemap;

use Typecho\Widget;
use Typecho\Db;
use Widget\Options;
use Widget\Base\Contents;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class SitemapWidget extends Widget
{
    /**
     * 数据库对象
     * 
     * @var Db
     */
    private $db;

    /**
     * 构造函数
     * 
     * @param mixed $request
     * @param mixed $response
     * @param mixed $params
     */
    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
        $this->db = Db::get();
    }

    /**
     * 获取数据库对象
     * 
     * @return Db
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * 获取最近更新的内容
     * 
     * @param int $limit
     * @return array
     */
    public function getRecentContents($limit = 10)
    {
        return $this->db->fetchAll($this->db->select()
            ->from('table.contents')
            ->where('status = ?', 'publish')
            ->order('modified', Db::SORT_DESC)
            ->limit($limit));
    }

    /**
     * 获取热门文章
     * 
     * @param int $limit
     * @return array
     */
    public function getPopularPosts($limit = 10)
    {
        return $this->db->fetchAll($this->db->select()
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('status = ?', 'publish')
            ->order('commentsNum', Db::SORT_DESC)
            ->limit($limit));
    }

    /**
     * 获取分类树
     * 
     * @param int $parent
     * @return array
     */
    public function getCategoryTree($parent = 0)
    {
        $categories = $this->db->fetchAll($this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'category')
            ->where('parent = ?', $parent)
            ->order('order', Db::SORT_ASC));
        
        $result = array();
        foreach ($categories as $category) {
            $category['children'] = $this->getCategoryTree($category['mid']);
            $result[] = $category;
        }
        
        return $result;
    }

    /**
     * 获取标签云
     * 
     * @param int $limit
     * @return array
     */
    public function getTagCloud($limit = 50)
    {
        return $this->db->fetchAll($this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'tags')
            ->order('count', Db::SORT_DESC)
            ->limit($limit));
    }

    /**
     * 获取归档列表
     * 
     * @return array
     */
    public function getArchives()
    {
        $options = Options::alloc();
        $db = Db::get();
        
        $archives = $db->fetchAll($db->select(array('created' => 'date'), array('COUNT(cid)' => 'count'))
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('status = ?', 'publish')
            ->group('DATE_FORMAT(FROM_UNIXTIME(created), "%Y-%m")')
            ->order('created', Db::SORT_DESC));
        
        return $archives;
    }

    /**
     * 检查URL是否在Sitemap中
     * 
     * @param string $url
     * @return bool
     */
    public function isUrlInSitemap($url)
    {
        // 这里可以实现URL检查逻辑
        // 例如查询数据库或解析缓存的Sitemap文件
        return true;
    }

    /**
     * 获取Sitemap文件信息
     * 
     * @return array
     */
    public function getSitemapInfo()
    {
        $cacheDir = __TYPECHO_ROOT_DIR__ . '/usr/cache/sitemap/';
        $info = array();
        
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '*.xml');
            foreach ($files as $file) {
                $info[] = array(
                    'name' => basename($file),
                    'size' => filesize($file),
                    'modified' => filemtime($file),
                    'url_count' => $this->countUrlsInFile($file)
                );
            }
        }
        
        return $info;
    }

    /**
     * 统计文件中的URL数量
     * 
     * @param string $file
     * @return int
     */
    private function countUrlsInFile($file)
    {
        $content = file_get_contents($file);
        preg_match_all('/<url>/', $content, $matches);
        return count($matches[0]);
    }

    /**
     * 生成HTML格式的Sitemap（用于用户浏览）
     * 
     * @return string
     */
    public function generateHtmlSitemap()
    {
        $options = Options::alloc();
        $html = '<div class="sitemap-html">' . "\n";
        
        // 首页
        $html .= '<h2>' . _t('首页') . '</h2>' . "\n";
        $html .= '<ul><li><a href="' . $options->siteUrl . '">' . $options->title . '</a></li></ul>' . "\n";
        
        // 页面
        $pages = $this->db->fetchAll($this->db->select()
            ->from('table.contents')
            ->where('type = ?', 'page')
            ->where('status = ?', 'publish')
            ->order('order', Db::SORT_ASC));
        
        if (!empty($pages)) {
            $html .= '<h2>' . _t('页面') . '</h2>' . "\n";
            $html .= '<ul>' . "\n";
            foreach ($pages as $page) {
                $html .= '<li><a href="' . $options->index . '/' . $page['slug'] . '/">' . $page['title'] . '</a></li>' . "\n";
            }
            $html .= '</ul>' . "\n";
        }
        
        // 分类
        $categories = $this->getCategoryTree();
        if (!empty($categories)) {
            $html .= '<h2>' . _t('分类') . '</h2>' . "\n";
            $html .= $this->renderCategoryTree($categories);
        }
        
        // 标签
        $tags = $this->db->fetchAll($this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'tags')
            ->order('count', Db::SORT_DESC)
            ->limit(100));
        
        if (!empty($tags)) {
            $html .= '<h2>' . _t('热门标签') . '</h2>' . "\n";
            $html .= '<div class="tag-cloud">' . "\n";
            foreach ($tags as $tag) {
                $html .= '<a href="' . $options->index . '/tag/' . urlencode($tag['slug']) . '/">' . $tag['name'] . '</a> ';
            }
            $html .= '</div>' . "\n";
        }
        
        // 最近文章
        $posts = $this->db->fetchAll($this->db->select()
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('status = ?', 'publish')
            ->order('created', Db::SORT_DESC)
            ->limit(50));
        
        if (!empty($posts)) {
            $html .= '<h2>' . _t('最近文章') . '</h2>' . "\n";
            $html .= '<ul>' . "\n";
            foreach ($posts as $post) {
                $html .= '<li><a href="' . $options->index . '/' . $post['slug'] . '.html">' . $post['title'] . '</a> <span>' . date('Y-m-d', $post['created']) . '</span></li>' . "\n";
            }
            $html .= '</ul>' . "\n";
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * 渲染分类树
     * 
     * @param array $categories
     * @return string
     */
    private function renderCategoryTree($categories)
    {
        $options = Options::alloc();
        $html = '<ul>' . "\n";
        
        foreach ($categories as $category) {
            $html .= '<li><a href="' . $options->index . '/category/' . urlencode($category['slug']) . '/">' . $category['name'] . '</a>';
            
            if (!empty($category['children'])) {
                $html .= $this->renderCategoryTree($category['children']);
            }
            
            $html .= '</li>' . "\n";
        }
        
        $html .= '</ul>' . "\n";
        
        return $html;
    }
}
