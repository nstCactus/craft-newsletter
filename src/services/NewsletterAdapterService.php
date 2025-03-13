<?php

namespace juban\newsletter\services;

use craft\base\Component;
use craft\helpers\Component as ComponentHelper;
use juban\newsletter\adapters\NewsletterAdapterInterface;
use juban\newsletter\Newsletter;

class NewsletterAdapterService extends Component
{
    public function createAdapter(string $type, array $settings = null): NewsletterAdapterInterface
    {
        return ComponentHelper::createComponent([
            'type' => $type,
            'settings' => $settings,
        ], NewsletterAdapterInterface::class);
    }

    public function getNewsletterAdapterForSite(string $siteHandle): NewsletterAdapterInterface
    {
        $settings = Newsletter::$plugin->getSettings()->getSiteSettings($siteHandle);
        $adapterTypes = Newsletter::getAdaptersTypes();

        // Backward compatibility with legacy adapters
        if (str_starts_with($settings->adapterType, "simplonprod")) {
            $settings->adapterType = str_replace('simplonprod', 'juban', $settings->adapterType);
        }

        if ($settings->adapterType === null) {
            $settings->adapterType = $adapterTypes[0];
            $settings->adapterTypeSettings = [];
        }

        return $this->createAdapter($settings->adapterType, $settings->adapterTypeSettings);
    }
}
