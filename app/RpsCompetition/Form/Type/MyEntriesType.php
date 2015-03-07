<?php

namespace RpsCompetition\Form\Type;

use RpsCompetition\Entity\Forms\MyEntries as MyEntriesEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class MyEntriesType
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Form\Type
 */
class MyEntriesType extends AbstractType
{
    /** @var MyEntriesEntity  */
    private $entity;

    /**
     * Constructor
     *
     * @param MyEntriesEntity $entity
     */
    public function __construct(MyEntriesEntity $entity)
    {
        $this->entity = $entity;
    }

    /**
     * {@inheritDoc}
     */
    public function buildform(FormBuilderInterface $builder, array $options)
    {
        $builder->add('submit_control', 'hidden')
                ->add('classification', 'hidden')
                ->add('_wpnonce', 'hidden')
                ->add(
                    'select_comp',
                    'choice',
                    [
                        'multiple' => false,
                        'expanded' => false,
                        'choices'  => $this->entity->getSelectedCompChoices(),
                        'attr'     => ['onchange' => 'submit_form("select_comp")']
                    ]
                )
                ->add(
                    'selected_medium',
                    'choice',
                    [
                        'multiple' => false,
                        'expanded' => false,
                        'choices'  => $this->entity->getSelectedMediumChoices(),
                        'attr'     => ['onchange' => 'submit_form("select_medium")']
                    ]
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
            [
                'data_class' => 'RpsCompetition\Entity\Forms\MyEntries',
            ]
        )
        ;
    }
}
