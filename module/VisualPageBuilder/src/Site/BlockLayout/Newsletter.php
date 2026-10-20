<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * A newsletter sign-up form that sends straight to your mailing service
 * (Mailchimp, Constant Contact, Buttondown…). Omeka stores nothing.
 *
 * Find the form address in your service's "embedded form" code: it's the
 * form's action="…" (for Mailchimp, …list-manage.com/subscribe/post?u=…).
 */
class Newsletter extends AbstractSchemaBlock
{
    /** On pages, and in the site's header and footer. */
    const PLACEMENT = 'both';

    const TEMPLATE = 'newsletter';

    const DESCRIPTION = 'A sign-up form for your email newsletter. It sends sign-ups straight to your mailing service, such as Mailchimp.'; // @translate

    const GOOD_FOR = 'Growing your mailing list from the website.'; // @translate

    public function getLabel()
    {
        return 'Newsletter sign-up'; // @translate
    }

    public function schema(): array
    {
        return [
            DisplayFields::heading('Stay in touch'),
            ['key' => 'text', 'type' => Schema::TYPE_TEXTAREA, 'group' => 'Form', 'default' => 'Get news about exhibits, events, and new additions to the collection.', 'label' => 'Text above the form'], // @translate
            ['key' => 'action', 'type' => Schema::TYPE_URL, 'group' => 'Mailing service', 'summary' => true, 'label' => 'Form address', 'info' => 'From your service\'s embedded form code: the address in action="…". Must start with https://.', 'placeholder' => 'https://….list-manage.com/subscribe/post?u=…'], // @translate
            ['key' => 'email_field', 'type' => Schema::TYPE_TEXT, 'group' => 'Mailing service', 'default' => 'EMAIL', 'label' => 'Name of the email field', 'info' => 'Mailchimp uses EMAIL. Check your service\'s form code.'], // @translate
            ['key' => 'ask_name', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Mailing service', 'default' => false, 'label' => 'Also ask for a first name'], // @translate
            ['key' => 'name_field', 'type' => Schema::TYPE_TEXT, 'group' => 'Mailing service', 'default' => 'FNAME', 'label' => 'Name of the first-name field', 'show_if' => ['ask_name' => [true]]], // @translate
            ['key' => 'button_text', 'type' => Schema::TYPE_TEXT, 'group' => 'Form', 'default' => 'Sign up', 'label' => 'Button text'], // @translate
            ['key' => 'privacy', 'type' => Schema::TYPE_TEXT, 'group' => 'Form', 'default' => 'We never share your address. Unsubscribe any time.', 'label' => 'Small print under the form'], // @translate
            ['key' => 'new_tab', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Form', 'default' => true, 'label' => 'Open the service\'s confirmation in a new tab'], // @translate
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $field = function ($v, $fallback) {
            return preg_match('/^[A-Za-z0-9_\[\]-]{1,60}$/', $v) ? $v : $fallback;
        };
        return [
            // Only https: a sign-up form must not send addresses unencrypted.
            'action' => preg_match('~^https://~i', $data['action']) ? $data['action'] : '',
            'emailField' => $field($data['email_field'], 'EMAIL'),
            'nameField' => $field($data['name_field'], 'FNAME'),
            'idBase' => 'vpb-news-' . preg_replace('/[^a-z0-9]/i', '', $blockKey),
        ];
    }
}
