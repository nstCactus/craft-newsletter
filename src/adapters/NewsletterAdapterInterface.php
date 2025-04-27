<?php

namespace juban\newsletter\adapters;

use craft\base\ConfigurableComponentInterface;

interface NewsletterAdapterInterface extends ConfigurableComponentInterface
{
    /**
     * Try to subscribe the given email into the newsletter mailing list service
     * @param array<string, mixed>|null $additionalFields
     */
    public function subscribe(string $email, array $additionalFields = null): bool;

    /**
     * Return the latest error message after a call to the subscribe method
     */
    public function getSubscriptionError(): ?string;
}
