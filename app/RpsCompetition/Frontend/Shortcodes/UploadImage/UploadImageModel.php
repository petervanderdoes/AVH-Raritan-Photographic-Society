<?php
namespace RpsCompetition\Frontend\Shortcodes\UploadImage;

use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Entity\Forms\UploadImage as UploadImageEntity;
use RpsCompetition\Form\Type\UploadImageType;
use RpsCompetition\Settings;
use Symfony\Component\Form\FormFactory;

/**
 * Class UploadImageModel
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Frontend\Shortcodes\UploadImage
 */
class UploadImageModel
{
    private $form_factory;
    private $request;
    private $settings;

    /**
     * Constructor
     *
     * @param FormFactory       $form_factory
     * @param Settings          $settings
     * @param IlluminateRequest $request
     */
    public function __construct(FormFactory $form_factory, Settings $settings, IlluminateRequest $request)
    {
        $this->settings = $settings;
        $this->request = $request;
        $this->form_factory = $form_factory;
    }

    /**
     * Get medium subset based on the request.
     *
     * @return string
     */
    public function getMediumSubset()
    {
        $medium_subset = $this->request->input('m', 'digital');
        $medium_subset = ucfirst($medium_subset);

        return $medium_subset;
    }

    /**
     * Get a new form.
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    public function getNewForm()
    {
        global $post;

        $action = home_url('/' . get_page_uri($post->ID));
        $medium_subset = $this->getMediumSubset();

        $ref = $this->request->input('wp_get_referer', wp_get_referer());

        $entity = new UploadImageEntity();
        $entity->setWpGetReferer($ref);
        $entity->setMediumSubset($medium_subset);
        $form = $this->form_factory->create(
            new UploadImageType(),
            $entity,
            ['action' => $action, 'attr' => ['id' => 'uploadentry']]
        )
        ;

        return $form;
    }

    /**
     * Get the form as it was submitted
     *
     * @return \Symfony\Component\Form\FormInterface
     */
    public function getSubmittedForm()
    {
        /** @var \Symfony\Component\Form\FormErrorIterator $error_obj */
        $error_obj = $this->settings->get('formerror');
        $form = $error_obj->getForm();

        return $form;
    }
}
