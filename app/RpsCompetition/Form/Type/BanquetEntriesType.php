<?php
namespace RpsCompetition\Form\Type;

use RpsCompetition\Entity\Forms\BanquetEntries as BanquetEntriesEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class BanquetEntriesType
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Form\Type
 */
class BanquetEntriesType extends AbstractType
{
    /** @var BanquetEntriesEntity */
    private $entity;

    /**
     * @param BanquetEntriesEntity $entity
     */
    public function __construct(BanquetEntriesEntity $entity)
    {
        $this->entity = $entity;
    }

    /**
     * {@inheritDoc}
     */
    public function buildform(FormBuilderInterface $builder, array $options)
    {
        $builder->add('allentries', 'hidden')
                ->add('banquetids', 'hidden')
                ->add('wp_get_referer', 'hidden')
                ->add(
                    'seasons',
                    'choice',
                    [
                        'multiple' => false,
                        'expanded' => false,
                        'choices'  => $this->entity->getSeasonsChoices(),
                        'attr'     => ['onchange' => 'submit_form("select_season")']
                    ]
                )
                ->add(
                    'update',
                    'submit',
                    ['label' => 'Update']
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
                    'reset',
                    'reset',
                    [
                        'label' => 'Reset',
                        'attr'  => [
                            'formnovalidate' => 'formnovalidate'
                        ]
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
                'data_class' => 'RpsCompetition\Entity\Forms\BanquetEntries',
            ]
        )
        ;
    }
}
