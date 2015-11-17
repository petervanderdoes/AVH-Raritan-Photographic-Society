<?php
namespace RpsCompetition\Form\Type;

use RpsCompetition\Entity\Form\ScoresCurrentUser as EntityFormScoresCurrentUser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class ScoresCurrentUserType
 *
 * @package   RpsCompetition\Form\Type
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2015, AVH Software
 */
class ScoresCurrentUserType extends AbstractType
{
    /** @var EntityFormScoresCurrentUser */
    private $entity;

    /**
     * @param EntityFormScoresCurrentUser $entity
     */
    public function __construct(EntityFormScoresCurrentUser $entity)
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
        );
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => 'RpsCompetition\Entity\Form\ScoresCurrentUser',
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'form';
    }
}
