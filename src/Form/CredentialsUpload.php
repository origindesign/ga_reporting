<?php

/**
 * @file
 * Contains \Drupal\ga_reporting\Form\CredentialsUpload
 */

namespace Drupal\ga_reporting\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;


/**
 * authorize.net form.
 */
class CredentialsUpload extends ConfigFormBase {


    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames() {
        return [
            'ga_reporting.credentials',
        ];
    }


    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ga_reporting_credentials_form';
    }


    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        $config = $this->config('ga_reporting.credentials');

        $form['credentials_json'] = array(
            '#type' => 'managed_file',
            '#title' => t('Credentials JSON file'),
            '#upload_validators'  => [
                'file_validate_extensions' => array('json'),
            ],
            '#required' => TRUE,
            '#upload_location' => 'private://credentials/',
            '#default_value' => ($config->get('credentials_file') ? [$config->get('credentials_file')] : '' )
        );

        return parent::buildForm($form, $form_state);
    }


    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        // Get form values
        $form_values = $form_state->getValues();

        // Check required fields
        if ($form_values['credentials_json'] == '') {
            $form_state->setErrorByName('credentials_json', $this->t('You must upload a credentials JSON file'));
        }

    }


    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        // Get form values
        $form_values = $form_state->getValues();

        // Set config from from values
        $this->config('ga_reporting.credentials')
            ->set('credentials_file', $form_values['credentials_json'][0])
            ->save();

        parent::submitForm($form, $form_state);

    }

}