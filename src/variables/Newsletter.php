<?php

namespace juban\newsletter\variables;

use juban\newsletter\adapters\NewsletterAdapterInterface;
use juban\newsletter\Newsletter as NewsletterPlugin;

class Newsletter
{
    public function getNewsletterAdapterForSite(string $siteHandle): NewsletterAdapterInterface
    {
        return NewsletterPlugin::$plugin->newsletterAdapterService->getNewsletterAdapterForSite($siteHandle);
    }
}
