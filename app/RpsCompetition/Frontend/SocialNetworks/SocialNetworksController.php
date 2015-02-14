<?php

namespace RpsCompetition\Frontend\SocialNetworks;

use Illuminate\Container\Container as IlluminateContainer;
use RpsCompetition\Libs\Controller;

if (!class_exists('AVH_RPS_Client')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

/**
 * Class SocialNetworksController
 *
 * @package RpsCompetition\Frontend\SocialNetworks
 */
class SocialNetworksController extends Controller
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

        $data = $this->model->getSocialButtons($this->model->getNetworks());

        echo $this->render('buttons.html.twig', $data);
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
        $data = $this->model->getNetworksWithApiEnabled();
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
}
