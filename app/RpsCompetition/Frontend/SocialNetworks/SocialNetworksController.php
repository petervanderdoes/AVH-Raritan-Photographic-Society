<?php

namespace RpsCompetition\Frontend\SocialNetworks;

use Illuminate\Container\Container as IlluminateContainer;
use RpsCompetition\Common\Helper as CommonHelper;
use RpsCompetition\Libs\Container;

/**
 * Class SocialNetworksHelper
 *
 * @package RpsCompetition\Frontend\SocialNetworks
 */
class SocialNetworksController extends Container
{
    protected $model;

    /**
     * Constructor
     *
     * @param IlluminateContainer $container
     */
    public function __construct(IlluminateContainer $container)
    {
        $this->setContainer($container);
        $this->setSettings($this->container->make('Settings'));
        $this->setOptions($this->container->make('OptionsGeneral'));
        $this->setTemplateEngine($this->container->make('Templating', array('template_dir' => $this->settings->get('template_dir') . '/social-networks', 'cache_dir' => $this->settings->get('upload_dir') . '/twig-cache/')));

        $this->model = new SocialNetworksModel();
    }

    /**
     * Add the fb-root div
     *
     * @internal Hook: suffusion_before_page
     *
     */
    public function actionAddFbRoot()
    {
        echo $this->render('fb-root.html.twig');
    }

    /**
     * Prepare for rendering of the social buttons.
     * We add this method as we can not add data that might be needed during the rendering.
     * We can not add the data at the hook level.
     *
     * @internal Hook: rps-social-buttons
     *
     */
    public function actionSocialButtons()
    {
        global $post;
        $options = get_option('avh-rps');

        if ($post->post_type == 'page' && ($post->ID == $options['members_page'] || $post->post_parent == $options['members_page'])) {
            return;
        }

        if (has_shortcode($post->post_content, 'theme-my-login')) {
            return;
        }

        $data = $this->model->dataSocialButtons($this->model->getNetworks());

        echo $this->render('buttons.html.twig',$data);
    }

    /**
     * Preparation for rendering content in the WordPress footer.
     * We add this method as we can not add data that might be needed during the rendering.
     * We can not add the data at the hook level.
     *
     * @internal Hook: wp_footer
     *
     */
    public function actionWpFooter()
    {
        $data = $this->model->dataApiNetworks();
        echo $this->render('in-footer.html.twig', $data);
    }

    /**
     * Preparation for rendering content in the WordPress header.
     * We add this method as we can not add data that might be needed during the rendering.
     * We can not add the data at the hook level.
     *
     * @internal Hook: wp_head
     *
     */
    public function actionWpHead()
    {
        echo $this->render('in-header.html.twig');
    }

    /**
     * Initialize all the scripts, styles and hooks used by the class
     *
     * @param array $data
     *
     */
    public function initClass($data)
    {
        $rps_social_buttons_script = $data['script'];
        $social_buttons_style = $data['style'];

        wp_register_script('rps-competition.social-buttons.script', CommonHelper::getPluginUrl($rps_social_buttons_script, $this->settings->get('javascript_dir')), array(), 'to_remove', true);
        wp_enqueue_script('rps-competition.social-buttons.script');

        wp_enqueue_style('rps-competition.fontawesome.style', 'http://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css"');
        wp_enqueue_style('rps-competition.social-buttons.style', CommonHelper::getPluginUrl($social_buttons_style, $this->settings->get('css_dir')), array(), 'to_remove');

        add_action('wp_head', array($this, 'actionWpHead'));
        add_action('wp_footer', array($this, 'actionWpFooter'), 999);
        add_action('suffusion_before_page', array($this, 'actionAddFbRoot'));
        add_action('rps-social-buttons', array($this, 'actionSocialButtons'));
    }
}