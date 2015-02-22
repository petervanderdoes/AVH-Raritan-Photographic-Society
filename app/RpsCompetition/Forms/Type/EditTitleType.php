<?php

namespace RpsCompetition\Forms\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints as Assert;

class EditTitleType extends AbstractType
{
    public function __construct($entity)
    {
        $this->entity = $entity;
    }

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
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'form';
    }

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
