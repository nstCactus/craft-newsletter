<?php

namespace juban\newsletter\models;

use Craft;
use craft\base\Model;

class LocalizedSettingsCollection extends Model
{
    /** @var array<string, Settings|array> */
    public array $localizedSettings = [];

    /**
     * Override setAttributes to capture dynamic site settings
     */
    public function setAttributes($values, $safeOnly = true): void
    {
        if (array_key_exists('localizedSettings', $values)) {
            // Current settings schema

            foreach ($values['localizedSettings'] as $siteHandle => $settingsArray) {
                if (!Craft::$app->getSites()->getSiteByHandle($siteHandle)) {
                    throw new \InvalidArgumentException("Site \"$siteHandle\" does not exist.");
                }

                if (array_key_exists('adapterTypeSettings', $settingsArray)) {
                    // Populating the settings object from project config
                    $this->localizedSettings[$siteHandle] = new Settings($settingsArray);
                } else {
                    // Normalizing a settings form before saving it

                    $siteSettings = new Settings();
                    $declaredSettingsNames = get_object_vars($siteSettings);

                    $settingsArray = [
                        'adapterTypeSettings' => $settingsArray[$settingsArray['adapterType']],
                        ...array_intersect_key($settingsArray, $declaredSettingsNames),
                    ];

                    $siteSettings->setAttributes($settingsArray, false);
                    $this->localizedSettings[$siteHandle] = $siteSettings;
                }
            }

            return;
        }


        if (array_intersect(array_keys($values), ['adapterType', 'adapterSettings', 'recaptchaEnabled'])) {
            // Legacy settings schema (non-localized)
            $this->setAttributesFromNonLocalizedSettings($values);
        }
    }

    private function setAttributesFromNonLocalizedSettings(array $values): void
    {
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $this->localizedSettings[$site->handle] = new Settings($values);
        }
    }

    public function getSiteSettings(string $siteHandle): Settings
    {
        return $this->localizedSettings[$siteHandle] ?? new Settings();
    }

    protected function defineRules(): array
    {
        return [
            ['localizedSettings', 'validateEach'],
        ];
    }

    public function validateEach($attribute): void
    {
        foreach ($this->$attribute as $siteHandle => $settingsModel) {
            if (!$settingsModel->validate()) {
                $this->addError($attribute, "Invalid settings for site '$siteHandle'.");
            }
        }
    }
}
