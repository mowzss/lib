<?php

namespace mowzs\lib\extend;


class SitemapIndexExtend
{
    /**
     * 存放所有子 Sitemap 的信息
     * @var array<int, array{loc: string, lastmod?: string}>
     */
    private array $sitemaps = [];

    /**
     * 添加一个 Sitemap 文件地址
     *
     * @param string $url Sitemap 文件的完整 URL
     * @param string|null $lastmod 最后修改时间 (YYYY-MM-DD 或 ISO8601 格式)
     */
    public function addSitemap(string $url, ?string $lastmod = null): void
    {
        $this->sitemaps[] = [
            'loc' => htmlspecialchars($url, ENT_XML1, 'UTF-8'),
            'lastmod' => $lastmod ? htmlspecialchars($lastmod, ENT_XML1, 'UTF-8') : null,
        ];
    }

    /**
     * 生成 Sitemap Index XML 内容
     *
     * @return string
     */
    public function generate(): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($this->sitemaps as $sitemap) {
            $xml .= "\t<sitemap>\n";
            $xml .= "\t\t<loc>{$sitemap['loc']}</loc>\n";
            if (!empty($sitemap['lastmod'])) {
                $xml .= "\t\t<lastmod>{$sitemap['lastmod']}</lastmod>\n";
            }
            $xml .= "\t</sitemap>\n";
        }

        $xml .= "</sitemapindex>";

        return $xml;
    }

    /**
     * 将生成的 Sitemap Index 保存为文件
     *
     * @param string $filePath 要保存的文件路径（含文件名）
     * @return bool 是否成功写入
     */
    public function saveToFile(string $filePath): bool
    {
        $content = $this->generate();
        return file_put_contents($filePath, $content, LOCK_EX) !== false;
    }

    /**
     * 清空当前所有已添加的 sitemap
     */
    public function clear(): void
    {
        $this->sitemaps = [];
    }
}
