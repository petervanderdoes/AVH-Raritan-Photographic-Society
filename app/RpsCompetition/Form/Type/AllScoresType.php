<?php
namespace RpsCompetition\Form\Type;

use RpsCompetition\Entity\Form\AllScores as EntityFormAllScores;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class AllScoresType
 *
 * @package   RpsCompetition\Form\Type
 * @author    Peter van der Does <peter@avirtualhome.com>
 * @copyright Copyright (c) 2014-2016, AVH Software
 */
class AllScoresType extends AbstractType
{
    private $entity;

    /**
     * @param EntityFormAllScores $entity
     */
    public function __construct(EntityFormAllScores $entity)
    {
        $this->entity = $entity;
    }

    /**
     * {@inheritDoc}
     */
    public function buildform(FormBuilderInterface $builder, array $options)
    {
        $builder->add('seasons',
                      'choice',
                      [
                          'multiple' => false,
                          'expanded' => false,
                          'choices'  => $this->entity->getSeasonChoices(),
                          'attr'     => ['onchange' => 'submit_form("select_season")']
                      ]);
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
                                   'data_class' => 'RpsCompetition\Entity\Form\AllScores',
                               ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'form';
    }
}
