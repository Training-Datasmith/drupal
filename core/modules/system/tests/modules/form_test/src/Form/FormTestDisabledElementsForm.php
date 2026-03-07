<?php

declare(strict_types=1);

namespace Drupal\form_test\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Builds a form to test disabled elements.
 *
 * @internal
 */
class FormTestDisabledElementsForm extends FormBase
{
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return '_form_test_disabled_elements';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        // Elements that take a simple default value.
        foreach (['textfield', 'textarea', 'search', 'tel', 'hidden'] as $type) {
            $form[$type] = [
              '#type' => $type,
              '#title' => $type,
              '#default_value' => $type,
              '#test_hijack_value' => 'HIJACK',
              '#disabled' => true,
            ];
        }

        // Multiple values option elements, disabled as a whole.
        foreach (['checkboxes', 'select'] as $type) {
            $form[$type . '_multiple'] = [
              '#type' => $type,
              '#title' => $type . ' (multiple)',
              '#options' => [
                'test_1' => 'Test 1',
                'test_2' => 'Test 2',
              ],
              '#multiple' => true,
              '#default_value' => ['test_2' => 'test_2'],
              // The keys of #test_hijack_value need to match the #name of the
              // control.
              // @see FormsTestCase::testDisabledElements()
              '#test_hijack_value' => $type == 'select' ? ['' => 'test_1'] : ['test_1' => 'test_1'],
              '#disabled' => true,
            ];
        }

        // Multiple values option elements, only single options disabled.
        $form['checkboxes_single_select'] = [
          '#type' => 'checkboxes',
          '#title' => 'checkboxes (multiple)',
          '#options' => [
            'test_1' => 'Test 1',
            'test_2' => 'Test 2',
          ],
          '#multiple' => true,
          '#default_value' => ['test_2' => 'test_2'],
          'test_1' => [
            '#disabled' => true,
          ],
        ];
        $form['checkboxes_single_unselect'] = [
          '#type' => 'checkboxes',
          '#title' => 'checkboxes (multiple)',
          '#options' => [
            'test_1' => 'Test 1',
            'test_2' => 'Test 2',
          ],
          '#multiple' => true,
          '#default_value' => ['test_2' => 'test_2'],
          'test_2' => [
            '#disabled' => true,
          ],
        ];

        // Single values option elements.
        foreach (['radios', 'select'] as $type) {
            $form[$type . '_single'] = [
              '#type' => $type,
              '#title' => $type . ' (single)',
              '#options' => [
                'test_1' => 'Test 1',
                'test_2' => 'Test 2',
              ],
              '#multiple' => false,
              '#default_value' => 'test_2',
              '#test_hijack_value' => 'test_1',
              '#disabled' => true,
            ];
        }

        // Checkbox and radio.
        foreach (['checkbox', 'radio'] as $type) {
            $form[$type . '_unchecked'] = [
              '#type' => $type,
              '#title' => $type . ' (unchecked)',
              '#return_value' => 1,
              '#default_value' => 0,
              '#test_hijack_value' => 1,
              '#disabled' => true,
            ];
            $form[$type . '_checked'] = [
              '#type' => $type,
              '#title' => $type . ' (checked)',
              '#return_value' => 1,
              '#default_value' => 1,
              '#test_hijack_value' => null,
              '#disabled' => true,
            ];
        }

        // Weight, number, range.
        foreach (['weight', 'number', 'range'] as $type) {
            $form[$type] = [
              '#type' => $type,
              '#title' => $type,
              '#default_value' => 10,
              '#test_hijack_value' => 5,
              '#disabled' => true,
            ];
        }

        // Color.
        $form['color'] = [
          '#type' => 'color',
          '#title' => 'color',
          '#default_value' => '#0000ff',
          '#test_hijack_value' => '#ff0000',
          '#disabled' => true,
        ];

        // The #disabled state should propagate to children.
        $form['disabled_container'] = [
          '#disabled' => true,
        ];
        foreach (['textfield', 'textarea', 'hidden', 'tel', 'url'] as $type) {
            $form['disabled_container']['disabled_container_' . $type] = [
              '#type' => $type,
              '#title' => $type,
              '#default_value' => $type,
              '#test_hijack_value' => 'HIJACK',
            ];
        }

        // Date.
        $date = new DrupalDateTime('1978-11-01 10:30:00', 'Europe/Berlin');
        // Starting with PHP 5.4.30, 5.5.15, JSON encoded DateTime objects include
        // microseconds. Make sure that the expected value is correct for all
        // versions by encoding and decoding it again instead of hardcoding it.
        // See https://github.com/php/php-src/commit/fdb2709dd27c5987c2d2c8aaf0cdbebf9f17f643
        $expected = json_decode(json_encode($date), true);
        $form['disabled_container']['disabled_container_datetime'] = [
          '#type' => 'datetime',
          '#title' => 'datetime',
          '#default_value' => $date,
          '#expected_value' => $expected,
          '#test_hijack_value' => new DrupalDateTime('1978-12-02 11:30:00', 'Europe/Berlin'),
          '#date_timezone' => 'Europe/Berlin',
        ];

        $form['disabled_container']['disabled_container_date'] = [
          '#type' => 'date',
          '#title' => 'date',
          '#default_value' => '2001-01-13',
          '#expected_value' => '2001-01-13',
          '#test_hijack_value' => '2013-01-01',
          '#date_timezone' => 'Europe/Berlin',
        ];

        // Try to hijack the email field with a valid email.
        $form['disabled_container']['disabled_container_email'] = [
          '#type' => 'email',
          '#title' => 'email',
          '#default_value' => 'foo@example.com',
          '#test_hijack_value' => 'bar@example.com',
        ];

        // Try to hijack the URL field with a valid URL.
        $form['disabled_container']['disabled_container_url'] = [
          '#type' => 'url',
          '#title' => 'url',
          '#default_value' => 'http://example.com',
          '#test_hijack_value' => 'http://example.com/foo',
        ];

        // Text format.
        $form['text_format'] = [
          '#type' => 'text_format',
          '#title' => 'Text format',
          '#disabled' => true,
          '#default_value' => 'Text value',
          '#format' => 'plain_text',
          '#expected_value' => [
            'value' => 'Text value',
            'format' => 'plain_text',
          ],
          '#test_hijack_value' => [
            'value' => 'HIJACK',
            'format' => 'filtered_html',
          ],
        ];

        // Password fields.
        $form['password'] = [
          '#type' => 'password',
          '#title' => 'Password',
          '#disabled' => true,
        ];
        $form['password_confirm'] = [
          '#type' => 'password_confirm',
          '#title' => 'Password confirm',
          '#disabled' => true,
        ];

        // Files.
        $form['file'] = [
          '#type' => 'file',
          '#title' => 'File',
          '#disabled' => true,
        ];
        $form['managed_file'] = [
          '#type' => 'managed_file',
          '#title' => 'Managed file',
          '#disabled' => true,
        ];

        // Buttons.
        $form['image_button'] = [
          '#type' => 'image_button',
          '#value' => 'Image button',
          '#src' => '',
          '#disabled' => true,
        ];
        $form['button'] = [
          '#type' => 'button',
          '#value' => 'Button',
          '#disabled' => true,
        ];
        $form['submit_disabled'] = [
          '#type' => 'submit',
          '#value' => 'Submit',
          '#disabled' => true,
        ];

        $form['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Submit'),
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $form_state->setResponse(new JsonResponse($form_state->getValues()));
    }

}
