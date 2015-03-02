<?php

namespace RpsCompetition\Entity\Forms;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class UploadImage
 *
 * @author    Peter van der Does
 * @copyright Copyright (c) 2015, AVH Software
 * @package   RpsCompetition\Entity\Forms
 */
class UploadImage
{
    protected $file_name;
    protected $medium_subset;
    protected $title;
    protected $wp_get_referer;

    /**
     * @param ClassMetadata $metadata
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addConstraint(new Assert\Callback('validate'));
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        return $this->file_name;
    }

    /**
     * @param mixed $file_name
     */
    public function setFileName($file_name)
    {
        $this->file_name = $file_name;
    }

    /**
     * @return string
     */
    public function getMediumSubset()
    {
        return $this->medium_subset;
    }

    /**
     * @param string $medium_subset
     */
    public function setMediumSubset($medium_subset)
    {
        $this->medium_subset = $medium_subset;
    }

    /**
     * @return string mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getWpGetReferer()
    {
        return $this->wp_get_referer;
    }

    /**
     * @param string $wp_get_referer
     */
    public function setWpGetReferer($wp_get_referer)
    {
        $this->wp_get_referer = $wp_get_referer;
    }

    /**
     * @param ExecutionContextInterface $context
     */
    public function validate(ExecutionContextInterface $context)
    {
        if ($this->getTitle() == 'p') {
            $context->buildViolation('This name sounds totally fake!')
                    ->atPath('title')
                    ->addViolation()
            ;
        }
    }
}
