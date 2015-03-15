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

use Imagine\Draw\DrawerInterface;
use Imagine\Effects\EffectsInterface;
use Imagine\Exception\OutOfBoundsException;
use Imagine\Exception\RuntimeException;
use Imagine\Image\Palette\Color\ColorInterface;
use Imagine\Image\Palette\PaletteInterface;

/**
 * The image interface
 */
interface ImageInterface extends ManipulatorInterface
{
    const FILTER_BESSEL = 'bessel';
    const FILTER_BLACKMAN = 'blackman';
    const FILTER_BOX = 'box';
    const FILTER_CATROM = 'catrom';
    const FILTER_CUBIC = 'cubic';
    const FILTER_GAUSSIAN = 'gaussian';
    const FILTER_HAMMING = 'hamming';
    const FILTER_HANNING = 'hanning';
    const FILTER_HERMITE = 'hermite';
    const FILTER_LANCZOS = 'lanczos';
    const FILTER_MITCHELL = 'mitchell';
    const FILTER_POINT = 'point';
    const FILTER_QUADRATIC = 'quadratic';
    const FILTER_SINC = 'sinc';
    const FILTER_TRIANGLE = 'triangle';
    const FILTER_UNDEFINED = 'undefined';
    const INTERLACE_LINE = 'line';
    const INTERLACE_NONE = 'none';
    const INTERLACE_PARTITION = 'partition';
    const INTERLACE_PLANE = 'plane';
    const RESOLUTION_PIXELSPERCENTIMETER = 'ppc';
    const RESOLUTION_PIXELSPERINCH = 'ppi';

    /**
     * Returns the image content as a PNG binary string
     *
     * @throws RuntimeException
     *
     * @return string binary
     */
    public function __toString();

    /**
     * Instantiates and returns a DrawerInterface instance for image drawing
     *
     * @return DrawerInterface
     */
    public function draw();

    /**
     * @return EffectsInterface
     */
    public function effects();

    /**
     * Returns the image content as a binary string
     *
     * @param string $format
     * @param array  $options
     *
     * @throws RuntimeException
     *
     * @return string binary
     */
    public function get($format, array $options = []);

    /**
     * Returns color at specified positions of current image
     *
     * @param PointInterface $point
     *
     * @throws RuntimeException
     *
     * @return ColorInterface|\Imagine\Image\Palette\Color\RGB|\Imagine\Image\Palette\Color\CMYK|\Imagine\Image\Palette\Color\Gray
     */
    public function getColorAt(PointInterface $point);

    /**
     * Returns current image size
     *
     * @return BoxInterface
     */
    public function getSize();

    /**
     * Returns array of image colors as Imagine\Image\Palette\Color\ColorInterface instances
     *
     * @return array
     */
    public function histogram();

    /**
     * Enables or disables interlacing
     *
     * @param string $scheme
     *
     * @throws \InvalidArgumentException When an unsupported Interface type is supplied
     *
     * @return ImageInterface
     */
    public function interlace($scheme);

    /**
     * @param boolean $keep_aspect_ratio
     *
     * @return ImageInterface
     */
    public function keepAspectRatio($keep_aspect_ratio = true);

    /**
     * Returns the image layers when applicable.
     *
     * @throws RuntimeException     In case the layer can not be returned
     * @throws OutOfBoundsException In case the index is not a valid value
     *
     * @return LayersInterface
     */
    public function layers();

    /**
     * Transforms creates a grayscale mask from current image, returns a new
     * image, while keeping the existing image unmodified
     *
     * @return ImageInterface
     */
    public function mask();

    /**
     * Returns the Image's meta data
     *
     * @return \Imagine\Image\ImageInterface
     */
    public function metadata();

    /**
     * Return the current color palette
     *
     * @return PaletteInterface
     */
    public function palette();

    /**
     * Applies a color profile on the Image
     *
     * @param ProfileInterface $profile
     *
     * @return ImageInterface
     *
     * @throws RuntimeException
     */
    public function profile(ProfileInterface $profile);

    /**
     * Set a palette for the image. Useful to change colorspace.
     *
     * @param PaletteInterface $palette
     *
     * @return ImageInterface
     *
     * @throws RuntimeException
     */
    public function usePalette(PaletteInterface $palette);
}
