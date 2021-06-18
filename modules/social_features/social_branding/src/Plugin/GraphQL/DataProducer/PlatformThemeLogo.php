<?php

namespace Drupal\social_branding\Plugin\GraphQL\DataProducer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Gets the platform theme logo information.
 *
 * @DataProducer(
 *   id = "platform_theme_logo",
 *   name = @Translation("Platform Theme Logo"),
 *   description = @Translation("The Platform Theme Logo."),
 *   produces = @ContextDefinition("string",
 *     label = @Translation("Platform Theme Logo")
 *   )
 * )
 */
class PlatformThemeLogo extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * PlatformThemeLogo constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $config_factory;
  }

  /**
   * Returns platform theme logo information.
   *
   * @return string|null
   *   The platform theme logo configuration.
   */
  public function resolve() : ?string {
    if ($this->config->get('socialblue.settings')->get('logo.path')) {
      return $this->config->get('socialblue.settings')->get('logo.path');
    }
    return NULL;
  }

}
