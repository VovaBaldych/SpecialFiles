<?php

namespace Drupal\webform\Plugin\Field\FieldFormatter; // Неймспейс для даного форматера

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformMessage;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\Plugin\WebformSourceEntity\QueryStringWebformSourceEntity;
use Drupal\webform\WebformMessageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Link to webform' formatter.
 *
 * @FieldFormatter(
 *   id = "webform_entity_reference_link",
 *   label = @Translation("Link to form"),
 *   description = @Translation("Display link to the referenced webform."),
 *   field_types = {
 *     "webform"
 *   }
 * )
 */
class WebformEntityReferenceLinkFormatter extends WebformEntityReferenceFormatterBase { // Оголошення класу WebformEntityReferenceLinkFormatter. Наслідується від WebformEntityReferenceFormatterBase

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory; // Дана змінна зберігатиме налаштування

  /**
   * The webform message manager.
   *
   * @var \Drupal\webform\WebformMessageManagerInterface
   */
  protected $messageManager; // Дана змінна відповідатиме за вивід тексту та розмітки на фронтенд

  /**
   * The webform token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager; // 

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->messageManager = $container->get('webform.message_manager');
    $instance->tokenManager = $container->get('webform.token_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() { // Налаштування форматера за замовчуваням. Повертається рендер масив
    return [
      'label' => 'Open [webform:title] webform', // Даний текст відображатиметься в адмін. панелі налаштувань форматера
      'dialog' => '', // Ширина діалогового вікна. За замовчуванням значення пусте
      'attributes' => [], // Атрибути HTML-елементу діалогового вікна. Сюди входятm CSS-клас, посилання на додаткові CSS-стиліб посилання на додаткові атрибути (YAML) 
    ] + parent::defaultSettings(); // Додаємо настройки за замовчуванням, успадковані від біатьківського класу WebformEntityReferenceFormatterBase
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() { // Дана функція дозволяє вивести коротку інформацію в адмін. панелі про поточні налаштування поля
    $summary = parent::settingsSummary(); // Наслідуємо стандартні значення від WebformEntityReferenceFormatterBase
    $summary[] = $this->t('Label: @label', ['@label' => $this->getSetting('label')]); // Додаємо лейбл
    $dialog_option_name = $this->getSetting('dialog'); // Дана змінна зберігає ширину діалогового вікна
    if ($dialog_option = $this->configFactory->get('webform.settings')->get('settings.dialog_options.' . $dialog_option_name)) { // Якщо ширина вікна задана в налаштуваннях
      $summary[] = $this->t('Dialog: @dialog', ['@dialog' => (isset($dialog_option['title']) ? $dialog_option['title'] : $dialog_option_name)]); // то додаємо це в масив
    }
    return $summary; // Повертаємо масив з інформацією
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) { // Форма налаштування поля. Відображається в адміністративній панелі
    $form = parent::settingsForm($form, $form_state); // Оголошення масиву форми. Успадкування від WebformEntityReferenceFormatterBase

    if ($this->fieldDefinition->getTargetEntityTypeId() === 'paragraph') { // 
      $form['message'] = [ // Поле з попереджувальним повідомленням
        '#type' => 'webform_message', // Тип - повідомлення вебформи
        '#message_type' => 'warning', // Тип повідомлення - поереджувальне
        '#message_message' => $this->t("This paragraph field's main entity will be used as the webform submission's source entity."), // Текст повідомлення
        '#message_close' => TRUE, // По замовчуванню поле з повідомленням приховане
        '#message_storage' => WebformMessage::STORAGE_SESSION, // Місце, де це повідомлення зберігається
      ];
    }

    $form['label'] = [ // Текстове поле з для лейблу
      '#title' => $this->t('Label'), // Назва поля
      '#type' => 'textfield', // Тип - текстове поле
      '#default_value' => $this->getSetting('label'), // Значення за замовчуванням. Береться з рядка 63 цього ж файлу 
      '#required' => TRUE, // Обов'язкове до заповнення
    ];

    $dialog_options = $this->configFactory->get('webform.settings')->get('settings.dialog_options'); // Змінна, в якій зберігаються опції ширини діалогового вікна
    
    if ($dialog_options) { // Якщо змінна з опціями вікна існує 
      $options = []; // Ологошується масив , в якому зберігатимуться тільки назви опцій
      foreach ($dialog_options as $dialog_option_name => $dialog_option) { // Заходимо в масив з налаштуваннями діалогового вікна
        $options[$dialog_option_name] = (isset($dialog_option['title'])) ? $dialog_option['title'] : $dialog_option_name; // Записуємо назву опції
      }
      $form['dialog'] = [ // Поле для вибору ширини модального вікна
        '#title' => $this->t('Dialog'), // Назва поля
        '#type' => 'select', // Тип - випадаючий список
        '#empty_option' => $this->t('- Select dialog -'), // Значення пустого поля
        '#default_value' => $this->getSetting('dialog'), // Значення за замовчуванням
        '#options' => $options, // Список з опціями. Назви опцій знаходяться в масиві $options
      ];
      $form['attributes'] = [ // Група полів для запису атрибутів для HTML-елемента
        '#type' => 'webform_element_attributes', // Тип - webform_element_attributes
        '#title' => $this->t('Link'), // Назва для групи полів
        '#default_value' => $this->getSetting('attributes'), // Значення за замовчуванням 
      ];
    }
    return $form; // Повертається масив з формою 
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) { // Функція, яка відповідає за вивід поля на фронтенд. Повертає рендер-масив для значення поля
    $source_entity = $items->getEntity(); // Оголошуємо змінну, де зберігається сутність
    $this->messageManager->setSourceEntity($source_entity); // Встановлюємо шлях до веб-форми

    $elements = []; // Оголошення масиву, який буде повертатись функцією. В цьому мисаві лежатимуть необхідні значення поля такі як URL форми, розміри вікна, ID ноди тощо

    /** @var \Drupal\webform\WebformInterface[] $entities */
    $entities = $this->getEntitiesToView($items, $langcode); // Оголошуємо змінну, де зберігаються сутності для перегляду
    foreach ($entities as $delta => $entity) { // За допомогою циклу заходимо в конкретний entity
      if ($entity->id() && !$entity->access('submission_create')) { // Не відображати веб-форму, якщо поточний користувач її не засабмітив
        continue;
      }

      if ($entity->isOpen()) { // Якщо entity відкрито
        $link_label = $this->getSetting('label'); // Оголошуємо змінну, де зберігається значення поля 'label'
        if (strpos($link_label, '[webform_submission') !== FALSE) { // Якщо форма засабмічена
          $link_entity = WebformSubmission::create([ // Створюємо новий сабмішн
            'webform_id' => $entity->id(), // Записуємо id вебформи
            'entity_type' => $source_entity->getEntityTypeId(), // Записуємо тип entity
            'entity_id' => $source_entity->id(), // Записуємо id entity
          ]);

          $link_entity->getWebform()->invokeHandlers('overrideSettings', $link_entity); // Викликати параметри заміни для всіх обробників веб-форм, щоб налаштувати будь-які параметри форми
        }
        else {
          $link_entity = $entity; // Присвоюємо сабмішну значення вибраного ентіті
        }

        $link_options = QueryStringWebformSourceEntity::getRouteOptionsQuery($source_entity); // Оголошуємо змінну, де зберігатимуться параметри для посилання
        $link = [ // Встановлюємо параметри для посилання
          '#type' => 'link', // Тип - посилання
          '#title' => ['#markup' => $this->tokenManager->replace($link_label, $link_entity)], // Назва посилання. Витягується з відповідного поля налаштувань
          '#url' => $entity->toUrl('canonical', $link_options), // Встановлюємо URL посилання
          '#attributes' => $this->getSetting('attributes') ?: [],
        ];
        if ($dialog = $this->getSetting('dialog')) {
          $link['#attributes']['class'][] = 'webform-dialog'; // Додаємо HTML-клас 'webform-dialog'
          $link['#attributes']['class'][] = 'webform-dialog-' . $dialog; // Додаємо HTML-клас 'webform-dialog' + значення з поля налаштувань 'Dialog'
          if (!\Drupal::config('webform.settings')->get('settings.dialog')) { // Прикріплюємо біблітоку для діалогового вікна вебформи, якщо цього ще не зроблено
            $link['#attached']['library'][] = 'webform/webform.dialog';
            $link['#attached']['drupalSettings']['webform']['dialog']['options'] = \Drupal::config('webform.settings')->get('settings.dialog_options');
          }
        }
        $elements[$delta] = $link; // Записуємо посилання в рендер-масив
      }
      else {
        $this->messageManager->setWebform($entity);
        $message_type = $entity->isOpening() ? WebformMessageManagerInterface::FORM_OPEN_MESSAGE : WebformMessageManagerInterface::FORM_CLOSE_MESSAGE;
        $elements[$delta] = $this->messageManager->build($message_type);
      }

      $this->setCacheContext($elements[$delta], $entity, $items[$delta]); // Встановлюється кешування
    }

    return $elements; // Повертаємо рендер масив елемента
  }

}
