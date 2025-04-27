<?php

namespace juban\newsletter\models;

use Craft;
use craft\base\Model;
use juban\newsletter\Newsletter;
use juban\newsletter\events\SubscribeEvent;

class NewsletterForm extends Model
{
    public ?string $email = null;

    public bool $consent = false;

    /** @var array<string, mixed> */
    public array $additionalFields = [];

    public const EVENT_BEFORE_SUBSCRIBE = 'beforeSubscribe';

    /**
     * @inheritdoc
     * @return array<mixed>
     * @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection Yii rules are too polymorphic to be described easily
     */
    public function rules(): array
    {
        return [
            ['email', 'trim'],
            ['email', 'required', 'message' => Craft::t('newsletter', 'Please provide a valid email address.')],
            ['email', 'email', 'message' => Craft::t('newsletter', 'Please provide a valid email address.')],
            ['consent', 'required', 'requiredValue' => true, 'message' => Craft::t('newsletter', 'Please provide your consent.')],
            ['additionalFields', 'default', 'value' => []],
        ];
    }

    public function subscribe(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $event = new SubscribeEvent();
        $this->trigger(self::EVENT_BEFORE_SUBSCRIBE, $event);

        if ($event->isSpam) {
            Craft::info("Spam submission detected ($this->email). Pretending subscription was successful without actually subscribing.", __METHOD__);
            return true;
        }

        // Use newsletter module to register new user
        $newsletterAdapter = Newsletter::$plugin->adapter;
        if (!$newsletterAdapter->subscribe($this->email, $this->additionalFields)) {
            $this->addError('email', $newsletterAdapter->getSubscriptionError());
            return false;
        }

        return true;
    }
}
