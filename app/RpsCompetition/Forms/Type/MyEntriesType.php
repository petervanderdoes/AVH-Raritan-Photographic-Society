<?php

namespace RpsCompetition\Forms\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class MyEntriesType
 *
 * @package RpsCompetition\Forms\Type
 */
class MyEntriesType extends AbstractType
{
    /**
     * @param $entity
     *
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
        $builder->add('submit_control', 'hidden')
                ->add('comp_date', 'hidden')
                ->add('medium', 'hidden')
                ->add('classification', 'hidden')
                ->add('_wpnonce', 'hidden')
        ;

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) {
                $form = $event->getForm();

                $form->add(
                    'select_comp',
                    'choice',
                    array(
                        'multiple' => false,
                        'expanded' => false,
                        'choices'  => $this->entity->getSelectComp(),
                        'attr'     => array('onchange' => 'submit_form("select_comp")')
                    )
                )
                ;
                $form->add(
                    'selected_medium',
                    'choice',
                    array(
                        'multiple' => false,
                        'expanded' => false,
                        'choices'  => $this->entity->getSelectedMedium(),
                        'attr'     => array('onchange' => 'submit_form("select_medium")')
                    )
                )
                ;
            }
        )
        ;
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) {
                $form = $event->getForm();

                $form->add('select_comp');
                $form->add('selected_medium');
            }
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
                'data_class' => 'RpsCompetition\Entity\Forms\MyEntries',
            )
        )
        ;
    }
}
