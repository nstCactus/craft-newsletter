<?php

namespace juban\newsletter\models;

use Craft;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use juban\newsletter\adapters\NewsletterAdapterInterface;
use yii\base\Behavior;

class Settings extends Model
{
    /** @var ?class-string<NewsletterAdapterInterface> */
    public ?string $adapterType = null;

    /** @var array<string, mixed> */
    public array $adapterTypeSettings = [];

    public bool $recaptchaEnabled = true;

    /**
     * @inheritdoc
     * @return array<string, array{class: class-string}|class-string|Behavior>
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['parser'] = [
            'class' => EnvAttributeParserBehavior::class,
            'attributes' => [
                'recaptchaEnabled',
            ],
        ];
        return $behaviors;
    }

    /**
     * @inheritdoc
     * @return array<string, string>
     */
    public function attributeLabels(): array
    {
        return [
            'adapterType' => Craft::t('newsletter', 'Service Type'),
        ];
    }

    /**
     * @inheritdoc
     * @return array<mixed>
     * @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection Yii rules are too polymorphic to be described easily
     */    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['adapterType'], 'required'];
        $rules[] = [['adapterType'], 'validateAdapterType'];
        $rules[] = [['adapterTypeSettings'], 'validateAdapterTypeSettings'];

        return $rules;
    }

    /**
     * Validates that adapterType is a valid class and extends Model.
     * @noinspection PhpUnused Called when validating the adapterType Yii attribute.
     */
    public function validateAdapterType(string $attribute): void
    {
        if (!class_exists($this->$attribute)) {
            $this->addError($attribute, "Class '$attribute' does not exist.");
            return;
        }

        if (!is_subclass_of($this->$attribute, NewsletterAdapterInterface::class)) {
            $this->addError($attribute, "Class '$attribute' must implement " . NewsletterAdapterInterface::class);
        }
    }

    /**
     * Validates adapterTypeSettings against the instantiated adapterType model.
     * @noinspection PhpUnused Called when validating the adapterTypeSettings Yii attribute.
     */
    public function validateAdapterTypeSettings(string $attribute): void
    {
        if (!$this->adapterType || !class_exists($this->adapterType)) {
            return;
        }

        /** @var Model $adapterInstance */
        $adapterInstance = new $this->adapterType();
        $adapterInstance->setAttributes($this->$attribute, false);

        if (!$adapterInstance->validate()) {
            foreach ($adapterInstance->getErrors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError("$attribute.$field", $message);
                }
            }
        }
    }
}
