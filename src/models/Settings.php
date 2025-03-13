<?php

namespace juban\newsletter\models;

use Craft;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use juban\newsletter\adapters\NewsletterAdapterInterface;

/**
 * NewsletterSettings class
 *
 * @author juban
 **/
class Settings extends Model
{
    public $adapterType;

    public $adapterTypeSettings = [];

    public $recaptchaEnabled = true;

    /**
     * @inheritdoc
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
     */
    public function attributeLabels()
    {
        return [
            'adapterType' => Craft::t('newsletter', 'Service Type'),
        ];
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['adapterType'], 'required'];
        $rules[] = [['adapterType'], 'validateAdapterType'];
        $rules[] = [['adapterTypeSettings'], 'validateAdapterTypeSettings'];

        return $rules;
    }

    /**
     * Validates that adapterType is a valid class and extends Model.
     */
    public function validateAdapterType($attribute, $params): void
    {
        if (!class_exists($this->$attribute)) {
            $this->addError($attribute, "Class '{$this->$attribute}' does not exist.");
            return;
        }

        if (!is_subclass_of($this->$attribute, NewsletterAdapterInterface::class)) {
            $this->addError($attribute, "Class '{$this->$attribute}' must implement " . NewsletterAdapterInterface::class);
        }
    }

    /**
     * Validates adapterTypeSettings against the instantiated adapterType model.
     */
    public function validateAdapterTypeSettings($attribute): void
    {
        if (!$this->adapterType || !class_exists($this->adapterType)) {
            return;
        }

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
