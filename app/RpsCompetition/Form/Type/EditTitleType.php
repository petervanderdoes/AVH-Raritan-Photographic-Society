<?php

namespace RpsCompetition\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class EditTitleType
 *
 * @package   RpsCompetition\Form\Type
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class EditTitleType extends AbstractType
{
    private $entity;

    /**
     * @param \RpsCompetition\Entity\Form\EditTitle $entity
     */
    public function __construct($entity)
    {
        $this->entity = $entity;
    }

    /**
     * {@inheritDoc}
     */
    public function buildform(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'new_title',
            'text',
            [
                'label'       => 'Title',
                'constraints' => new Assert\Length(
                    [
                        'min'        => 2,
                        'max'        => 128,
                        'minMessage' => 'The title must be at least {{ limit }} characters long',
                        'maxMessage' => 'The title can not be longer than {{ limit }} characters',
                    ]
                ),
                'attr'        => [
                    'maxlength' => '128'
                ]
            ]
        )
                ->add(
                    'submit',
                    'submit',
                    ['label' => 'Submit']
                )
                ->add(
                    'cancel',
                    'submit',
                    [
                        'label' => 'Cancel',
                        'attr'  => [
                            'formnovalidate' => 'formnovalidate'
                        ]
                    ]
                )
                ->add(
                    'id',
                    'hidden'
                )
                ->add(
                    'title',
                    'hidden'
                )
                ->add(
                    'server_file_name',
                    'hidden'
                )
                ->add(
                    'm',
                    'hidden'
                )
                ->add(
                    'wp_get_referer',
                    'hidden'
                )
        ;
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => 'RpsCompetition\Entity\Form\EditTitle',
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'form';
    }
}
