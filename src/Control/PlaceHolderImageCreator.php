<?php

declare(strict_types=1);

namespace Sunnysideup\PerfectCmsImages\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injectable;


/**
 * Class \Sunnysideup\PerfectCmsImages\Control\PlaceHolderImageCreator
 *
 */
class PlaceHolderImageCreator extends Controller
{

    public static function get_link(int $width, int $height, ?string $text = null)
    {
        $actions = ['width' => $width, 'height' => $height, 'text' => $text];
        $actions = http_build_query($actions);
        return Controller::join_links(Director::absoluteBaseURL(), self::$url_segment, 'myimage') .
            '?' . $actions;
    }

    public function Link($actions = null)
    {
        if (is_array($actions)) {
            $actions = http_build_query($actions);
        } elseif (is_string($actions)) {
            // do nothing
        }
        return Controller::join_links(Director::absoluteBaseURL(), self::$url_segment, 'myimage') .
            '?' . $actions;
    }

    private static $url_segment = 'placeholderimagecreator';

    private static array $allowed_actions = [
        'myimage',
    ];

    function myimage($request): void
    {
        if (Director::isLive()) {
            echo '404-image';
            return;
        }
        $width = (int) $request->getVar('width');
        $height = (int) $request->getVar('height');
        $text = Convert::raw2htmlid($request->getVar('text'));

        if ($width < 1 || $height < 1) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Width/height must be >= 1.';
            return;
        }

        if (!extension_loaded('gd')) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'GD extension is not enabled.';
            return;
        }

        $img = imagecreatetruecolor($width, $height);
        if ($img === false) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Failed to create image.';
            return;
        }

        // Random background (avoid very dark)
        $bgR = mt_rand(40, 255);
        $bgG = mt_rand(40, 255);
        $bgB = mt_rand(40, 255);

        $bg = imagecolorallocate($img, $bgR, $bgG, $bgB);
        imagefilledrectangle($img, 0, 0, $width - 1, $height - 1, $bg);

        // Label
        $label = $text ? $text : $width . 'x' . $height;

        // Choose a readable text colour based on brightness
        $brightness = (int) (0.299 * $bgR + 0.587 * $bgG + 0.114 * $bgB);
        $text = $brightness > 140
            ? imagecolorallocate($img, 0, 0, 0)
            : imagecolorallocate($img, 255, 255, 255);

        // Built-in font size: 1..5
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($label);
        $textHeight = imagefontheight($font);

        $x = (int) max(0, (($width - $textWidth) / 2));
        $y = (int) max(0, (($height - $textHeight) / 2));

        // Optional: tiny shadow for contrast
        $shadow = $brightness > 140
            ? imagecolorallocate($img, 255, 255, 255)
            : imagecolorallocate($img, 0, 0, 0);

        imagestring($img, $font, $x + 1, $y + 1, $label, $shadow);
        imagestring($img, $font, $x, $y, $label, $text);

        $response = $this->getResponse();
        $response->addHeader('Content-Type', 'image/png');
        imagepng($img);
    }
}
