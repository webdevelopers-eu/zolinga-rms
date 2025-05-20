<?php

declare(strict_types=1);

namespace Zolinga\Rms;

use Zolinga\System\Events\{ListenerInterface, ContentEvent};

/**
 * Cms integration class.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-04-19
 */
class CmsIntegration implements ListenerInterface
{

    public function onContent(ContentEvent $event): void
    {
        global $api;

        $headNode = $event->xpath->evaluate('/html/head')?->item(0);
        if (!$headNode) { // no html content?
            return;
        }

        // Do something with the content event.
        $htmlClass = $event->content->documentElement->getAttribute('class');
        $htmlClass .= ' ' . ($api->user->id ? 'rms-logged-in' : 'rms-logged-out');
        $event->content->documentElement->setAttribute('class', trim($htmlClass));

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
                html.rms-logged-in .for-guests,
                html:not([data-user-tags~="debugger"]) .for-debuggers,
                html:not([data-user-tags~="administrator"]) .for-administrators {
                    display: none !important;
                    pointer-events: none !important;
                    position: absolute !important;
                    opacity: 0 !important;
                }

                /** This is a workaround for FF & Safari not supporting :host-context() */
                html.rms-logged-out {
                    --for-users-scale: 0;
                    --for-users-position: absolute;
                }
                html.rms-logged-in {
                    --for-guests-scale: 0;
                    --for-guests-position: absolute;
                }
                :host *.for-users[class] { /* just to make it more specific */
                    scale: var(--for-users-scale) !important;
                    position: var(--for-users-position) !important;
                }
                :host *.for-guests[class] { /* just to make it more specific */
                    scale: var(--for-guests-scale) !important;
                    position: var(--for-guests-position) !important;
                }
            CSS));

        // // @future When FF & Safari supports :host-context()
        // :host-context(html.rms-logged-out) .for-users,
        // :host-context(html.rms-logged-in) .for-guests {
        //     display: none !important;
        //     pointer-events: none !important;
        //     position: absolute !important;
        //     opacity: 0 !important;
        // }
    }
}
