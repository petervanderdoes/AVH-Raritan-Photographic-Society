<?php

/*
 * This file is part of the Imagine package.
 *
 * (c) Bulat Shakirzyanov <mallluhuct@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Imagine\Filter\Basic;

use Imagine\Filter\FilterInterface;
use Imagine\Image\BoxInterface;
use Imagine\Image\ImageInterface;

/**
 * A thumbnail filter
 */
class Thumbnail implements FilterInterface
{
    /**
     * @var string
     */
    private $filter;
    /**
     * @var string
     */
    private $mode;
    /**
     * @var BoxInterface
     */
    private $size;

    /**
     * Constructs the Thumbnail filter with given width, height and mode
     *
     * @param BoxInterface $size
     * @param string       $mode
     * @param string       $filter
     */
    public function __construct(
        BoxInterface $size,
        $mode = ImageInterface::THUMBNAIL_INSET,
        $filter = ImageInterface::FILTER_UNDEFINED
    ) {
        $this->size = $size;
        $this->mode = $mode;
        $this->filter = $filter;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(ImageInterface $image)
    {
        return $image->thumbnail($this->size, $this->mode, $this->filter);
    }
}
