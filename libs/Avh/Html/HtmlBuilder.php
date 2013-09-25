<?php
namespace Avh\Html;

/**
 * HTML helper class.
 * Provides generic methods for generating various HTML
 * tags and making output HTML safe.
 */
class HtmlBuilder
{

    /**
     *
     * @var array preferred order of attributes
     */
    //@format_off
        public static $attribute_order = array ( 'action',
                                                    'method',
                                                    'type',
                                                    'id',
                                                    'name',
                                                    'value',
                                                    'href',
                                                    'src',
                                                    'width',
                                                    'height',
                                                    'cols',
                                                    'rows',
                                                    'size',
                                                    'maxlength',
                                                    'rel',
                                                    'media',
                                                    'accept-charset',
                                                    'accept',
                                                    'tabindex',
                                                    'accesskey',
                                                    'alt',
                                                    'title',
                                                    'class',
                                                    'style',
                                                    'selected',
                                                    'checked',
                                                    'readonly',
                                                    'disabled',
                                                   )
        ;
        // @format_on

    /**
     *
     * @var boolean automatically target external URLs to a new window?
     */
    public static $windowed_urls = FALSE;
    private $_base_uri;

    /**
     * Create HTML link anchors.
     * Note that the title is not escaped, to allow
     * HTML elements within links (images, etc).
     *
     * echo AVH2_Html::anchor('/user/profile', 'My Profile');
     *
     * @param string $uri
     *        URL or URI string
     * @param string $title
     *        link text
     * @param array $attributes
     *        HTML anchor attributes
     * @return string
     * @uses AVH2_Html::attributes
     */
    public static function anchor ($uri, $title = NULL, array $attributes = NULL)
    {
        if ( $title === NULL ) {
            // Use the URI as the title
            $title = $uri;
        }

        if ( $uri === '' ) {
            // Only use the base URL
            $uri = home_url('/');
        } else {
            if ( strpos($uri, '://') !== FALSE ) {
                if ( self::$windowed_urls === TRUE and empty($attributes['target']) ) {
                    // Make the link open in a new window
                    $attributes['target'] = '_blank';
                }
            } elseif ( $uri[0] !== '#' ) {
                // Make the URI absolute for non-id anchors
                $uri = plugin_dir_url($uri);
            }
        }

        // Add the sanitized link to the attributes
        $attributes['href'] = $uri;

        return '<a' . self::attributes($attributes) . '>' . $title . '</a>';
    }

    /**
     * Creates an email (mailto:) anchor.
     * Note that the title is not escaped,
     * to allow HTML elements within links (images, etc).
     *
     * echo AVH2_Html::mailto($address);
     *
     * @param string $email
     *        email address to send to
     * @param string $title
     *        link text
     * @param
     *        array %attributes HTML anchor attributes
     * @return string
     * @uses AVH2_Html::attributes
     */
    public static function mailto ($email, $title = NULL, array $attributes = NULL)
    {
        if ( $title === NULL ) {
            // Use the email address as the title
            $title = $email;
        }

        return '<a href="&#109;&#097;&#105;&#108;&#116;&#111;&#058;' . $email . '"' . self::attributes($attributes) . '>' . $title . '</a>';
    }

    /**
     * Creates a image link.
     *
     * echo AVH2_Html::image('media/img/logo.png', array('alt' => 'My Company'));
     *
     * @param string $file
     *        file name
     * @param array $attributes
     *        default attributes
     * @return string
     * @uses URL::base
     * @uses AVH2_Html::attributes
     */
    public static function image ($file, array $attributes = NULL)
    {
        if ( strpos($file, '://') === FALSE ) {
            // Add the base URL
            $file = AVH_PluginController::$base_url . $file;
        }

        // Add the image link
        $attributes['src'] = $file;

        return '<img' . self::attributes($attributes) . ' />';
    }

    /**
     * Compiles an array of HTML attributes into an attribute string.
     * Attributes will be sorted using AVH2_Html::$attribute_order for consistency.
     *
     * echo '<div'.AVH2_Html::attributes($attrs).'>'.$content.'</div>';
     *
     * @param array $attributes
     *        attribute list
     * @return string
     */
    public static function attributes (array $attributes = NULL)
    {
        if ( empty($attributes) )
            return '';

        $_sorted = array();
        foreach ( self::$attribute_order as $_key ) {
            if ( isset($attributes[$_key]) ) {
                // Add the attribute to the sorted list
                $_sorted[$_key] = $attributes[$_key];
            }
        }

        // Combine the sorted attributes
        $attributes = $_sorted + $attributes;

        $_compiled = '';
        foreach ( $attributes as $_key => $_value ) {
            if ( $_value === NULL ) {
                // Skip attributes that have NULL values
                continue;
            }

            if ( is_int($_key) ) {
                // Assume non-associative keys are mirrored attributes
                $_key = $_value;
            }

            // Add the attribute value
            $_compiled .= ' ' . $_key . '="' . esc_attr($_value) . '"';
        }

        return $_compiled;
    }
}
