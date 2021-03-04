<?php

namespace Drupal\colorbox_field_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'colorbox_field_formatter' formatter for entityreferences.
 *
 * @FieldFormatter(
 *   id = "colorbox_field_formatter_entityreference",
 *   label = @Translation("Colorbox FF"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class ColorboxFieldFormatterEntityreference extends ColorboxFieldFormatter {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) { // Форма налаштування поля. Відображається в адміністративній панелі
    $form = parent::settingsForm($form, $form_state); // Оголошення масиву форми. Успадкування від ColorboxFieldFormatter
    $form['link_type']['#access'] = FALSE; // Приховуємо поле link_type
    $form['link']['#access'] = FALSE; // Приховуємо поле link
    return $form; // Повертається масив з формою 
  }

  /**
   * {@inheritdoc}
   */
  protected function viewValue(FieldItemInterface $item) {
    /** @noinspection PhpUndefinedFieldInspection */
    return $item->entity->label();
  }

  /**
   * {@inheritdoc}
   */
  protected function getUrl(FieldItemInterface $item): Url {
    /** @noinspection PhpUndefinedFieldInspection */
    return $item->entity->toUrl();
  }

}
