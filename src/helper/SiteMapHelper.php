<?php

namespace mowzs\lib\helper;

class SiteMapHelper
{
    /**
     * 节点参数
     * @var array
     */
    private array $items = [];

    /**
     * 配置参数
     * @var array
     */
    private array $config = [];

    /**
     * 初始化
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        if (empty($this->config['path'])) {
            $this->config['path'] = './';
        }
        if (empty($this->config['pathurl'])) {
            $this->config['pathurl'] = request()->domain();
        }
        if (empty($this->config['title'])) {
            $this->config['title'] = '网站地图';
        }
        if (empty($this->config['tpl_path'])) {
            $this->config['tpl_path'] = __DIR__ . '/tpl/';
        }

        if (!is_dir($this->config['path'])) {
            mkdir($this->config['path'], 0755, true);
        }
    }

    /**
     * 设置标题
     * @param $title
     * @return void
     */
    public function setTitle($title): void
    {
        $this->config['title'] = $title;
    }

    /**
     * 设置文件保存路径
     * @param $filename
     */
    public function setPath($filename): void
    {
        $end_str = substr($filename, -1);
        if ($end_str != '/' && $end_str != "\/") {
            $filename .= '/';
        }
        $this->config['path'] = $filename;
    }

    /**
     * 设置模板路径
     * @param string $tplPath
     */
    public function setTplPath(string $tplPath): void
    {
        $this->config['tpl_path'] = rtrim($tplPath, '/') . '/';
    }

    /**
     * 添加一个节点
     * @param string $url
     * @param string $lastmod
     * @param float $priority between 1~0.5
     * @param string $changefreq Always 经常,hourly 每小时,daily 每天,weekly 每周,monthly 每月,yearly 每年,never 从不
     */
    public function addItem(string $url, string $lastmod = '', float $priority = 0.8, string $changefreq = 'daily'): void
    {
        $lastmod = $lastmod ?? date('Y-m-d');
        $this->items[] = [
            'url' => $url,
            'priority' => $priority,
            'changefreq' => $changefreq,
            'lastmod' => $lastmod,
        ];
    }

    /**
     * 生成文件
     * @param string $type xml html txt
     * @param int $chunk
     */
    public function generated(string $type, string $name = 'sitemap', $chunk = null)
    {
        if (!$this->items) {
            die('请添加数据->addItem');
        } else {
            if (!$this->config['path']) {
                die('请设置文件存放路径->setPath');
            }
        }
        $chunk = $chunk ?? count($this->items);
        $items = array_chunk($this->items, $chunk);
        $function_type = 'handle' . ucfirst($type);
        foreach ($items as $k => $item) {
            $data = $this->$function_type($item);
            if ($k) {
                $name .= $k;
            }
            $name .= '.' . $type;
            $this->saveFile($name, $data);
        }
        $pathurl = $this->config['pathurl'] . $name;
        return $pathurl;
    }

    /**
     * 保存数据生成文件
     * @param $file_name
     * @param $data
     */
    private function saveFile($file_name, $data): void
    {
        $filename = $this->config['path'] . $file_name;
        $handle = fopen($filename, 'w+');
        !$handle && die("文件打开失败");
        flock($handle, LOCK_EX);
        if (!empty($data)) {
            fwrite($handle, $data);
        }
        flock($handle, LOCK_UN);
        fclose($handle);
        0 && @chmod($filename, 0777);
    }

    /**
     * 处理HTML模板
     * @param $arr
     * @return string
     */
    private function handleHtml($arr): string
    {
        $templatePath = $this->config['tpl_path'] . 'html.tpl';
        if (!file_exists($templatePath)) {
            die("HTML模板文件不存在: {$templatePath}");
        }

        $template = file_get_contents($templatePath);
        $replacements = [
            '{{title}}' => $this->config['title'],
            '{{items}}' => $this->generateHtmlItems($arr),
        ];

        return strtr($template, $replacements);
    }

    /**
     * 生成HTML中的项目列表
     * @param $arr
     * @return string
     */
    private function generateHtmlItems($arr): string
    {
        $html = '';
        foreach ($arr as $item) {
            $html .= '<a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['url']) . '</a>';
        }
        return $html;
    }

    /**
     * 处理TXT模板
     * @param $arr
     * @return string
     */
    private function handleTxt($arr): string
    {
        $txt = '';
        foreach ($arr as $item) {
            $txt .= $item['url'] . "\r\n";
        }
        return $txt;
    }

    /**
     * 处理XML模板
     * @param $arr
     * @return string
     */
    private function handleXml($arr): string
    {
        $templatePath = $this->config['tpl_path'] . 'xml.tpl';
        if (!file_exists($templatePath)) {
            die("XML模板文件不存在: {$templatePath}");
        }

        $template = file_get_contents($templatePath);
        $replacements = [
            '{{items}}' => $this->generateXmlItems($arr),
        ];

        return strtr($template, $replacements);
    }

    /**
     * 生成XML中的项目列表
     * @param $arr
     * @return string
     */
    private function generateXmlItems($arr): string
    {
        $xml = '';
        foreach ($arr as $item) {
            $xml .= "\t\t<url>\n";
            $xml .= "\t\t\t<loc>" . htmlspecialchars($item['url']) . "</loc>\r\n";
            $xml .= "\t\t\t<priority>" . htmlspecialchars($item['priority']) . "</priority>\r\n";
            $xml .= "\t\t\t<lastmod>" . htmlspecialchars($item['lastmod']) . "</lastmod>\r\n";
            $xml .= "\t\t\t<changefreq>" . htmlspecialchars($item['changefreq']) . "</changefreq>\r\n";
            $xml .= "\t\t</url>\n";
        }
        return $xml;
    }
}
