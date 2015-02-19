<?php
namespace RpsCompetition\Forms;

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
    static function formEditTitle($formFactory, $action, $data)
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

    /**
     * Setup form: My Digital/Print Entries
     *
     * @param \Symfony\Component\Form\FormFactory $formFactory
     * @param string                              $action
     * @param array                               $data
     *
     * @return \Symfony\Component\Form\Form
     */
    static function formMyEntries($formFactory, $action, $data)
    {
        $form = $formFactory->createBuilder('form', null, array('action' => $action, 'attr' => array('id' => 'myentries')))
                            ->add('submit_control', 'hidden')
                            ->add('comp_date', 'hidden', array('data' => $data['competition_date']))
                            ->add('medium', 'hidden', array('data' => $data['medium']))
                            ->add('classification', 'hidden', array('data' => $data['classification']))
                            ->add('_wpnonce', 'hidden', array('data' => $data['wp_nonce']))
                            ->add('select_comp', 'choice', array('choices' => $data['select_competition']['options'], 'attr' => array('onchange' => 'submit_form("select_comp")')))
                            ->add('selected_medium', 'choice', array('choices' => $data['select_medium']['options'], 'attr' => array('onchange' => 'submit_form("select_medium")')))
                            ->getForm()
        ;

        return $form;
    }

    /**
     * Setup form: Upload Entry
     *
     * @param \Symfony\Component\Form\FormFactory $formFactory
     * @param string                              $action
     * @param string                              $medium_subset
     * @param string                              $ref
     *
     * @return \Symfony\Component\Form\Form
     */
    static function formUploadEntry($formFactory, $action, $medium_subset, $ref)
    {
        $form = $formFactory->createBuilder('form', null, array('action' => $action, 'attr' => array('id' => 'uploadentry')))
                            ->add(
                                'title',
                                'text',
                                array(
                                    'label'       => 'Title (required)',
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
                                    )
                                )
                            )
                            ->add(
                                'file_name',
                                'file',
                                array(
                                    'label'       => 'File Name (required)',
                                    'constraints' => new Assert\Image(),
                                    'attr'        => array(
                                        'accept' => 'image/*'
                                    )
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
                                'medium_subset',
                                'hidden',
                                array('data' => $medium_subset)
                            )
                            ->add(
                                'wp_get_referer',
                                'hidden',
                                array(
                                    'data' => remove_query_arg(array('m'), $ref)
                                )
                            )
                            ->getForm()
        ;

        return $form;
    }
}
