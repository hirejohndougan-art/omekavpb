<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * Links to the society's social media and email.
 */
class SocialLinks extends AbstractSchemaBlock
{
    /** On pages, and in the site's header and footer. */
    const PLACEMENT = 'both';

    const TEMPLATE = 'social-links';

    const DESCRIPTION = 'Links to your Facebook, Instagram, YouTube, and other pages, and your email.'; // @translate

    const GOOD_FOR = 'Footers and contact pages.'; // @translate

    const NETWORKS = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'x' => 'X', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'email' => 'Email'];

    public function getLabel()
    {
        return 'Social links'; // @translate
    }

    public function schema(): array
    {
        $fields = [DisplayFields::heading()];
        foreach (self::NETWORKS as $key => $label) {
            $fields[] = ['key' => $key, 'type' => $key === 'email' ? Schema::TYPE_TEXT : Schema::TYPE_URL, 'group' => 'Links', 'summary' => $key === 'facebook', 'label' => $label, 'placeholder' => $key === 'email' ? 'info@example.org' : 'https://…'];
        }
        $fields[] = ['key' => 'align', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'left', 'label' => 'Alignment', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']]; // @translate
        $fields[] = ['key' => 'style', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'buttons', 'label' => 'Style', 'options' => ['buttons' => 'Round buttons', 'text' => 'Text links']]; // @translate
        return $fields;
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $links = [];
        foreach (self::NETWORKS as $key => $label) {
            $value = $data[$key];
            if ($key === 'email') {
                $value = filter_var($value, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $value : '';
            }
            if ($value !== '') {
                $links[] = ['key' => $key, 'label' => $label, 'url' => $value];
            }
        }
        return ['links' => $links];
    }
}
