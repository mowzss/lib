<?php

namespace mowzs\lib\helper;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;

class QrcodeHelper
{
    /**
     * 获取二维码内容接口
     * @param string $text 二维码文本内容
     * @param string $logo
     * @return ResultInterface
     */
    public static function getQrcode(string $text, string $logo = ''): ResultInterface
    {
        $return = Builder::create()->data($text)->size(300)->margin(15)
            ->writer(new PngWriter())->encoding(new Encoding('UTF-8'))
            ->writerOptions([])->validateResult(false)
            ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh());
        if (!empty($logo)) {
            $return = $return->logoPath($logo);
        }
        return $return->build();
    }
}
