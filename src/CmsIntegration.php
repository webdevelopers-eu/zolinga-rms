<?php

declare(strict_types=1);

namespace Zolinga\Rms;
use Zolinga\System\Events\{ListenerInterface,ContentEvent};

/**
 * Cms integration class.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-04-19
 */
class CmsIntegration implements ListenerInterface {

    public function onContent(ContentEvent $event): void
    {
        global $api;

        // Do something with the content event.
        $htmlClass = $event->content->documentElement->getAttribute('class');
        $htmlClass .= ' ' . ($api->user->id ? 'rms-logged-in' : 'rms-logged-out');
        $event->content->documentElement->setAttribute('class', trim($htmlClass));

        $res = $event->xpath->query('/html/head');
        if ($res && $res->length > 0) {
            $headNode = $res->item(0);

            // Watch for login/logout cookies
            $script = $event->content->createElement('script');
            $headNode->appendChild($script);
            $script->setAttribute('src', '/dist/zolinga-rms/rms.js');
            $script->setAttribute('type', 'module');

            // Add styles
            $style = $event->content->createElement('style');
            $headNode->appendChild($style);
            $style->setAttribute('type', 'text/css');
            $style->appendChild($event->content->createTextNode(<<<CSS
                html.rms-logged-out .for-users,
                html.rms-logged-in .for-guests {
                    display: none !important;
                    pointer-events: none !important;
                    position: absolute !important;
                    opacity: 0 !important;
                }
                CSS));
        }
    }
}