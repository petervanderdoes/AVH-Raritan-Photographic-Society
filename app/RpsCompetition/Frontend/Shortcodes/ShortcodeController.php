<?php
namespace RpsCompetition\Frontend\Shortcodes;

use RpsCompetition\Application;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class ShortcodeController
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes
 */
final class ShortcodeController
{
    /** @var \Avh\Html\HtmlBuilder */
    private $html;

    /**
     * Constructor
     *
     * @param Application $container
     */
    public function __construct(Application $container)
    {
        $this->html = $container->make('HtmlBuilder');
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
