<?php
/**
 * Created by PhpStorm.
 * User: pdoes
 * Date: 2/20/15
 * Time: 1:43 PM
 */

namespace RpsCompetition\Forms\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class UploadEntryType
 *
 * @package RpsCompetition\Forms\Type
 */
class UploadEntryType extends AbstractType
{
    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'title',
            'text',
            [
                'label'       => 'Title (required)',
                'constraints' => new Assert\Length(
                    [
                        'min'        => 2,
                        'max'        => 128,
                        'minMessage' => 'The title must be at least {{ limit }} characters long',
                        'maxMessage' => 'The title can not be longer than {{ limit }} characters',
                    ]
                ),
                'attr'        => [
                    'maxlength' => '128'
                ]
            ]
        )
                ->add(
                    'file_name',
                    'file',
                    [
                        'label'       => 'File Name (required)',
                        'required'    => false,
                        'constraints' => [
                            new Assert\Image(['message' => 'The selected file is not an image file.']),
                            new Assert\NotBlank(['message' => 'An image file needs to be selected for upload.'])
                        ],
                        'attr'        => [
                            'accept' => 'image/*'
                        ]
                    ]
                )
                ->add(
                    'submit',
                    'submit',
                    ['label' => 'Submit']
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
                    'medium_subset',
                    'hidden'
                )
                ->add(
                    'wp_get_referer',
                    'hidden'
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
                'data_class' => 'RpsCompetition\Entity\Forms\UploadEntry',
            ]
        )
        ;
    }
}
