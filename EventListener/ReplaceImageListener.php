<?php

/*
 * This file is part of the GifExceptionBundle project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Joli\GifExceptionBundle\EventListener;

use Symfony\Component\Asset\Packages;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ReplaceImageListener implements EventSubscriberInterface
{
    private const IMAGES_DIR = '../../Resources/public/images';

    /** @var string[][] */
    private $gifs;

    /** @var string */
    private $exceptionController;

    /** @var Packages */
    private $packages;

    /**
     * @param string[][] $gifs
     */
    public function __construct(array $gifs, string $exceptionController, Packages $packages = null)
    {
        $this->gifs = $gifs;
        $this->exceptionController = $exceptionController;
        $this->packages = $packages;
    }

    /**
     * Handle the response for exception and replace the little Phantom by a random Gif.
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        if ($event->isMasterRequest()
            || $event->getRequest()->attributes->get('_controller') !== $this->exceptionController) {
            return;
        }

        // Status code is not set by the exception controller but only by the
        // kernel at the very end.
        // So lets use the status code from the flatten exception instead.
        $statusCode = $event->getRequest()->attributes->get('exception')->getStatusCode();

        $dir = $this->getGifDir($statusCode);
        $gif = $this->getRandomGif($dir);
        $url = $this->getGifUrl($dir, $gif);

        $content = $event->getResponse()->getContent();

        $content = preg_replace(
            '@<div class="exception-illustration hidden-xs-down">(.*?)</div>@ims',
            sprintf('<div class="exception-illustration hidden-xs-down" style="opacity:1"><img alt="Exception detected!" src="%s" data-gif style="height:66px" /></div>', $url),
            $content
        );

        $event->getResponse()->setContent($content);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -1000],
        ];
    }

    /**
     * Return the gif folder for the given status code.
     */
    private function getGifDir(int $statusCode): string
    {
        if (\array_key_exists($statusCode, $this->gifs) && \count($this->gifs[$statusCode]) > 0) {
            return $statusCode;
        }

        return 'other';
    }

    /**
     * Return a random gif name for the given directory.
     */
    private function getRandomGif(string $dir): string
    {
        $imageIndex = random_int(0, \count($this->gifs[$dir]) - 1);

        return $this->gifs[$dir][$imageIndex];
    }

    /**
     * Return a the url of given gif in the given directory.
     */
    private function getGifUrl(string $dir, string $gif): string
    {
        return $this->generateUrl(sprintf('bundles/gifexception/images/%s/%s', $dir, $gif));
    }

    /**
     * Generate an url in both Symfony 2 and Symfony 3+ compatible ways.
     */
    private function generateUrl(string $url): string
    {
        if (null !== $this->packages) {
            return $this->packages->getUrl($url);
        }

        return $url;
    }
}
