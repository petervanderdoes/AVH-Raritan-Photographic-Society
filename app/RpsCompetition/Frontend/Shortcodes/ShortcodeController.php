<?php
namespace RpsCompetition\Frontend\Shortcodes;

use RpsCompetition\Application;

/**
 * Class ShortcodeController
 *
 * @package   RpsCompetition\Frontend\Shortcodes
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
final class ShortcodeController
{
    /** @var \Avh\Html\HtmlBuilder */
    private $html;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->html = $app->make('HtmlBuilder');
    }

    /**
     * Display an obfuscated email link.
     *
     * @param array  $attr    The shortcode argument list. Allowed arguments:
     *                        - email
     *                        - HTML Attributes
     * @param string $content The content of a shortcode when it wraps some content.
     * @param string $tag     The shortcode name
     *
     * @return string
     */
    public function shortcodeEmail($attr, $content, $tag)
    {
        return $this->html->mailto($attr['email'], $content, $attr);
    }
}
