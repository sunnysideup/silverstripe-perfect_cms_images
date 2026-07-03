<?php

declare(strict_types=1);

namespace Sunnysideup\PerfectCmsImages\Control;

use Override;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Convert;

/**
 * Class \Sunnysideup\PerfectCmsImages\Control\PlaceHolderImageCreator
 */
class PlaceHolderImageCreator extends Controller
{
    public static function get_link(int $width, int $height, ?string $text = null)
    {
        $actions = [
            'width' => $width,
            'height' => $height,
            'text' => $text,
        ];
        $actions = http_build_query($actions);
        return Controller::join_links(Director::absoluteBaseURL(), self::$url_segment, 'myimage') .
            '?' . $actions;
    }

    #[Override]
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

    public function myimage(HTTPRequest $request): HTTPResponse
    {
        if (Director::isLive()) {
            return $this->plainTextResponse('404-image', 404);
        }

        $width = (int) $request->getVar('width');
        $height = (int) $request->getVar('height');
        $text = Convert::raw2htmlid($request->getVar('text'));

        if ($width < 1 || $height < 1) {
            return $this->plainTextResponse('Width/height must be >= 1.', 400);
        }

        if (! extension_loaded('gd')) {
            return $this->plainTextResponse('GD extension is not enabled.', 500);
        }

        $img = imagecreatetruecolor($width, $height);
        if ($img === false) {
            return $this->plainTextResponse('Failed to create image.', 500);
        }

        // Random background (avoid very dark)
        $bgR = mt_rand(40, 255);
        $bgG = mt_rand(40, 255);
        $bgB = mt_rand(40, 255);

        $bg = imagecolorallocate($img, $bgR, $bgG, $bgB);
        imagefilledrectangle($img, 0, 0, $width - 1, $height - 1, $bg);

        // Label
        $label = $text ?: $width . 'x' . $height;

        // Choose a readable text colour based on brightness
        $brightness = (int) (0.299 * $bgR + 0.587 * $bgG + 0.114 * $bgB);
        $textColour = $brightness > 140
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
        imagestring($img, $font, $x, $y, $label, $textColour);

        // Capture the PNG output so it can be returned via the response body
        // rather than being echoed directly.
        ob_start();
        imagepng($img);
        $body = (string) ob_get_clean();

        // Free the image resource.
        imagedestroy($img);

        $response = $this->getResponse();
        $response->addHeader('Content-Type', 'image/png');
        $response->setBody($body);

        return $response;
    }

    protected function plainTextResponse(string $message, int $statusCode): HTTPResponse
    {
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        $response->addHeader('Content-Type', 'text/plain; charset=utf-8');
        $response->setBody($message);

        return $response;
    }
}
