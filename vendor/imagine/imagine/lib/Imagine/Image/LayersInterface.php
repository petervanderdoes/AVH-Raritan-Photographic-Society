<?php

/*
 * This file is part of the Imagine package.
 *
 * (c) Bulat Shakirzyanov <mallluhuct@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Imagine\Image;

use Imagine\Exception\InvalidArgumentException;
use Imagine\Exception\OutOfBoundsException;
use Imagine\Exception\RuntimeException;

/**
 * The layers interface
 */
interface LayersInterface extends \Iterator, \Countable, \ArrayAccess
{
    /**
     * Adds an image at the end of the layers stack
     *
     * @param ImageInterface $image
     *
     * @return LayersInterface
     *
     * @throws RuntimeException
     */
    public function add(ImageInterface $image);

    /**
     * Animates layers
     *
     * @param string  $format The output output format
     * @param integer $delay  The delay in milliseconds between two frames
     * @param integer $loops  The number of loops, 0 means infinite
     *
     * @return LayersInterface
     *
     * @throws InvalidArgumentException In case an invalid argument is provided
     * @throws RuntimeException         In case the driver fails to animate
     */
    public function animate($format, $delay, $loops);

    /**
     * Coalesce layers. Each layer in the sequence is the same size as the first and composited with the next layer in
     * the sequence.
     */
    public function coalesce();

    /**
     * Returns the image at offset
     *
     * @param integer $offset
     *
     * @return ImageInterface
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function get($offset);

    /**
     * Returns true if a layer at offset is preset
     *
     * @param integer $offset
     *
     * @return Boolean
     */
    public function has($offset);

    /**
     * Merge layers into the original objects
     *
     * @throws RuntimeException
     */
    public function merge();

    /**
     * Removes the image at offset
     *
     * @param integer $offset
     *
     * @return LayersInterface
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function remove($offset);

    /**
     * Set an image at offset
     *
     * @param integer        $offset
     * @param ImageInterface $image
     *
     * @return LayersInterface
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    public function set($offset, ImageInterface $image);
}
