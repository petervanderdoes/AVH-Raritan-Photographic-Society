<?php

namespace RpsCompetition\Frontend\Shortcodes\EditTitle;

use Illuminate\Config\Repository as Settings;
use Illuminate\Http\Request as IlluminateRequest;
use RpsCompetition\Db\QueryEntries;
use RpsCompetition\Entity\Form\EditTitle as EntityFormEditTitle;
use RpsCompetition\Form\Type\EditTitleType;
use RpsCompetition\Helpers\PhotoHelper;
use Symfony\Component\Form\FormFactory;

/**
 * Class EditTitleModel
 *
 * @package   RpsCompetition\Frontend\Shortcodes\EditTitle
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class EditTitleModel
{
    private $form_factory;
    private $photo_helper;
    private $query_entries;
    private $request;
    private $settings;

    /**
     * Constructor
     *
     * @param QueryEntries      $query_entries
     * @param PhotoHelper       $photo_helper
     * @param FormFactory       $form_factory
     * @param Settings          $settings
     * @param IlluminateRequest $request
     */
    public function __construct(
        QueryEntries $query_entries,
        PhotoHelper $photo_helper,
        FormFactory $form_factory,
        Settings $settings,
        IlluminateRequest $request
    ) {
        $this->query_entries = $query_entries;
        $this->photo_helper  = $photo_helper;
        $this->form_factory  = $form_factory;
        $this->settings      = $settings;
        $this->request       = $request;
    }

    /**
     * Get the data
     *
     * @param $server_filename
     *
     * @return array
     */
    public function getData($server_filename)
    {
        $data                    = [];
        $data['image']['source'] = $this->photo_helper->getThumbnailUrl($server_filename, '200');

        return $data;
    }

    /**
     * Get the entry
     *
     * @param int $entry_id
     *
     * @return QueryEntries
     */
    public function getEntry($entry_id)
    {
        return $this->query_entries->getEntryById($entry_id);
    }

    /**
     * Get the entry ID based on the reqeust
     *
     * @return int
     */
    public function getEntryId()
    {
        return (int) $this->request->input('id');
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
     * Create a new form
     *
     * @param int    $entry_id
     * @param string $title
     * @param string $server_file_name
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */

    public function getNewForm($entry_id, $title, $server_file_name)
    {
        global $post;
        $entity        = new EntityFormEditTitle();
        $medium_subset = $this->getMediumSubset();

        $action = add_query_arg(['id' => $entry_id, 'm' => strtolower($medium_subset)], get_permalink($post->ID));
        $entity->setId($entry_id);

        $entity->setNewTitle($title);
        $entity->setTitle($title);
        $entity->setServerFileName($server_file_name);
        $entity->setM($medium_subset);
        $entity->setWpGetReferer(remove_query_arg(['m', 'id'], wp_get_referer()));
        $form = $this->form_factory->create(new EditTitleType(),
                                            $entity,
                                            ['action' => $action, 'attr' => ['id' => 'edittitle']]);

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
        $form      = $error_obj->getForm();

        return $form;
    }
}
