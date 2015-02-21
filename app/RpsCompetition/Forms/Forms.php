<?php
namespace RpsCompetition\Forms;

use RpsCompetition\Entity\UploadEntry;
use Symfony\Component\Form\Form;
use Symfony\Component\Validator\Constraints as Assert;

class Forms
{
    /**
     * The default dataset for the page MyEntries
     *
     * @return array
     */
    static function defaultDataMyEntries()
    {
        $data = [];
        $data['competition_date'] = '';
        $data['medium'] = '';
        $data['classification'] = '';
        $data['wp_nonce'] = '';
        $data['select_competition']['options'] = [];
        $data['select_medium']['options'] = [];

        return $data;
    }

    /**
     * Setup form: Edit Title
     *
     * @param \Symfony\Component\Form\FormFactory $formFactory
     * @param string                              $action
     * @param array                               $data
     *
     * @return \Symfony\Component\Form\Form
     */
    static function formEditTitle($formFactory, $action = '', $data=[])
    {
        $form = $formFactory->createBuilder('form', null, array('action' => $action, 'attr' => array('id' => 'edittitle')))
                            ->add(
                                'new_title',
                                'text',
                                array(
                                    'label'       => 'Title',
                                    'constraints' => new Assert\Length(
                                        array(
                                            'min'        => 2,
                                            'max'        => 128,
                                            'minMessage' => 'The title must be at least {{ limit }} characters long',
                                            'maxMessage' => 'The title can not be longer than {{ limit }} characters',
                                        )
                                    ),
                                    'attr'        => array(
                                        'maxlength' => '128'
                                    ),
                                    'data'        => $data['title']
                                )
                            )
                            ->add(
                                'submit',
                                'submit',
                                array('label' => 'Submit')
                            )
                            ->add(
                                'cancel',
                                'submit',
                                array(
                                    'label' => 'Cancel',
                                    'attr'  => array(
                                        'formnovalidate' => 'formnovalidate'
                                    )
                                )
                            )
                            ->add(
                                'id',
                                'hidden',
                                array('data' => $data['entry_id'])
                            )
                            ->add(
                                'title',
                                'hidden',
                                array('data' => $data['title'])
                            )
                            ->add(
                                'server_file_name',
                                'hidden',
                                array('data' => $data['server_file_name'])
                            )
                            ->add(
                                'm',
                                'hidden',
                                array('data' => $data['medium_subset'])
                            )
                            ->add(
                                'wp_get_referer',
                                'hidden',
                                array('data' => $data['ref'])
                            )
                            ->getForm()
        ;

        return $form;
    }
}
