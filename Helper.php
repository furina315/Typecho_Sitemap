<?php
/**
 * Sitemap Helper 类
 * 
 * 提供Sitemap相关的辅助方法
 * 
 * @package Sitemap
 * @author 喜多ちゃん
 * @version 1.1.0
 */

namespace TypechoPlugin\Sitemap;

use Typecho\Db;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Helper
{
    /**
     * 验证URL是否有效
     * 
     * @param string $url
     * @return bool
     */
    public static function isValidUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 转义XML特殊字符
     * 
     * @param string $string
     * @return string
     */
    public static function escapeXml($string)
    {
        return htmlspecialchars($string, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * 格式化日期为W3C格式
     * 
     * @param int $timestamp
     * @return string
     */
    public static function formatW3cDate($timestamp)
    {
        return date('c', $timestamp);
    }

    /**
     * 获取站点URL
     * 
     * @return string
     */
    public static function getSiteUrl()
    {
        return Options::alloc()->siteUrl;
    }

    /**
     * 获取首页URL
     * 
     * @return string
     */
    public static function getIndexUrl()
    {
        return Options::alloc()->index;
    }

    /**
     * 获取文章URL
     * 
     * @param array $content
     * @return string
     */
    public static function getContentUrl($content)
    {
        $options = Options::alloc();
        
        // 根据Typecho的永久链接设置构建URL
        $type = $content['type'];
        $slug = $content['slug'];
        $created = $content['created'];
        
        if ($type == 'page') {
            return rtrim($options->index, '/') . '/' . $slug . '/';
        } else {
            // 文章URL格式
            return rtrim($options->index, '/') . '/' . $slug . '.html';
        }
    }

    /**
     * 获取分类URL
     * 
     * @param array $category
     * @return string
     */
    public static function getCategoryUrl($category)
    {
        $options = Options::alloc();
        return rtrim($options->index, '/') . '/category/' . urlencode($category['slug']) . '/';
    }

    /**
     * 获取标签URL
     * 
     * @param array $tag
     * @return string
     */
    public static function getTagUrl($tag)
    {
        $options = Options::alloc();
        return rtrim($options->index, '/') . '/tag/' . urlencode($tag['slug']) . '/';
    }

    /**
     * 获取作者URL
     * 
     * @param int $uid
     * @return string
     */
    public static function getAuthorUrl($uid)
    {
        $options = Options::alloc();
        return rtrim($options->index, '/') . '/author/' . $uid . '/';
    }

    /**
     * 获取归档URL
     * 
     * @param string $year
     * @param string $month
     * @return string
     */
    public static function getArchiveUrl($year, $month = null)
    {
        $options = Options::alloc();
        $url = rtrim($options->index, '/') . '/' . $year . '/';
        if ($month) {
            $url .= $month . '/';
        }
        return $url;
    }

    /**
     * 获取Feed URL
     * 
     * @param string $type
     * @return string
     */
    public static function getFeedUrl($type = 'rss')
    {
        $options = Options::alloc();
        return rtrim($options->index, '/') . '/feed/' . ($type == 'atom' ? 'atom/' : '');
    }

    /**
     * 获取文章缩略图
     * 
     * @param array $content
     * @return string|null
     */
    public static function getThumbnail($content)
    {
        // 从内容中提取第一张图片
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content['text'], $matches)) {
            $src = $matches[1];
            // 转换为绝对URL
            if (strpos($src, 'http') !== 0) {
                $src = self::getSiteUrl() . ltrim($src, '/');
            }
            return $src;
        }
        
        return null;
    }

    /**
     * 获取文章摘要
     * 
     * @param string $content
     * @param int $length
     * @return string
     */
    public static function getExcerpt($content, $length = 200)
    {
        // 去除HTML标签
        $text = strip_tags($content);
        // 去除多余空白
        $text = preg_replace('/\s+/', ' ', $text);
        // 截取指定长度
        if (mb_strlen($text, 'UTF-8') > $length) {
            $text = mb_substr($text, 0, $length, 'UTF-8') . '...';
        }
        
        return trim($text);
    }

    /**
     * 检查内容是否公开
     * 
     * @param array $content
     * @return bool
     */
    public static function isPublicContent($content)
    {
        return $content['status'] == 'publish' && 
               $content['password'] == '' && 
               $content['hidden'] == 0;
    }

    /**
     * 获取内容更新频率
     * 
     * @param int $created
     * @param int $modified
     * @return string
     */
    public static function getChangeFreq($created, $modified)
    {
        $now = time();
        $age = $now - $modified;
        $updateInterval = $modified - $created;
        
        // 根据更新频率判断
        if ($updateInterval < 86400) { // 1天内更新
            return 'daily';
        } elseif ($updateInterval < 604800) { // 1周内更新
            return 'weekly';
        } elseif ($updateInterval < 2592000) { // 1月内更新
            return 'monthly';
        } elseif ($age < 31536000) { // 1年内
            return 'monthly';
        } else {
            return 'yearly';
        }
    }

    /**
     * 计算内容优先级
     * 
     * @param array $content
     * @return float
     */
    public static function calculatePriority($content)
    {
        $priority = 0.5;
        
        // 根据内容类型调整
        if ($content['type'] == 'page') {
            $priority += 0.1;
        }
        
        // 根据评论数量调整
        if ($content['commentsNum'] > 0) {
            $priority += min($content['commentsNum'] / 100, 0.2);
        }
        
        // 根据内容年龄调整
        $age = time() - $content['created'];
        if ($age < 2592000) { // 1个月内
            $priority += 0.1;
        } elseif ($age < 15552000) { // 6个月内
            $priority += 0.05;
        }
        
        return min(round($priority, 1), 1.0);
    }

    /**
     * 获取数据库实例
     * 
     * @return Db
     */
    public static function getDb()
    {
        return Db::get();
    }

    /**
     * 获取插件选项
     * 
     * @return object
     */
    public static function getOptions()
    {
        return Options::alloc()->plugin('Sitemap');
    }

    /**
     * 记录日志
     * 
     * @param string $message
     * @param string $type
     * @return void
     */
    public static function log($message, $type = 'info')
    {
        $logDir = __TYPECHO_ROOT_DIR__ . '/usr/logs/';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . 'sitemap_' . date('Y-m-d') . '.log';
        $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($type) . '] ' . $message . PHP_EOL;
        
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * 获取缓存目录
     * 
     * @return string
     */
    public static function getCacheDir()
    {
        $cacheDir = __TYPECHO_ROOT_DIR__ . '/usr/cache/sitemap/';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        return $cacheDir;
    }

    /**
     * 清理旧缓存文件
     * 
     * @param int $maxAge 最大保留时间（小时）
     * @return int 清理的文件数量
     */
    public static function cleanOldCache($maxAge = 168)
    {
        $cacheDir = self::getCacheDir();
        $count = 0;
        $maxAgeSeconds = $maxAge * 3600;
        
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '*.xml');
            foreach ($files as $file) {
                if (is_file($file) && (time() - filemtime($file)) > $maxAgeSeconds) {
                    @unlink($file);
                    $count++;
                }
            }
        }
        
        return $count;
    }

    /**
     * 获取文件大小（人类可读）
     * 
     * @param int $bytes
     * @return string
     */
    public static function formatFileSize($bytes)
    {
        $units = array('B', 'KB', 'MB', 'GB');
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * 检查搜索引擎爬虫
     * 
     * @return string|false
     */
    public static function detectSearchBot()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $bots = array(
            'google' => 'Googlebot',
            'bing' => 'bingbot',
            'baidu' => 'Baiduspider',
            'yandex' => 'YandexBot',
            'sogou' => 'Sogou',
            '360' => '360Spider',
        );
        
        foreach ($bots as $name => $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return $name;
            }
        }
        
        return false;
    }
}
