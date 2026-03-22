/**
 * Sitemap 插件 JavaScript
 * 
 * 后台管理页面的交互功能
 */

(function($) {
    'use strict';

    // Sitemap Admin 对象
    var SitemapAdmin = {
        
        /**
         * 初始化
         */
        init: function() {
            this.bindEvents();
            this.loadStats();
        },

        /**
         * 绑定事件
         */
        bindEvents: function() {
            var self = this;
            
            // 清除缓存按钮
            $('#sitemap-clear-cache').on('click', function(e) {
                e.preventDefault();
                self.clearCache();
            });
            
            // Ping搜索引擎按钮
            $('#sitemap-ping').on('click', function(e) {
                e.preventDefault();
                self.pingSearchEngines();
            });
            
            // 刷新统计按钮
            $('#sitemap-refresh-stats').on('click', function(e) {
                e.preventDefault();
                self.loadStats();
            });
            
            // 预览Sitemap按钮
            $('#sitemap-preview').on('click', function(e) {
                e.preventDefault();
                window.open($(this).data('url'), '_blank');
            });
        },

        /**
         * 加载统计信息
         */
        loadStats: function() {
            var self = this;
            
            $.ajax({
                url: window.sitemapAjaxUrl || '/action/sitemap',
                type: 'GET',
                data: { do: 'stats' },
                dataType: 'json',
                beforeSend: function() {
                    self.showLoading('正在加载统计信息...');
                },
                success: function(response) {
                    if (response.success) {
                        self.updateStats(response.stats);
                        self.updateCacheInfo(response.cache);
                    } else {
                        self.showError('加载统计信息失败');
                    }
                },
                error: function() {
                    self.showError('网络错误，请稍后重试');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },

        /**
         * 更新统计显示
         */
        updateStats: function(stats) {
            $('#stat-posts').text(stats.posts);
            $('#stat-pages').text(stats.pages);
            $('#stat-categories').text(stats.categories);
            $('#stat-tags').text(stats.tags);
            $('#stat-total').text(stats.total);
        },

        /**
         * 更新缓存信息
         */
        updateCacheInfo: function(cache) {
            var statusText = cache.enabled ? 
                '已启用 (' + cache.files + ' 个文件)' : 
                '未启用';
            $('#cache-status').text(statusText);
        },

        /**
         * 清除缓存
         */
        clearCache: function() {
            var self = this;
            
            if (!confirm('确定要清除Sitemap缓存吗？')) {
                return;
            }
            
            $.ajax({
                url: window.sitemapAjaxUrl || '/action/sitemap',
                type: 'GET',
                data: { do: 'clear' },
                dataType: 'json',
                beforeSend: function() {
                    self.showLoading('正在清除缓存...');
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('缓存已清除');
                        self.loadStats();
                    } else {
                        self.showError('清除缓存失败');
                    }
                },
                error: function() {
                    self.showError('网络错误，请稍后重试');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },

        /**
         * Ping搜索引擎
         */
        pingSearchEngines: function() {
            var self = this;
            
            if (!confirm('确定要通知搜索引擎更新Sitemap吗？')) {
                return;
            }
            
            $.ajax({
                url: window.sitemapAjaxUrl || '/action/sitemap',
                type: 'GET',
                data: { do: 'ping' },
                dataType: 'json',
                beforeSend: function() {
                    self.showLoading('正在通知搜索引擎...');
                },
                success: function(response) {
                    if (response.success) {
                        var results = response.results;
                        var message = '通知结果：\n';
                        
                        if (results.google) {
                            message += 'Google: ' + (results.google.status === 'success' ? '成功' : '失败') + '\n';
                        }
                        if (results.bing) {
                            message += 'Bing: ' + (results.bing.status === 'success' ? '成功' : '失败') + '\n';
                        }
                        if (results.baidu) {
                            message += '百度: ' + results.baidu.message + '\n';
                        }
                        
                        alert(message);
                    } else {
                        self.showError('通知失败');
                    }
                },
                error: function() {
                    self.showError('网络错误，请稍后重试');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },

        /**
         * 显示加载状态
         */
        showLoading: function(message) {
            $('#sitemap-loading').text(message).show();
        },

        /**
         * 隐藏加载状态
         */
        hideLoading: function() {
            $('#sitemap-loading').hide();
        },

        /**
         * 显示成功消息
         */
        showSuccess: function(message) {
            alert(message);
        },

        /**
         * 显示错误消息
         */
        showError: function(message) {
            alert('错误：' + message);
        }
    };

    // 文档加载完成后初始化
    $(document).ready(function() {
        if ($('.sitemap-admin').length > 0) {
            SitemapAdmin.init();
        }
    });

    // 暴露到全局
    window.SitemapAdmin = SitemapAdmin;

})(jQuery);
