<?php
declare(strict_types=1);


namespace happy\admin\libs\helper;

use happy\admin\libs\Helper;

/**
 * SEO 头部元信息生成（Open Graph / Twitter / 字节跳动 / JSON-LD）
 *
 * 由 common.php 中的 seo_head_meta() 全局函数委托调用，
 * 模板通过 {:seo_head_meta(...)} 直接使用。
 */
class SeoHeadMetaHelper extends Helper
{
    /**
     * 渲染 SEO 头部元信息
     *
     * @param mixed  $info       当前页面实体（详情/栏目/工具配置）
     * @param mixed  $list       当前列表数据
     * @param mixed  $total      列表总数
     * @param mixed  $web_config 全站配置
     * @param string $type       输出类型：all|og|json_ld
     * @return string
     */
    public function render(mixed $info = [], mixed $list = [], mixed $total = 0, mixed $web_config = [], string $type = 'all'): string
    {
        $info = $this->toArray($info);
        $config = $this->toArray($web_config);
        $request = $this->app->request;

        $system = $this->toArray($config['system'] ?? []);
        $layer = $request->layer(true);
        $module = $this->toArray($config[$layer] ?? []);

        $siteName = trim((string)($system['site_name'] ?? ''));
        $siteDomain = rtrim((string)($system['site_domain'] ?? ''), '/');
        $siteLogo = (string)($system['site_logo_image'] ?? ($system['square_logo'] ?? ''));
        $siteDescription = (string)($system['seo_description'] ?? '');
        $pageUrl = (string)$request->url(true);

        $title = trim((string)($info['seo_title'] ?? '')) ?: trim((string)($info['title'] ?? ''));
        if ($title === '') {
            $title = trim((string)($system['seo_title'] ?? '')) ?: $siteName;
        }

        $description = (string)($info['seo_description'] ?? '');
        if ($description === '') {
            $description = (string)($info['description'] ?? '');
        }
        if ($description === '') {
            $description = (string)($info['content'] ?? '');
        }
        if ($description === '') {
            $description = (string)($system['seo_description'] ?? ($module['seo_description'] ?? ''));
        }
        $description = get_word(del_html($description), 120);

        $image = (string)($info['image'] ?? '') ?: $siteLogo;

        $listItems = $this->buildListItems($list, $siteDomain);

        // 分页页标题补充"第N页"，与 <title> 保持一致
        $page = (int)$request->get('page', 1);
        if ($page > 1) {
            $title .= ' - 第' . $page . '页';
        }

        // 详情页判定：实体的 cid 表明它属于某个栏目；工具/栏目配置没有 cid
        // 工具页判定：实体来自 tools_content_1（含 node），且没有栏目关系
        $isTool = empty($info['column']) && !empty($info['node']);
        $isDetail = !empty($info['id']) && !empty($info['cid']) && !$isTool;
        $isList = !$isDetail && !$isTool && count($listItems) > 0;

        $ogHtml = $this->buildOgHtml($info, $siteName, $title, $description, $pageUrl, $image, $isDetail);
        $jsonLdHtml = $this->buildJsonLdHtml(
            $info, $siteName, $siteDomain, $siteLogo, $siteDescription, $pageUrl,
            $title, $description, $image, $listItems, $total, $isDetail, $isList, $isTool
        );

        $type = strtolower($type);
        if ($type === 'og') {
            return $ogHtml;
        }
        if ($type === 'json_ld' || $type === 'jsonld') {
            return $jsonLdHtml;
        }

        return $ogHtml . $jsonLdHtml;
    }

