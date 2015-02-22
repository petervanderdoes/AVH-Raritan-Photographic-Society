<?php

namespace RpsCompetition\Forms\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class EditTitleType
 *
 * @package RpsCompetition\Forms\Type
 */
class EditTitleType extends AbstractType
{
    /**
     * @param $entity
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
    public function getName()
    {
        return 'form';
    }

    /**
     * {@inheritDoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => 'RpsCompetition\Entity\Forms\EditTitle',
            )
        )
        ;
    }
}
