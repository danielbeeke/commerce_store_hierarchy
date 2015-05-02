<?php

/**
 * @file
 * Contains Drupal\commerce_store_hierarchy\OverviewStores.
 */

namespace Drupal\commerce_store_hierarchy;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\commerce_store\Entity\StoreType;
use Drupal\Component\Utility\SafeMarkup;

/**
 * Provides a list controller for stores.
 */
class OverviewStores extends FormBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The term storage controller.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $storageController;

  /**
   * Constructs an OverviewTerms object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   */
  public function __construct(ModuleHandlerInterface $module_handler, EntityManagerInterface $entity_manager) {
    $this->moduleHandler = $module_handler;
    $this->storageController = $entity_manager->getStorage('commerce_store');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_store_hierarchy_overview_stores';
  }


  public function sort_stores_by_weight($a, $b) {
    if ($a->parent->value && $a->parent->value == $b->id->value) {
      return -1;
    }

    return ($a->weight->value < $b->weight->value) ? -1 : 1;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $stores = $this->storageController->loadMultiple();

    uasort($stores, array($this, 'sort_stores_by_weight'));

    $form['stores'] = array(
      '#type' => 'table',
      // '#header' => array($this->t('Name'), $this->t('Type'), $this->t('E-mail'), $this->t('Currency'), $this->t('Weight')),
      '#header' => array($this->t('Name'), $this->t('Weight'), $this->t('Depth'), $this->t('Parent'), $this->t('Weight')),
      '#empty' => $this->t('No stores found, create first a store.'),
      '#attributes' => array(
        'id' => 'stores',
      ),
    );

    foreach ($stores as $key => $store) {
      $form['stores'][$key]['#store'] = $store;

      if (isset($store->depth->value) && $store->depth->value > 0) {
        $indentation = array(
          '#theme' => 'indentation',
          '#size' => $store->depth->value,
        );
      }

      $form['stores'][$key]['store'] = array(
        '#prefix' => !empty($indentation) ? drupal_render($indentation) : '',
        '#type' => 'link',
        '#title' => $store->getName(),
        '#url' => $store->urlInfo('edit-form'),
      );

      $form['stores'][$key]['store']['sid'] = array(
        '#type' => 'hidden',
        '#value' => $store->id(),
        '#attributes' => array(
          'class' => array('store-id'),
        ),
      );

      $form['stores'][$key]['store']['parent'] = array(
        '#type' => 'hidden',
        '#default_value' => $store->parent->target_id,
        '#attributes' => array(
          'class' => array('store-parent'),
        ),
      );

      $form['stores'][$key]['store']['depth'] = array(
        '#type' => 'hidden',
        '#default_value' => $store->depth->value,
        '#attributes' => array(
          'class' => array('store-depth'),
        ),
      );

      $storeType = StoreType::load($store->bundle());

      $form['stores'][$key]['weight_label']['#markup'] = $store->weight->value;
      $form['stores'][$key]['depth']['#markup'] = $store->depth->value;
      $form['stores'][$key]['parent']['#markup'] = $store->parent->target_id;

      // $form['stores'][$key]['type']['#markup'] = SafeMarkup::checkPlain($storeType->label());
      // $form['stores'][$key]['mail']['#markup'] = $store->getEmail();
      // $form['stores'][$key]['default_currency']['#markup'] = $store->getDefaultCurrency();

      $form['stores'][$key]['weight'] = array(
        '#type' => 'weight',
        '#delta' => $key,
        '#title' => $this->t('Weight for added term'),
        '#title_display' => 'invisible',
        '#default_value' => $store->weight->value,
        '#attributes' => array(
          'class' => array('store-weight'),
        ),
      );

      $form['stores'][$key]['#attributes']['class'][] = 'draggable';

    }

    $form['stores']['#tabledrag'][] = array(
      'action' => 'match',
      'relationship' => 'parent',
      'group' => 'store-parent',
      'subgroup' => 'store-parent',
      'source' => 'store-id',
      'hidden' => FALSE,
    );

    $form['stores']['#tabledrag'][] = array(
      'action' => 'depth',
      'relationship' => 'group',
      'group' => 'store-depth',
      'hidden' => FALSE,
    );

    $form['stores']['#tabledrag'][] = array(
      'action' => 'order',
      'relationship' => 'sibling',
      'group' => 'store-weight',
    );

    $form['actions'] = array('#type' => 'actions', '#tree' => FALSE);
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    foreach($values['stores'] as $store_value) {
      $store = entity_load('commerce_store', $store_value['store']['sid']);

      if ($store_value['store']['parent']) {

        $parent = entity_load('commerce_store', $store_value['store']['parent']);
        $store->set('parent', $parent->id());
        $store->set('weight', NULL);
      }
      else {
        $store->set('weight', $store_value['weight']);
        $store->set('parent', NULL);
      }

      $store->set('depth', $store_value['store']['depth']);
      $store->save();
    }
  }
}
