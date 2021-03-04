<?php

namespace Drupal\colorbox_field_formatter\Plugin\Field\FieldFormatter; // Неймспейс для даного форматера

use Drupal\colorbox\ColorboxAttachment;
use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\field_ui\Form\EntityViewDisplayEditForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'colorbox_field_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "colorbox_field_formatter",
 *   label = @Translation("Colorbox FF"),
 *   field_types = {
 *     "computed",
 *     "string"
 *   }
 * )
 */
class ColorboxFieldFormatter extends FormatterBase implements ContainerFactoryPluginInterface { // Оголошення класу ColorboxFieldFormatter. Наслідується від FormatterBase та реалізовує інтерфейс

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\colorbox\ColorboxAttachment
   */
  protected $colorboxAttachment;

  /**
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * ColorboxFieldFormatter constructor.
   *
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   * @param array $settings
   * @param $label
   * @param $view_mode
   * @param array $third_party_settings
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   * @param \Drupal\colorbox\ColorboxAttachment $colorbox_attachment
   * @param \Drupal\Core\Utility\Token $token
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, ModuleHandlerInterface $module_handler, ColorboxAttachment $colorbox_attachment, Token $token) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->moduleHandler = $module_handler;
    $this->colorboxAttachment = $colorbox_attachment;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('module_handler'),
      $container->get('colorbox.attachment'),
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'style' => 'default',
      'link_type' => 'content',
      'link' => '',
      'width' => '500',
      'height' => '500',
      'iframe' => 0,
      'inline_selector' => '',
      'anchor' => '',
      'class' => '',
      'rel' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) { // Форма налаштування поля. Відображається в адміністративній панелі
    $form = parent::settingsForm($form, $form_state); // Оголошення масиву форми. Успадкування від FormatterBase

    $form['style'] = [ // Випадаючий список з вибором стилю модального вікна
      '#title' => $this->t('Style of colorbox'), // Назва поля
      '#type' => 'select', // Тип - випадаючий список
      '#default_value' => $this->getSetting('style'), // Значення за замовчуванням 
      '#options' => $this->getStyles(), // Підтягуємо список з опціями
      '#attributes' => [ // Атрибути HTML-елемента
        'class' => ['colorbox-field-formatter-style'], // CSS-клас для випадаючого списку
      ],
    ];

    $form['link_type'] = [
      '#title' => $this->t('Link colorbox to'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('link_type'),
      '#options' => $this->getLinkTypes(),
      '#attributes' => [ // Атрибути HTML-елемента
        'class' => ['colorbox-field-formatter-link-type'], // CSS-клас для випадаючого списку
      ],
      '#states' => [ // Стани
        'visible' => [
          'select.colorbox-field-formatter-style' => ['value' => 'default'],
        ],
      ],
    ];
    $form['link'] = [
      '#title' => $this->t('URI'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('link'),
      '#states' => [
        'visible' => [
          'select.colorbox-field-formatter-style' => ['value' => 'default'],
          'select.colorbox-field-formatter-link-type' => ['value' => 'manual'],
        ],
      ],
    ];
    if ($this->moduleHandler->moduleExists('token') &&
      ($buildInfo = $form_state->getBuildInfo()) &&
      ($callback_object = $buildInfo['callback_object']) &&
      ($callback_object instanceof EntityViewDisplayEditForm)) {
      $form['token_help_wrapper'] = [
        '#type' => 'container',
        '#states' => [
          'visible' => [
            'select.colorbox-field-formatter-style' => ['value' => 'default'],
            'select.colorbox-field-formatter-link-type' => ['value' => 'manual'],
          ],
        ],
      ];
      $form['token_help_wrapper']['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['entity' => $callback_object->getEntity()->getTargetEntityTypeId()],
        '#global_types' => TRUE,
      ];
    }

    $form['inline_selector'] = [
      '#title' => $this->t('Inline selector'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('inline_selector'),
      '#states' => [
        'visible' => [
          'select.colorbox-field-formatter-style' => ['value' => 'colorbox-inline'],
        ],
      ],
    ];

    $form['width'] = [
      '#title' => $this->t('Width'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('width'),
    ];
    $form['height'] = [
      '#title' => $this->t('Height'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('height'),
    ];
    $form['iframe'] = [
      '#title' => $this->t('iFrame Mode'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('iframe'),
    ];
    $form['anchor'] = [
      '#title' => $this->t('Anchor'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('anchor'),
    ];
    $form['class'] = [
      '#title' => $this->t('Class'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('class'),
    ];
    $form['rel'] = [
      '#title' => $this->t('Rel'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('rel'),
      '#description' => $this->t('This can be used to identify a group for Colorbox to cycle through.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $styles = $this->getStyles();
    $summary[] = $this->t('Style: @style', ['@style' => $styles[$this->getSetting('style')],]);

    if ($this->getSetting('style') === 'default') {
      $types = $this->getLinkTypes();
      if ($this->getSetting('link_type') === 'manual') {
        $summary[] = $this->t('Link to @link', ['@link' => $this->getSetting('link'),]);
      }
      else {
        $summary[] = $this->t('Link to @link', ['@link' => $types[$this->getSetting('link_type')],]);
      }
    }

    if ($this->getSetting('style') === 'colorbox-inline') {
      $summary[] = $this->t('Inline selector: @selector', ['@selector' => $this->getSetting('inline_selector'),]);
    }
    $summary[] = $this->t('Width: @width', ['@width' => $this->getSetting('width'),]);
    $summary[] = $this->t('Height: @height', ['@height' => $this->getSetting('height'),]);
    $summary[] = $this->t('iFrame Mode: @mode', ['@mode' => ($this->getSetting('iframe') ? $this->t('Yes') : $this->t('No')),]);
    if (!empty($this->getSetting('anchor'))) {
      $summary[] = $this->t('Anchor: #@anchor', ['@anchor' => $this->getSetting('anchor'),]);
    }
    if (!empty($this->getSetting('class'))) {
      $summary[] = $this->t('Classes: @class', ['@class' => $this->getSetting('class'),]);
    }
    if (!empty($this->getSetting('rel'))) {
      $summary[] = $this->t('Rel: @rel', ['@rel' => $this->getSetting('rel')]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array { // Функція, яка відповідає за вивід поля на фронтенд. Повертає рендер-масив для значення поля
    $element = []; // Оголошення масиву, який буде повертатись функцією. В цьому мисаві лежатимуть необхідні значення поля такі як URL форми, розміри вікна, ID ноди тощо

    foreach ($items as $delta => $item) { // За допомогою циклу заходимо в конкретний елемент (в даному випадку форму)
      $output = $this->viewValue($item); // Зберігається значення поля, яке буде виводитись
      $url = $this->getUrl($item); // Оскільки форма у Drupal - це Entity, то дана змінна зберігає URL до даного Entity
      $options = [ // Оголошення масиву, в якому зберігаються налаштування форматтера
        'html' => TRUE, // Дозволяється виводити HTML. Формується тег <a></a>
        'attributes' => [ // Атрибути HTML-елемента
          'class' => ['colorbox', $this->getSetting('style')], // Для HTML-елементу задається клас 'colorbox', а також класи з поля налаштувань 'Style of colorbox'
        ],
        'query' => [ // Запит на зміну налаштувань
          'width' => $this->getSetting('width'), // Задається ширина вікна колорбоксу, вказана в полі налаштувань 'Width'
          'height' => $this->getSetting('height'),// Задається висота вікна колорбоксу, вказана в полі налаштувань 'Height'
        ],
      ];
      if ($this->getSetting('iframe')) { // Якщо в полі налаштувань активована опція 'iFrame'
        $options['query']['iframe'] = 'true'; // то в рендер-масиві налаштувань змінюється значення цього поля на true
      }
      if (!empty($this->getSetting('anchor'))) { // Якщо поле налаштувань 'anchor' заповнене
        $options['fragment'] = $this->getSetting('anchor'); // то в рендер-масиві налаштувань змінюється значення цього поля на те, яке вказане у відповідному полі налаштувань
      }
      if (!empty($this->getSetting('class'))) { // Якщо поле налаштувань 'class' заповнене
        $options['attributes']['class'] = array_merge($options['attributes']['class'], explode(' ', $this->getSetting('class'))); // то в рендер-масиві налаштувань до наявних класів HTML-елемента додаємо ще й ті класи, які вказані у відповідному полі налаштувань
      }
      if (!empty($this->getSetting('rel'))) { // Якщо поле налаштувань 'rel' заповнене
        $options['attributes']['rel'] = $this->getSetting('rel'); // то в рендер-масиві налаштувань змінюється значення цього поля на те, яке вказане у відповідному полі налаштувань
      }
      if ($this->getSetting('style') === 'colorbox-inline') { // Якщо значення поля налаштувань 'Style of colorbox' дорівнює 'colorbox-inline'
        $colorbox_inline_attributes = [ // Заповнюємо атрибути HTML-елемента
          'data-colorbox-inline' => $this->getSetting('inline_selector'), // У цей атрибут підтягується значення з поля налаштувань 'inline_selector'
          'data-width' => $this->getSetting('width'), // У цей атрибут підтягується значення з поля налаштувань 'width'
          'data-height' => $this->getSetting('height'), // У цей атрибут підтягується значення з поля налаштувань 'height'
        ];
        $options['attributes'] = array_merge($options['attributes'], $colorbox_inline_attributes); // В рендер масиві налаштувань поле 'attributes' доповнюємо значеннями змінної $colorbox_inline_attributes
      }
      $url->setOptions($options); // Встановлюємо параметри для URL вибраного Entity
      $link = Link::fromTextAndUrl($output, $url); // Функція fromTextAndUrl повертає текст посилання, де назва посилання - $output, URL - $url
      $element[$delta] = $link->toRenderable(); // Повертається рендер-масив посилання
    }

    // Прикріплюємо Colorbox JS and CSS.
    if ($this->colorboxAttachment->isApplicable()) { // Перевіряє, чи можна бібліотека готова до використання
      $this->colorboxAttachment->attach($element); // Якщо так - прикріплюємо
    }
    return $element; // Повертаємо рендер масив елемента
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return string|array
   *   The textual output generated.
   */
  protected function viewValue(FieldItemInterface $item) {
    // The text value has no text format assigned to it, so the user input
    // should equal the output, including newlines.
    /** @noinspection PhpUndefinedFieldInspection */
    return nl2br(Html::escape($item->value)); // Вставляє код розриву рядка перед кожним переводом рядка. Уникається текст, спеціальні символи перетворюються в  HTML-сутності. Повертається значення поля.
  }

