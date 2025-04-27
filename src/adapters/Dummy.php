<?php

namespace juban\newsletter\adapters;

use Craft;
use Throwable;

/**
 * Dummy adapter
 * This class is intended as a base for concret adapters
 *
 * @property-read ?string $settingsHtml
 * @property-read string $subscriptionError
 */
class Dummy extends BaseNewsletterAdapter
{
    /** @noinspection PhpUnused */
    public mixed $someAttribute = null;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'Dummy';
    }

    /**
     * @inheritdoc
     * @return array<string, string>
     */
    public function attributeLabels(): array
    {
        return [
            'someAttribute' => Craft::t('newsletter', 'Some attribute'),
        ];
    }

    /**
     * @inheritdoc
     * @throws Throwable If rendering the template fails
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('newsletter/newsletterAdapters/Dummy/settings', [
            'adapter' => $this,
        ]);
    }

    public function subscribe(string $email, array $additionalFields = null): bool
    {
        return true;
    }

    public function getSubscriptionError(): string
    {
        return "Some error";
    }

    /**
     * @inheritdoc
     * @return array<mixed>
     * @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection Yii rules are too polymorphic to be described easily
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['someAttribute'], 'trim'];
        $rules[] = [['someAttribute'], 'required'];
        return $rules;
    }
}
