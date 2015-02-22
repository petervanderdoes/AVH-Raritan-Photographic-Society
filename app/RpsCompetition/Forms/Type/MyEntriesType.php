<?php

namespace RpsCompetition\Forms\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints as Assert;

class MyEntriesType extends AbstractType
{
    public function __construct($entity)
    {
        $this->entity = $entity;
    }

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
                'data_class' => 'RpsCompetition\Entity\Forms\MyEntries',
            )
        )
        ;
    }
}
