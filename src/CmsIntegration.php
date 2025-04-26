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
                    --for-users-display: none;
                    --for-users-size: 0px;
                    --for-users-overflow: hidden;
                    --for-users-position: absolute;
                    --for-users-events: none;
                    --for-guests-display: invalid-value; /* gets ignored */
                    --for-guests-size: invalid-value;
                    --for-guests-overflow: invalid-value;
                    --for-guests-position: invalid-value;
                    --for-guests-events: invalid-value; 
                }
                html.rms-logged-in {
                    --for-users-display: invalid-value; /* gets ignored */
                    --for-users-size: invalid-value;
                    --for-users-overflow: invalid-value;
                    --for-users-position: invalid-value;
                    --for-users-events: invalid-value;
                    --for-guests-display: none;
                    --for-guests-size: 0px;
                    --for-guests-overflow: hidden;
                    --for-guests-position: absolute;
                    --for-guests-events: none;
                }
                :host *.for-users[class] { /* just to make it more specific */
                    display: var(--for-users-display) !important;
                    max-width: var(--for-users-size) !important;
                    max-height: var(--for-users-size) !important;
                    overflow: var(--for-users-overflow) !important;
                    pointer-events: var(--for-users-events) !important;
                    position: var(--for-users-position) !important;
                }
                :host *.for-guests[class] { /* just to make it more specific */
                    display: var(--for-guests-display) !important;
                    max-width: var(--for-guests-size) !important;
                    max-height: var(--for-guests-size) !important;
                    overflow: var(--for-guests-overflow) !important;
                    pointer-events: var(--for-guests-events) !important;
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
