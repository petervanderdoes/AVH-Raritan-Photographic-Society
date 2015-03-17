<?php
namespace RpsCompetition\Frontend\SocialNetworks;

use RpsCompetition\Application;

/**
 * Class SocialNetworksController
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\SocialNetworks
 */
class SocialNetworksController
{
    /** @var SocialNetworksModel */
    protected $model;
    private $container;
    private $options;
    private $settings;
    /** @var  SocialNetworksView */
    private $view;

    /**
     * Constructor
     *
     * @param Application $container
     */
    public function __construct(Application $container)
    {
        $this->container = $container;
        $this->settings = $this->container->make('Settings');
        $this->options = $this->container->make('OptionsGeneral');

        $this->model = $this->container->make('SocialNetworksModel');
        $this->view = $this->container->make('SocialNetworksView');
    }

    /**
     * Add the fb-root div
     *
     * @internal Hook: suffusion_before_page
     *
     */
    public function actionAddFbRoot()
    {
        $this->view->display('fb-root.html.twig');
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

        $this->view->display('buttons.html.twig', $data);
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
        $this->view->display('in-footer.html.twig', $data);
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
        $this->view->display('in-header.html.twig');
    }
}