  /**
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return \Drupal\Core\Url
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function getUrl(FieldItemInterface $item): Url {
    $entity = $item->getEntity();
    if ($this->getSetting('link_type') === 'content') {
      return $entity->toUrl();
    }

    $link = $this->getSetting('link');
    if ($this->moduleHandler->moduleExists('token')) {
      $link = $this->token->replace($this->getSetting('link'), [$entity->getEntityTypeId() => $entity], ['clear' => TRUE]);
    }
    return Url::fromUserInput($link);
  }

  /**
   * Callback to provide an array for a select field containing all supported
   * colorbox styles.
   *
   * @return array
   */
  private function getStyles(): array {
    $styles = [
      'default' => $this->t('Default'),
    ];
    if ($this->moduleHandler->moduleExists('colorbox_inline')) {
      $styles['colorbox-inline'] = $this->t('Colorbox inline');
    }
    if ($this->moduleHandler->moduleExists('colorbox_node')) {
      $styles['colorbox-node'] = $this->t('Colorbox node');
    }

    return $styles;
  }

  /**
   * Callback to provide an arry for a select field containing all link types.
   *
   * @return array
   */
  private function getLinkTypes(): array {
    return [
      'content' => $this->t('Content'),
      'manual' => $this->t('Manually provide a link'),
    ];
  }

}