    /**
     * 构建 Open Graph / Twitter / 字节跳动 meta 标签
     */
    private function buildOgHtml(array $info, string $siteName, string $title, string $description, string $pageUrl, string $image, bool $isDetail): string
    {
        $og = [
            'og:type' => $isDetail ? 'article' : 'website',
            'og:site_name' => $siteName,
            'og:title' => $title,
            'og:description' => $description,
            'og:url' => $pageUrl,
        ];
        if ($image !== '') {
            $og['og:image'] = $image;
        }
        if ($isDetail && !empty($info['create_time'])) {
            $og['article:published_time'] = $this->dateIso($info['create_time']);
        }

        $html = '';
        foreach ($og as $property => $content) {
            if ($content === '') {
                continue;
            }
            $html .= '<meta property="' . $this->escape($property) . '" content="' . $this->escape($content) . '"/>' . "\n";
        }
        foreach (['title' => $title, 'description' => $description, 'image' => $image] as $name => $content) {
            if ($content === '') {
                continue;
            }
            $html .= '<meta name="twitter:' . $this->escape($name) . '" content="' . $this->escape($content) . '"/>' . "\n";
        }
        $html .= '<meta name="twitter:card" content="summary_large_image"/>' . "\n";

        // 字节跳动搜索：详情页发布/更新时间
        if ($isDetail) {
            $published = $this->dateIso($info['create_time'] ?? '');
            if ($published !== '') {
                $html .= '<meta property="bytedance:published_time" content="' . $this->escape($published) . '"/>' . "\n";
                $dateModified = $this->dateIso($info['update_time'] ?? ($info['create_time'] ?? ''));
                $html .= '<meta property="bytedance:updated_time" content="' . $this->escape($dateModified !== '' ? $dateModified : $published) . '"/>' . "\n";
            }
        }

        return $html;
    }

    /**
     * 构建 JSON-LD 结构化数据脚本
     */
    private function buildJsonLdHtml(
        array $info, string $siteName, string $siteDomain, string $siteLogo, string $siteDescription, string $pageUrl,
        string $title, string $description, string $image, array $listItems, mixed $total,
        bool $isDetail, bool $isList, bool $isTool = false
    ): string {
        $organization = [
            '@type' => 'Organization',
            '@id' => $siteDomain . '/#organization',
            'name' => $siteName,
            'url' => $siteDomain . '/',
        ];
        if ($siteLogo !== '') {
            $organization['logo'] = ['@type' => 'ImageObject', 'url' => $siteLogo];
        }

        $website = [
            '@type' => 'WebSite',
            '@id' => $siteDomain . '/#website',
            'url' => $siteDomain . '/',
            'name' => $siteName,
            'inLanguage' => 'zh-CN',
            'publisher' => ['@id' => $siteDomain . '/#organization'],
        ];
        if ($siteDescription !== '') {
            $website['description'] = $siteDescription;
        }
        if ($siteDomain !== '') {
            $website['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $siteDomain . '/search?query={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        $graph = [$organization, $website];

        $webPage = [
            '@type' => 'WebPage',
            '@id' => $pageUrl . '#webpage',
            'url' => $pageUrl,
            'name' => $title,
            'isPartOf' => ['@id' => $siteDomain . '/#website'],
            'inLanguage' => 'zh-CN',
        ];
        if ($description !== '') {
            $webPage['description'] = $description;
        }

        if ($isDetail) {
            $article = [
                '@type' => 'Article',
                '@id' => $pageUrl . '#article',
                'headline' => $title,
                'url' => $pageUrl,
                'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $pageUrl],
                'isPartOf' => ['@id' => $siteDomain . '/#website'],
            ];
            if ($description !== '') {
                $article['description'] = $description;
            }
            if ($image !== '') {
                $article['image'] = $image;
            }
            $published = $this->dateIso($info['create_time'] ?? '');
            if ($published !== '') {
                $article['datePublished'] = $published;
                $dateModified = $this->dateIso($info['update_time'] ?? ($info['create_time'] ?? ''));
                $article['dateModified'] = $dateModified !== '' ? $dateModified : $published;
            }
            $article['author'] = [
                '@type' => 'Person',
                'name' => (string)($info['author_name'] ?? '') ?: $siteName,
            ];
            $article['publisher'] = ['@id' => $siteDomain . '/#organization'];

            $crumbs = [
                ['@type' => 'ListItem', 'position' => 1, 'name' => $siteName ?: '首页', 'item' => $siteDomain . '/'],
            ];
            $column = $this->toArray($info['column'] ?? []);
            if (!empty($column['title']) && !empty($info['cid'])) {
                $crumbs[] = [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => (string)$column['title'],
                    'item' => urls('columns/index', ['id' => $info['cid']], true, true),
                ];
            }
            $crumbs[] = [
                '@type' => 'ListItem',
                'position' => count($crumbs) + 1,
                'name' => $title,
                'item' => $pageUrl,
            ];

            $graph[] = $article;
            $graph[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => $crumbs,
            ];
        } elseif ($isTool) {
            // 工具/计算器页：WebApplication，而非 Article
            $graph[] = $webPage;
            $application = [
                '@type' => 'WebApplication',
                '@id' => $pageUrl . '#application',
                'name' => $title,
                'url' => $pageUrl,
                'isPartOf' => ['@id' => $siteDomain . '/#website'],
                'applicationCategory' => 'UtilitiesApplication',
                'operatingSystem' => 'All',
                'inLanguage' => 'zh-CN',
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0',
                    'priceCurrency' => 'CNY',
                ],
            ];
            if ($description !== '') {
                $application['description'] = $description;
            }
            if ($image !== '') {
                $application['image'] = $image;
            }
            $graph[] = $application;
            $graph[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => $siteName ?: '首页', 'item' => $siteDomain . '/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => $title, 'item' => $pageUrl],
                ],
            ];
        } elseif ($isList) {
            $graph[] = $webPage;
            $graph[] = [
                '@type' => 'CollectionPage',
                '@id' => $pageUrl . '#collection',
                'url' => $pageUrl,
                'name' => $title,
                'isPartOf' => ['@id' => $siteDomain . '/#website'],
                'inLanguage' => 'zh-CN',
            ];
            $graph[] = [
                '@type' => 'ItemList',
                'itemListElement' => $listItems,
                'numberOfItems' => (int)$total > 0 ? (int)$total : count($listItems),
            ];
            $graph[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => $siteName ?: '首页', 'item' => $siteDomain . '/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => $title, 'item' => $pageUrl],
                ],
            ];
        } else {
            $graph[] = $webPage;
        }

        $json = json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graph],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return $json === false
            ? ''
            : '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }

