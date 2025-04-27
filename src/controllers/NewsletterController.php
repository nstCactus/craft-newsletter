<?php

namespace juban\newsletter\controllers;

use craft\web\Controller;
use juban\newsletter\models\NewsletterForm;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

class NewsletterController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    /**
     * @return Response|null
     * @throws MethodNotAllowedHttpException
     */
    public function actionSubscribe(): ?Response
    {
        $this->requirePostRequest();

        // form validation
        $newsletterForm = new NewsletterForm();
        $newsletterForm->email = $this->request->post('email');
        $newsletterForm->consent = $this->request->post('consent');
        $newsletterForm->additionalFields = $this->request->post('additionalFields');

        // Subscribe failed, send the form back
        if (!$newsletterForm->subscribe()) {
            return $this->asModelFailure(
                $newsletterForm,
                null,
                'newsletterForm',
                data: [
                    'success' => false,
                ]
            );
        }

        // Subscribe was successful
        return $this->asModelSuccess(
            $newsletterForm,
            data: [
                'success' => true,
            ]
        );
    }
}
