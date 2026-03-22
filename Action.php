<?php
/**
 * Sitemap Action 类
 * 
 * 处理Sitemap相关的独立请求
 * 
 * @package Sitemap
 * @author 喜多ちゃん
 * @version 1.1.0
 */

namespace TypechoPlugin\Sitemap;

use Typecho\Widget;
use Typecho\Db;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Action extends Widget
{
    /**
     * 执行动作
     * 
     * @return void
     */
    public function action()
    {
        // 权限验证：仅管理员可操作
        $this->user->pass('administrator', true);
        
        $do = $this->request->get('do');
        
        switch ($do) {
            case 'ping':
                $this->pingSearchEngines();
                break;
            case 'clear':
                $this->clearCache();
                break;
            case 'stats':
                $this->getStats();
                break;
            default:
                $this->response->throwJson(array('error' => 'Invalid action'));
        }
    }

    /**
     * 通知搜索引擎
     * 
     * @return void
     */
    private function pingSearchEngines()
    {
        $options = Options::alloc();
        $sitemapUrl = $options->index . '/sitemap.xml';
        
        $results = array();
        
        // Ping Google
        $googlePingUrl = 'http://www.google.com/webmasters/tools/ping?sitemap=' . urlencode($sitemapUrl);
        $results['google'] = $this->sendPing($googlePingUrl);
        
        // Ping Bing
        $bingPingUrl = 'http://www.bing.com/webmaster/ping.aspx?siteMap=' . urlencode($sitemapUrl);
        $results['bing'] = $this->sendPing($bingPingUrl);
        
        // Ping Baidu (通过百度站长平台API)
        // 注意：百度需要API密钥，这里仅作示例
        $results['baidu'] = array('status' => 'manual', 'message' => '请手动提交到百度搜索资源平台');
        
        $this->response->throwJson(array(
            'success' => true,
            'results' => $results
        ));
    }

    /**
     * 发送Ping请求
     * 
     * @param string $url
     * @return array
     */
    private function sendPing($url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Typecho Sitemap Plugin/1.0.0');
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            return array(
                'status' => $httpCode == 200 ? 'success' : 'error',
                'http_code' => $httpCode,
                'error' => $error ?: null
            );
        }
        
        // 回退方案：使用 file_get_contents
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 10,
                'user_agent' => 'Typecho Sitemap Plugin/1.0.0',
                'ignore_errors' => true
            )
        ));
        
        $response = @file_get_contents($url, false, $context);
        $httpCode = 0;
        if (isset($http_response_header[0])) {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
            $httpCode = isset($matches[1]) ? intval($matches[1]) : 0;
        }
        
        return array(
            'status' => $httpCode == 200 ? 'success' : 'error',
            'http_code' => $httpCode
        );
    }

    /**
     * 清除缓存
     * 
     * @return void
     */
    private function clearCache()
    {
        Plugin::clearCache();
        
        $this->response->throwJson(array(
            'success' => true,
            'message' => '缓存已清除'
        ));
    }

    /**
     * 获取统计信息
     * 
     * @return void
     */
    private function getStats()
    {
        $db = Db::get();
        $options = Options::alloc();
        
        // 统计文章数量
        $postCount = $db->fetchObject($db->select(array('COUNT(cid)' => 'num'))
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('status = ?', 'publish'))->num;
        
        // 统计页面数量
        $pageCount = $db->fetchObject($db->select(array('COUNT(cid)' => 'num'))
            ->from('table.contents')
            ->where('type = ?', 'page')
            ->where('status = ?', 'publish'))->num;
        
        // 统计分类数量
        $categoryCount = $db->fetchObject($db->select(array('COUNT(mid)' => 'num'))
            ->from('table.metas')
            ->where('type = ?', 'category'))->num;
        
        // 统计标签数量
        $tagCount = $db->fetchObject($db->select(array('COUNT(mid)' => 'num'))
            ->from('table.metas')
            ->where('type = ?', 'tag'))->num;
        
        // 检查缓存状态
        $cacheDir = __TYPECHO_ROOT_DIR__ . '/usr/cache/sitemap/';
        $cacheFiles = is_dir($cacheDir) ? glob($cacheDir . '*.xml') : array();
        $cacheStatus = !empty($cacheFiles);
        
        $this->response->throwJson(array(
            'success' => true,
            'stats' => array(
                'posts' => intval($postCount),
                'pages' => intval($pageCount),
                'categories' => intval($categoryCount),
                'tags' => intval($tagCount),
                'total' => intval($postCount + $pageCount + $categoryCount + $tagCount)
            ),
            'cache' => array(
                'enabled' => $cacheStatus,
                'files' => count($cacheFiles),
                'dir' => $cacheDir
            ),
            'sitemap_url' => $options->index . '/sitemap.xml'
        ));
    }
}