    /**
     * 构建列表项（用于 ItemList）
     */
    private function buildListItems(mixed $list, string $siteDomain): array
    {
        $items = [];
        if (!is_iterable($list)) {
            return $items;
        }
        foreach ($list as $row) {
            $row = $this->toArray($row);
            $name = trim((string)($row['title'] ?? ($row['name'] ?? '')));
            $itemUrl = $this->absoluteUrl(trim((string)($row['url'] ?? '')), $siteDomain);
            if ($name === '' || $itemUrl === '') {
                continue;
            }
            $items[] = [
                '@type' => 'ListItem',
                'position' => count($items) + 1,
                'name' => $name,
                'url' => $itemUrl,
            ];
        }
        return $items;
    }

    /**
     * 混合值转数组
     */
    private function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return (array)$value->toArray();
            }
            if ($value instanceof \Traversable) {
                return iterator_to_array($value);
            }
            return (array)$value;
        }
        return [];
    }

    /**
     * HTML 属性转义
     */
    private function escape(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * 日期转 ISO 8601
     */
    private function dateIso(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }
        $timestamp = is_numeric($value) ? (int)$value : strtotime((string)$value);
        return $timestamp ? date('c', $timestamp) : '';
    }

    /**
     * 相对 URL 补全为绝对 URL
     */
    private function absoluteUrl(string $value, string $domain): string
    {
        if ($value === '' || preg_match('#^https?://#i', $value)) {
            return $value;
        }
        return $domain . '/' . ltrim($value, '/');
    }
}
