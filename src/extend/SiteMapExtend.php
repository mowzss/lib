<?php
declare(strict_types=1);

namespace happy\admin\libs\extend;

use RuntimeException;

class SiteMapExtend
{
    private array $items = [];
    private array $config = [];
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'path' => './',
            'pathurl' => request()->domain(), // 注意：CLI下request()可能为空，建议在Command中显式传入
            'title' => '网站地图',
            'tpl_path' => __DIR__ . '/tpl/',
        ], $config);
        
        if (!is_dir($this->config['path'])) {
            if (!mkdir($this->config['path'], 0755, true) && !is_dir($this->config['path'])) {
                throw new RuntimeException("Sitemap目录创建失败: {$this->config['path']}");
            }
        }
    }
    
    /**
     * 添加节点（强制类型安全）
     */
    public function addItem(string $url, string $lastmod = '', float $priority = 0.8, string $changefreq = 'daily'): void
    {
        // 防御性编程：确保 lastmod 有值且为合法字符串
        if ($lastmod === '' || $lastmod === null) {
            $lastmod = date('Y-m-d');
        }
        
        $this->items[] = [
            'url' => $url,
            'lastmod' => $lastmod,
            'priority' => max(0.0, min(1.0, $priority)), // 限制在 0~1 之间
            'changefreq' => $changefreq,
        ];
    }
    
    /**
     * 生成文件并返回访问URL
     * @throws RuntimeException
     */
    public function generated(string $type, string $name = 'sitemap'): string
    {
        if (empty($this->items)) {
            throw new RuntimeException('Sitemap生成失败：未添加任何数据 (addItem)');
        }
        if (empty($this->config['path'])) {
            throw new RuntimeException('Sitemap生成失败：未设置文件存放路径');
        }
        
        $functionType = 'handle' . ucfirst($type);
        if (!method_exists($this, $functionType)) {
            throw new RuntimeException("不支持的Sitemap类型: {$type}");
        }
        
        // 【修复】移除内部的 array_chunk，分片逻辑应由 Command 层控制
        // 避免 Command 已经按 5000 条分片后，这里又意外触发二次分片导致文件名错乱
        $data = $this->$functionType($this->items);
        
        $fileName = $name . '.' . $type;
        $this->saveFile($fileName, $data);
        
        // 清空当前实例的数据，防止复用实例时数据污染
        $this->items = [];
        
        return rtrim($this->config['pathurl'], '/') . '/' . $fileName;
    }
    
    private function saveFile(string $fileName, string $data): void
    {
        $filePath = rtrim($this->config['path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
        
        // 使用 file_put_contents 替代 fopen/fwrite，更原子化且不易出错
        $result = file_put_contents($filePath, $data, LOCK_EX);
        if ($result === false) {
            throw new RuntimeException("Sitemap文件写入失败: {$filePath}");
        }
    }
    
    /**
     * 获取当前已添加的条目数
     */
    public function count(): int
    {
        return count($this->items);
    }
    
    // ==================== 格式处理器 ====================
    
    private function handleXml(array $arr): string
    {
        $templatePath = $this->config['tpl_path'] . 'xml.tpl';
        if (!file_exists($templatePath)) {
            throw new RuntimeException("XML模板文件不存在: {$templatePath}");
        }
        
        return strtr(file_get_contents($templatePath), [
            '{{items}}' => $this->generateXmlItems($arr),
        ]);
    }
    
    private function generateXmlItems(array $arr): string
    {
        $xml = '';
        foreach ($arr as $item) {
            $xml .= "\t\t<url>\n";
            $xml .= "\t\t\t<loc>" . htmlspecialchars($item['url'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
            $xml .= "\t\t\t<lastmod>" . htmlspecialchars($item['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
            // priority 是数字，不需要 htmlspecialchars，格式化即可
            $xml .= "\t\t\t<priority>" . number_format($item['priority'], 1) . "</priority>\n";
            $xml .= "\t\t\t<changefreq>" . htmlspecialchars($item['changefreq'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</changefreq>\n";
            $xml .= "\t\t</url>\n";
        }
        return $xml;
    }
    
    private function handleTxt(array $arr): string
    {
        $lines = array_column($arr, 'url');
        return implode("\n", $lines) . "\n";
    }
    
    private function handleHtml(array $arr): string
    {
        $templatePath = $this->config['tpl_path'] . 'html.tpl';
        if (!file_exists($templatePath)) {
            throw new RuntimeException("HTML模板文件不存在: {$templatePath}");
        }
        
        return strtr(file_get_contents($templatePath), [
            '{{title}}' => htmlspecialchars($this->config['title'], ENT_QUOTES, 'UTF-8'),
            '{{items}}' => $this->generateHtmlItems($arr),
        ]);
    }
    
    private function generateHtmlItems(array $arr): string
    {
        $html = '';
        foreach ($arr as $item) {
            $safeUrl = htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8');
            $html .= "<a href=\"{$safeUrl}\">{$safeUrl}</a>\n";
        }
        return $html;
    }
}
