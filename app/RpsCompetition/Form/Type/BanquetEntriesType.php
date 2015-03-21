<?php
namespace RpsCompetition\Form\Type;

use RpsCompetition\Entity\Form\BanquetEntries as EntityFormBanquetEntries;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class BanquetEntriesType
 *
 * @package   RpsCompetition\Form\Type
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class BanquetEntriesType extends AbstractType
{
    /** @var EntityFormBanquetEntries */
    private $entity;

    /**
     * @param EntityFormBanquetEntries $entity
     */
    public function __construct(EntityFormBanquetEntries $entity)
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
                'data_class' => 'RpsCompetition\Entity\Form\BanquetEntries',
            ]
        )
        ;
    }
}
