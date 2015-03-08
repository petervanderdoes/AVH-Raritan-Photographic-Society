<?php
namespace RpsCompetition\Form\Type;

use RpsCompetition\Entity\Forms\ScoresCurrentUser as ScoresCurrentUserEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class ScoresCurrentUserType
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Form\Type
 */
class ScoresCurrentUserType extends AbstractType
{
    /** @var ScoresCurrentUserEntity */
    private $entity;

    /**
     * @param ScoresCurrentUserEntity $entity
     */
    public function __construct(ScoresCurrentUserEntity $entity)
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
                'data_class' => 'RpsCompetition\Entity\Forms\ScoresCurrentUser',
            ]
        )
        ;
    }
}
