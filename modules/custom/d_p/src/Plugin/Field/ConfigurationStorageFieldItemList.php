<?php

namespace Drupal\d_p\Plugin\Field;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\d_p\ParagraphSettingSelectInterface;
use Drupal\d_p\ParagraphSettingTypesInterface;

/**
 * Provides field item list class for configuration storage field type.
 *
 * @package Drupal\d_p\Plugin\Field
 */
class ConfigurationStorageFieldItemList extends FieldItemList implements ConfigurationStorageFieldItemListInterface {
  use LoggerChannelTrait;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Paragraph setting plugin manager.
   *
   * @var \Drupal\d_p\ParagraphSettingPluginManagerInterface
   */
  protected $pluginManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);

    $this->pluginManager = \Drupal::service('d_p.paragraph_settings.plugin.manager');
    $this->logger = $this->getLogger('d_p');
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $value = parent::getValue();

    return $value[0] ?? new \StdClass();
  }

  /**
   * {@inheritdoc}
   */
  public function hasClasses(): bool {
    return (bool) count($this->getClasses());
  }

  /**
   * {@inheritdoc}
   */
  public function hasClass($classes): bool {
    if (is_array($classes)) {
      foreach ($classes as $class) {
        if ($this->hasClass($class)) {
          return TRUE;
        }
      }
      return FALSE;
    }

    return in_array((string) $classes, $this->getClasses());
  }

  /**
   * {@inheritdoc}
   */
  public function getClasses() {
    $classes = $classes_value = $this->getClassesValue();

    if (is_object($classes_value)) {
      $classes = get_object_vars($classes_value);
    }
    elseif (is_string($classes_value)) {
      $classes = explode(self::CSS_CLASS_DELIMITER, $classes_value);
    }
    elseif (!is_array($classes)) {
      $classes = [];
    }

    $this->appendDefaultClasses($classes);

    return array_filter($classes);
  }

  /**
   * Get value containing classes only.
   *
   * @return string
   *   Classes string.
   */
  protected function getClassesValue() {
    return $this->getValue()->{ParagraphSettingTypesInterface::CSS_CLASS_SETTING_NAME} ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function addClass(string $value): ConfigurationStorageFieldItemListInterface {
    $classes = $this->getClasses();
    $classes[] = $value;

    $this->setClasses($classes);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeClass(string $value): ConfigurationStorageFieldItemListInterface {
    if ($this->hasClass($value)) {
      $classes = $this->getClasses();

      unset($classes[array_search($value, $classes)]);

      $this->setClasses($classes);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function replaceClass(string $old_value, string $new_value): ConfigurationStorageFieldItemListInterface {
    if ($this->hasClass($old_value)) {
      $classes = $this->getClasses();
      $classes[array_search($old_value, $classes)] = $new_value;

      $this->setClasses($classes);
    }

    return $this;
  }

  /**
   * Setter for CSS classes.
   *
   * @param array $classes
   *   Classes to be set.
   */
  protected function setClasses(array $classes): void {
    $values = $this->getValue();
    $values->{ParagraphSettingTypesInterface::CSS_CLASS_SETTING_NAME} = array_unique($classes);

    $this->setEncodedValue($values);
  }

  /**
   * Adds default classes to classes set.
   *
   * @param array $classes
   *   Default classes to be populated.
   */
  protected function appendDefaultClasses(array &$classes) {

    $defaults = $this->getStorageItemDefaultClasses(ParagraphSettingTypesInterface::CSS_CLASS_SETTING_NAME);
    foreach ($defaults as $modifier) {
      // Add default classes if any value from options is not present.
      if (empty(array_intersect($modifier['options'], $classes))) {
        $classes[] = $modifier['default'];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasSettingValue(string $setting_name): bool {
    return in_array($setting_name, $this->getSettingValue($setting_name));
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingValue(string $setting_name, $default = NULL) {
    return $this->getValue()->$setting_name ?? $this->getStorageItemDefaultValue($setting_name) ?? $default;
  }

  /**
   * {@inheritdoc}
   */
  public function setSettingValue(string $name, $value): ConfigurationStorageFieldItemListInterface {
    $values = $this->getValue();
    $values->$name = $value;

    $this->setEncodedValue($values);

    return $this;
  }

  /**
   * Set value as JSON encoded string.
   *
   * @param mixed $values
   *   Values to be set.
   */
  protected function setEncodedValue($values) {
    $this->setValue(json_encode($values), TRUE);
  }

  /**
   * Getter for storage item default value.
   *
   * @param string $storage_id
   *   The storage id.
   *
   * @return mixed|null
   *   The default value.
   */
  protected function getStorageItemDefaultValue(string $storage_id) {
    try {
      return $this->loadStorageItemById($storage_id)->getDefaultValue();
    }
    catch (PluginException $exception) {
      $this->logger->error($exception->getMessage());

      return NULL;
    }
  }

  /**
   * Getter for storage item default classes.
   *
   * @param string $storage_id
   *   The storage id.
   *
   * @return array
   *   The default classes.
   */
  protected function getStorageItemDefaultClasses(string $storage_id): array {
    $defaults = [];

    try {
      $plugin = $this->loadStorageItemById($storage_id);
      /** @var \Drupal\d_p\ParagraphSettingInterface[] $plugins */
      $class_plugins = $plugin->getChildrenPlugins();

      foreach ($class_plugins as $plugin) {
        if ($plugin instanceof ParagraphSettingSelectInterface) {
          $defaults[] = [
            'options' => $plugin->getOptions(),
            'default' => $plugin->getDefaultValue(),
          ];
        }
      }
    }
    catch (PluginException $exception) {
      $this->logger->error($exception->getMessage());
    }

    return $defaults;
  }

  /**
   * Loads storage item.
   *
   * @param string $storage_id
   *   The storage id (plugin id).
   *
   * @return \Drupal\d_p\ParagraphSettingInterface|object
   *   The plugin instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function loadStorageItemById(string $storage_id) {
    return $this->pluginManager->getPluginById($storage_id);
  }

}
