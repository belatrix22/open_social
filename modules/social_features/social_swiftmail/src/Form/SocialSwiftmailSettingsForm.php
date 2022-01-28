<?php

namespace Drupal\social_swiftmail\Form;

use Drupal\activity_creator\Plugin\ActivityDestinationManager;
use Drupal\Component\Utility\Html as HtmlUtility;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SocialSwiftmailSettingsForm.
 *
 * @package Drupal\social_swiftmail\Form
 */
class SocialSwiftmailSettingsForm extends ConfigFormBase {

  /**
   * The 'email' activity destination plugin.
   *
   * @var \Drupal\activity_send_email\Plugin\ActivityDestination\EmailActivityDestination
   */
  protected $emailActivityDestination;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The batch builder.
   *
   * @var \Drupal\Core\Batch\BatchBuilder
   */
  protected $batchBuilder;

  /**
   * The batch builder.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * SocialSwiftmailSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\activity_creator\Plugin\ActivityDestinationManager $activity_destination_manager
   *   The activity destination manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   The Date formatter.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ActivityDestinationManager $activity_destination_manager,
    ModuleHandlerInterface $module_handler,
    DateFormatterInterface $date_formatter
  ) {
    parent::__construct($config_factory);

    $this->emailActivityDestination = $activity_destination_manager->createInstance('email');
    $this->batchBuilder = new BatchBuilder();
    $this->moduleHandler = $module_handler;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.activity_destination.processor'),
      $container->get('module_handler'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'social_swiftmail.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'social_swiftmail_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('social_swiftmail.settings');

    $form['notification'] = [
      '#type' => 'details',
      '#title' => $this->t('Default email notification settings'),
      '#open' => FALSE,
    ];

    // Settings helper for admins.
    $form['notification']['helper'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('<b>Please note:</b> the change below will not impact the users who have changed their default email notification settings.'),
    ];

    // Get grouped default templates.
    $items = _activity_send_email_default_template_items();

    // Get all message templates.
    $email_message_templates = $this->emailActivityDestination->getSendEmailMessageTemplates();

    // Alter message templates and add them to specific group.
    $this->moduleHandler->alter('activity_send_email_notifications', $items, $email_message_templates);

    // Sort a list of email frequencies by weight.
    $email_frequencies = sort_email_frequency_options();

    $notification_options = [];
    // Place the sorted data in an actual form option.
    foreach ($email_frequencies as $option) {
      $notification_options[$option['id']] = $option['name'];
    }

    $template_frequencies = $config->get('template_frequencies') ?: [];

    foreach ($items as $item_id => $item) {
      $rows = [];
      foreach ($item['templates'] as $template) {
        $rows[] = $this->buildRow($template, $notification_options, $template_frequencies);
      }
      $form['notification'][$item_id] = [
        '#type' => 'table',
        '#caption' => [
          '#markup' => '<h6>' . $item['title'] . '</h6>',
        ],
        '#rows' => $rows,
      ];
    }

    $form['template'] = [
      '#type' => 'details',
      '#title' => $this->t('Template configuration'),
      '#open' => FALSE,
    ];

    $template_header = $config->get('template_header');
    $form['template']['template_header'] = [
      '#title' => $this->t('Template header'),
      '#type' => 'text_format',
      '#default_value' => $template_header['value'] ?: '',
      '#format' => $template_header['format'] ?: 'mail_html',
      '#allowed_formats' => [
        'mail_html',
      ],
      '#description' => $this->t('Enter information you want to show in the email notifications header'),
    ];

    $template_footer = $config->get('template_footer');
    $form['template']['template_footer'] = [
      '#title' => $this->t('Template footer'),
      '#type' => 'text_format',
      '#default_value' => $template_footer['value'] ?: '',
      '#format' => $template_footer['format'] ?: 'mail_html',
      '#allowed_formats' => [
        'mail_html',
      ],
      '#description' => $this->t('Enter information you want to show in the email notifications footer'),
    ];

    $form['timeslot'] = [
      '#type' => 'details',
      '#title' => $this->t('Time slot for daily or weekly notifications'),
      '#open' => FALSE,
    ];

    // Settings helper for admins.
    $form['timeslot']['helper'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Define a time slot on a day for sending daily or weekly notifications.'),
    ];
    $form['timeslot']['timeslot_start'] = [
      '#type' => 'select',
      '#title' => $this->t("Send daily or weekly notifications after"),
      '#options' => $this->timeinterval(),
      '#default_value' => $config->get('timeslot_start') ? $config->get('timeslot_start') : 0,
      '#description' => t('For reference, current server time is: @time', ['@time' => $this->dateFormatter->format(time(), 'custom', 'H:i', $this->config('system.date')->get('timezone')['default'])]),
    ];
    $form['timeslot']['timeslot_end'] = [
      '#type' => 'select',
      '#title' => $this->t("Send daily or weekly notifications before"),
      '#options' => $this->timeinterval(),
      '#default_value' => $config->get('timeslot_end') ? $config->get('timeslot_end') : 1435,
      '#description' => $this->t('Be aware, that a cron run has to happen during the time slot!'),
    ];
    $form['remove_open_social_branding'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove Open Social Branding'),
      '#description' => $this->t('Open Social Branding will be replaced by site name (and slogan if available).'),
      '#default_value' => $config->get('remove_open_social_branding'),
    ];

    $form['do_not_send_emails_new_users'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Don't send email notifications to users who have never logged in"),
      '#description' => $this->t('When this setting is enabled, users who have never logged in will not receive email notifications, they still receive notifications via the notification centre within the community.'),
      '#default_value' => $config->get('do_not_send_emails_new_users'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $start = $form_state->getValue('timeslot_start');
    $end = $form_state->getValue('timeslot_end');
    if ($end <= $start) {
      $form_state->setErrorByName('timeslot_end', t('Please specify a valid time slot'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Save config.
    $config = $this->config('social_swiftmail.settings');
    $config->set('remove_open_social_branding', $form_state->getValue('remove_open_social_branding'));
    $config->set('do_not_send_emails_new_users', $form_state->getValue('do_not_send_emails_new_users'));

    // Set notification settings.
    $templates = $this->emailActivityDestination->getSendEmailMessageTemplates();
    $user_inputs = $form_state->getUserInput();
    foreach (array_keys($templates) as $template) {
      if (isset($user_inputs[$template])) {
        $config->set('template_frequencies.' . $template, $user_inputs[$template]);
      }
    }

    // Define the time slot for sending daily or weekly notifications.
    $config->set('timeslot_start', $form_state->getValue('timeslot_start'));
    $config->set('timeslot_end', $form_state->getValue('timeslot_end'));

    // Set the template header and footer settings.
    $config->set('template_header', $form_state->getValue('template_header'));
    $config->set('template_footer', $form_state->getValue('template_footer'));

    $config->save();
  }

  /**
   * Returns row for table.
   *
   * @param string $template
   *   Template ID.
   * @param array $notification_options
   *   Array of options.
   * @param array $template_frequencies
   *   Frequencies for all templates from config.
   *
   * @return array[]
   *   Row.
   */
  private function buildRow($template, array $notification_options, array $template_frequencies) {
    $email_message_templates = $this->emailActivityDestination->getSendEmailMessageTemplates();
    $row = [
      [
        'width' => '50%',
        'data' => ['#plain_text' => $email_message_templates[$template]],
      ],
    ];

    $default_value = isset($template_frequencies[$template]) ? $template_frequencies[$template] : 'immediately';

    foreach ($notification_options as $notification_id => $notification_option) {
      $parents_for_id = [$template, $notification_id];
      $row[] = [
        'data' => [
          '#type' => 'radio',
          '#title' => $notification_option,
          '#return_value' => $notification_id,
          '#value' => $default_value === $notification_id ? $notification_id : FALSE,
          '#name' => $template,
          '#id' => HtmlUtility::getUniqueId('edit-' . implode('-', $parents_for_id)),
        ],
      ];
    }

    return $row;
  }

  /**
   * Returns timeintervals.
   *
   * @return array
   *   Interval.
   */
  private function timeinterval() {
    $options = [];
    for ($x = 0; $x < 24; $x++) {
      for ($y = 0; $y < 60; $y = $y + 5) {
        $key = $x * 60 + $y;
        $options[$key] = ($x >= 10 ? $x : '0' . $x) . ":" . ($y >= 10 ? $y : '0' . $y);
      }
    }
    return $options;
  }

}
