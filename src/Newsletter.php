<?php

namespace juban\newsletter;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use craft\services\Plugins;
use craft\web\Controller;
use craft\web\twig\variables\CraftVariable;
use juban\googlerecaptcha\GoogleRecaptcha;
use juban\newsletter\adapters\Brevo;
use juban\newsletter\adapters\Mailchimp;
use juban\newsletter\adapters\Mailjet;
use juban\newsletter\adapters\NewsletterAdapterInterface;
use juban\newsletter\models\LocalizedSettingsCollection;
use juban\newsletter\models\NewsletterForm;
use juban\newsletter\models\Settings;
use juban\newsletter\services\NewsletterAdapterService;
use juban\newsletter\variables\Newsletter as NewsletterVariable;
use yii\base\Event;
use yii\web\Response;

/**
 * Craft plugins are very much like little applications in and of themselves. We’ve made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://docs.craftcms.com/v3/extend/
 *
 * @property NewsletterAdapterInterface $adapter
 * @property NewsletterAdapterService $newsletterAdapterService
 *
 * @author    juban
 * @package   Newsletter
 * @since     1.0.0
 *
 * @property  LocalizedSettingsCollection $settings
 * @method    LocalizedSettingsCollection getSettings()
 */
class Newsletter extends Plugin
{
    public const EVENT_REGISTER_NEWSLETTER_ADAPTER_TYPES = 'registerNewsletterAdapterTypes';

    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * Newsletter::$plugin
     *
     * @var Newsletter
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * To execute your plugin’s migrations, you’ll need to increase its schema version.
     *
     * @var string
     */
    public string $schemaVersion = '1.1.0';

    /**
     * Set to `true` if the plugin should have a settings view in the control panel.
     *
     * @var bool
     */
    public bool $hasCpSettings = true;

    /**
     * Set to `true` if the plugin should have its own section (main nav item) in the control panel.
     *
     * @var bool
     */
    public bool $hasCpSection = false;

    /**
     * Initializes the plugin.
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Set a @modules alias pointed to the modules/ directory
        Craft::setAlias('@newsletter', __DIR__);

        $this->_registerAfterInstallEvent();
        $this->_registerRecaptchaVerification();
        $this->_registerVariables();

        $this->set('newsletterAdapterService', NewsletterAdapterService::class);
        // Register adapter component
        // TODO: rename to currentSiteNewsletterAdapter
        $this->set('adapter', function() {
            $currentSite = Craft::$app->getSites()->getCurrentSite();
            return $this->getNewsletterAdapterForSite($currentSite->handle);
        });

        Craft::info(
            Craft::t(
                'newsletter',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    /**
     * Redirect user to plugin setting after installation if from CP
     */
    private function _registerAfterInstallEvent(): void
    {
        // Do something after we're installed
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function(PluginEvent $event) {
                if ($event->plugin !== $this) {
                    return;
                }

                if (!Craft::$app->getRequest()->getIsCpRequest()) {
                    return;
                }

                // Redirect to settings page
                Craft::$app->getResponse()->redirect(
                    UrlHelper::cpUrl('settings/plugins/newsletter')
                )->send();
            }
        );
    }

    /**
     * Verify user submission with Google reCAPTCHA plugin if enabled
     */
    private function _registerRecaptchaVerification(): void
    {
        $settings = $this->getCurrentSiteSettings();

        if (App::parseBooleanEnv($settings->recaptchaEnabled) !== true) {
            return;
        }

        if (!Craft::$app->plugins->isPluginEnabled('google-recaptcha')) {
            return;
        }

        Event::on(
            NewsletterForm::class,
            NewsletterForm::EVENT_AFTER_VALIDATE,
            static function(Event $event) {
                $form = $event->sender;
                if (!GoogleRecaptcha::$plugin->recaptcha->verify()) {
                    $form->addError('recaptcha', Craft::t('newsletter', 'Please prove you are not a robot.'));
                }
            }
        );
    }

    /**
     * Return the list of available newsletter adapters
     * @return string[]
     * FIXME: Move this method to the NewsletterAdapterService
     */
    public static function getAdaptersTypes(): array
    {
        $adaptersTypes = [
            Mailjet::class,
            Brevo::class,
            Mailchimp::class,
        ];

        $event = new RegisterComponentTypesEvent([
            'types' => $adaptersTypes,
        ]);
        Event::trigger(static::class, self::EVENT_REGISTER_NEWSLETTER_ADAPTER_TYPES, $event);

        return $event->types;
    }

    public function beforeSaveSettings(): bool
    {
        $settings = $this->getSettings();

        // Convert SiteSettings objects into arrays before saving
        $convertedSites = [];
        foreach ($settings->localizedSettings as $siteHandle => $siteSettings) {
            if ($siteSettings instanceof Settings) {
                $convertedSites[$siteHandle] = $siteSettings->toArray();
            } else {
                $convertedSites[$siteHandle] = $siteSettings;
            }
        }

        // Update the settings model with the converted site settings
        $settings->localizedSettings = $convertedSites;

        return parent::beforeSaveSettings();
    }

    protected function createSettingsModel(): ?Model
    {
        return new LocalizedSettingsCollection();
    }

    public function getSettingsResponse(): Response
    {
        // check if a configuration file may override Control Panel settings
        $configService = Craft::$app->getConfig();
        $config = $configService->getConfigFromFile('newsletter');
        if (!empty($config)) {
            $configPath = $configService->getConfigFilePath('newsletter');
        }

        $sites = Craft::$app->getSites()->getEditableSites();

        /** @var Controller $controller */
        $controller = Craft::$app->controller;

        // Create every available adapter
        $allAdapters = [];
        $adapterTypeOptions = [];
        foreach (self::getAdaptersTypes() as $adapterType) {
            /** @var string|NewsletterAdapterInterface $adapterType */
            $allAdapters[] = $this->newsletterAdapterService->createAdapter($adapterType);
            $adapterTypeOptions[] = [
                'value' => $adapterType,
                'label' => $adapterType::displayName(),
            ];
        }

        // Sort them by name
        ArrayHelper::multisort($adapterTypeOptions, 'label');

        return $controller->renderTemplate('newsletter/_layouts/settings.twig', [
            // for _layout/settings.twig
            'plugin' => $this,
            'configPath' => $configPath ?? null,
            'sites' => $sites,
            'tabs' => ArrayHelper::map($sites, 'handle', static fn($site) => [
                'url'   => "#$site->handle",
                'label' => $site->name,
            ]),

            // for site-settings.twig
            'settings' => $this->getSettings(),
            'allAdapters' => $allAdapters,
            'adapterTypeOptions' => $adapterTypeOptions,
        ]);
    }

    public function getCurrentSiteSettings(): ?Settings
    {
        $currentSiteHandle = Craft::$app->getSites()->getCurrentSite()->handle;

        return $this->getSettings()->getSiteSettings($currentSiteHandle);

    }

    public function getNewsletterAdapterForSite(string $siteHandle): NewsletterAdapterInterface
    {
        return $this->newsletterAdapterService->getNewsletterAdapterForSite($siteHandle);
    }

    private function _registerVariables(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $e) {
            $e->sender->set('newsletter', NewsletterVariable::class);
        });
    }
}
