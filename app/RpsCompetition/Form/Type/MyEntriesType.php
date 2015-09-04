<?php

namespace RpsCompetition\Form\Type;

use RpsCompetition\Entity\Form\MyEntries as EntityFormMyEntries;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class MyEntriesType
 *
 * @package   RpsCompetition\Form\Type
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class MyEntriesType extends AbstractType
{
    /** @var EntityFormMyEntries */
    private $entity;

    /**
     * Constructor
     *
     * @param EntityFormMyEntries $entity
     */
    public function __construct(EntityFormMyEntries $entity)
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
                'data_class' => 'RpsCompetition\Entity\Form\MyEntries',
            ]
        );
    }
}
