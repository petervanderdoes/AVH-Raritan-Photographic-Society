<?php
namespace RpsCompetition\Forms;

use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms as SymfonyForms;
use Symfony\Component\Validator\Constraints\MinLength;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

class Forms
{
    /**
     * Create Entry Upload Form
     *
     * @param string $action
     * @param string $medium_subset
     * @param string $ref
     *
     * @return \Symfony\Component\Form\Form
     */
    static function formUploadEntry($action, $medium_subset, $ref)
    {
        $validator = Validation::createValidator();
        $formFactory = SymfonyForms::createFormFactoryBuilder()
                                   ->addExtension(new ValidatorExtension($validator))
                                   ->getFormFactory()
        ;
        $form = $formFactory->createBuilder('form', null, array('action' => $action, 'attr' => array('id' => 'uploadentry')))
                            ->add(
                                'title',
                                'text',
                                array(
                                    'label'       => 'Title (required)',
                                    'constraints' => new NotBlank(),
                                    'attr'        => array(
                                        'maxlength' => '128'
                                    )
                                )
                            )
                            ->add(
                                'file_name',
                                'file',
                                array(
                                    'label' => 'File Name (required)',
                                    'attr'  => array(
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
