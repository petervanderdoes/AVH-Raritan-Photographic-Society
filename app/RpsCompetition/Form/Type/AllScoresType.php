<?php
namespace RpsCompetition\Form\Type;

use RpsCompetition\Entity\Forms\AllScores as AllScoresEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class AllScoresType
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Form\Type
 */
class AllScoresType extends AbstractType
{
    /** @var AllScoresEntity */
    private $entity;

    /**
     * @param AllScoresEntity $entity
     */
    public function __construct(AllScoresEntity $entity)
    {
        $this->entity = $entity;
    }

    /**
     * {@inheritDoc}
     */
    public function buildform(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'seasons',
            'choice',
            [
                'multiple' => false,
                'expanded' => false,
                'choices'  => $this->entity->getSeasonChoices(),
                'attr'     => ['onchange' => 'submit_form("select_season")']
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
                'data_class' => 'RpsCompetition\Entity\Forms\AllScores',
            ]
        )
        ;
    }
}
