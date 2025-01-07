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
    protected function getLinks(): string
    {
        if ($this->simple) {
            return '';
        }

        $block = [
            'first' => null,
            'slider' => null,
            'last' => null,
        ];

        $side = 3;
        $window = $side * 2;

        if ($this->lastPage < $window + 6) {
            $block['first'] = $this->getUrlRange(1, $this->lastPage);
        } elseif ($this->currentPage <= $window) {
            $block['first'] = $this->getUrlRange(1, $window + 1);
            //$block['last'] = $this->getUrlRange($this->lastPage - 1, $this->lastPage);
        } elseif ($this->currentPage > ($this->lastPage - $window)) {
            //$block['first'] = $this->getUrlRange(1, 1);
            $block['last'] = $this->getUrlRange($this->lastPage - $window, $this->lastPage);
        } else {
            //$block['first'] = $this->getUrlRange(1, 1);
            $block['slider'] = $this->getUrlRange($this->currentPage - $side, $this->currentPage + $side);
            //$block['last'] = $this->getUrlRange($this->lastPage, $this->lastPage);
        }

        $html = '';

        if (is_array($block['first'])) {
            $html .= $this->getUrlLinks($block['first']);
        }

        if (is_array($block['slider'])) {
            //$html .= $this->getDots();
            $html .= $this->getUrlLinks($block['slider']);
        }

        if (is_array($block['last'])) {
            //$html .= $this->getDots();
            $html .= $this->getUrlLinks($block['last']);
        }

        return $html;
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
