<?php

namespace mowzs\lib;

use think\paginator\driver\Bootstrap;


class Page extends Bootstrap
{
    /**
     * 生成一个激活的按钮
     * @param string $text
     * @return string
     */
    protected function getActivePageWrapper(string $text): string
    {
        return '<span class="active">' . $text . '</span>';
    }

    /**
     * 生成一个可点击的按钮
     * @param string $url
     * @param string $page
     * @return string
     */
    protected function getAvailablePageWrapper(string $url, string $page): string
    {
        return '<a href="' . htmlentities($url) . '">' . $page . '</a>';
    }

    /**
     * 生成统计信息
     * @return string
     */
    protected function info()
    {
        return "<a class='page-info'>" . $this->currentPage . '/' . $this->lastPage
            //.  "/" . $this->total
            . "</a>";
    }

    /**
     * 生成一个禁用的按钮
     * @param string $text
     * @return string
     */
    protected function getDisabledTextWrapper(string $text): string
    {
        return '<span class="disabled">' . $text . '</span>';
    }

    /**
     * 生成省略号按钮
     * @return string
     */
    protected function getDots(): string
    {
        return $this->getDisabledTextWrapper('...');
    }

    /**
     * 页码按钮
     * @return string
     */
    /**
     * 页码按钮（修复版：支持省略号，避免过多页码显示）
     * @return string
     */
    /**
     * 只显示当前页前后各两页，共最多5个页码
     * @return string
     */
    /**
     * 只显示当前页前后各三页，共最多7个页码
     * @return string
     */
    protected function getLinks(): string
    {
        if ($this->simple) {
            return '';
        }

        $current = $this->currentPage();
        $last = $this->lastPage;
        $side = 3; // 前后各显示3页

        // 计算页码范围
        $start = max(1, $current - $side);
        $end = min($last, $current + $side);

        // 如果总页数不足7页，从第1页开始补齐
        if ($end - $start + 1 < 7) {
            // 尝试向左或向右扩展
            if ($start == 1) {
                $end = min($last, $start + 6); // 确保至少7个页码
            } elseif ($end == $last) {
                $start = max(1, $end - 6); // 确保至少7个页码
            }
        }

        $urls = $this->getUrlRange($start, $end);

        return $this->getUrlLinks($urls);
    }

    /**
     * 下一页按钮
     * @param string $text
     * @return string
     */
    protected function getNextButton(string $text = '下一页'): string
    {
        if (!$this->hasMore) {
            return $this->getDisabledTextWrapper($text);
        }

        $url = $this->url($this->currentPage() + 1);

        return $this->getPageLinkWrapper($url, $text);
    }

    /**
     * 生成普通页码按钮
     * @param string $url
     * @param string $page
     * @return string
     */
    protected function getPageLinkWrapper(string $url, string $page): string
    {
        if ($this->currentPage() == $page) {
            return $this->getActivePageWrapper($page);
        }

        return $this->getAvailablePageWrapper($url, $page);
    }

    /**
     * 上一页按钮
     * @param string $text
     * @return string
     */
    protected function getPreviousButton(string $text = '上一页'): string
    {

        if ($this->currentPage() <= 1) {
            return $this->getDisabledTextWrapper($text);
        }

        $url = $this->url(
            $this->currentPage() - 1
        );

        return $this->getPageLinkWrapper($url, $text);
    }

    /**
     * 批量生成页码按钮.
     * @param array $urls
     * @return string
     */
    protected function getUrlLinks(array $urls): string
    {
        $html = '';

        foreach ($urls as $page => $url) {
            $html .= $this->getPageLinkWrapper($url, $page);
        }

        return $html;
    }

    /**
     * 渲染分页html
     * @return mixed
     */
    public function render()
    {
        if ($this->hasPages()) {
            if ($this->simple) {
                return sprintf(
                    '<div class="page-pager">%s %s</div>',
                    $this->getWapPreviousButton(),
                    $this->getWapNextButton(),
                );
            } elseif (request()->isMobile()) {
                return sprintf(
                    '<div class="page-pagination">%s %s %s %s</div>',
                    $this->getPreviousButton(),
                    $this->getHome(),
                    $this->getEnd(),
                    $this->getNextButton()
                );
            } else {
                return sprintf(
                    '<div class="page-pagination">%s %s %s %s %s</div>',
                    $this->getPreviousButton(),
                    $this->getHome(),
                    $this->getLinks(),
                    $this->getEnd(),
                    $this->getNextButton()
                );
            }
        }
    }

    /**
     * 上一页按钮
     * @param string $text
     * @return string
     */
    protected function getWapPreviousButton(string $text = '上一页'): string
    {

        if ($this->currentPage() <= 1) {
            return $this->getDisabledTextWrapper($text);
        }

        $url = $this->url(
            $this->currentPage() - 1
        );
        return $this->getPageLinkWrapper($url, $text);
    }

    /**
     * 下一页按钮
     * @param string $text
     * @return string
     */
    protected function getWapNextButton(string $text = '下一页'): string
    {
        if (!$this->hasMore) {
            return $this->getDisabledTextWrapper($text);
        }

        $url = $this->url($this->currentPage() + 1);

        return $this->getPageLinkWrapper($url, $text);
    }

    /**
     * 首页
     * @param string $text
     * @return string
     */
    protected function getHome(string $text = '首页'): string
    {
        if ($this->currentPage() < 1) {
            return $this->getDisabledTextWrapper($text);
        }
        $url = $this->url(1);
        return $this->getPageLinkWrapper($url, $text);
    }

    /**
     * 尾页
     * @param string $text
     * @return string
     */
    protected function getEnd(string $text = '尾页'): string
    {
        if (!$this->hasMore) {
            return $this->getDisabledTextWrapper($text);
        }
        $url = $this->url($this->lastPage());
        return $this->getPageLinkWrapper($url, $text);
    }
}
