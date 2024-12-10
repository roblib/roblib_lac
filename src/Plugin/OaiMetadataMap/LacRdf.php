<?php

namespace Drupal\roblib_lac\Plugin\OaiMetadataMap;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\rest_oai_pmh\Plugin\OaiMetadataMapBase;
use Drupal\views\Views;

/**
 * Default Metadata Map.
 *
 * @OaiMetadataMap(
 *  id = "lac_rdf",
 *  label = @Translation("LAC (View Mapping)"),
 *  metadata_format = "thesis",
 *  template = {
 *    "type" = "module",
 *    "name" = "roblib_lac",
 *    "directory" = "templates",
 *    "file" = "thesis"
 *  }
 * )
 */
class LacRdf extends OaiMetadataMapBase {

  /**
   * Get the top level XML for the OAI response.
   */
  public function getMetadataFormat() {
    return [
      'metadataPrefix' => 'thesis',
      'schema' => 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd',
      'metadataNamespace' => 'http://www.openarchives.org/OAI/2.0/oai_dc/',
    ];
  }

  /**
   * Wrap the Thesis response.
   */
  public function getMetadataWrapper() {
    return [
      'thesis' => [
        '@xmlns' => 'http://www.ndltd.org/standards/metadata/etdms/1.1/',
        '@xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
        '@xmlns:dc' => 'http://purl.org/dc/elements/1.1/',
        '@xmlns:dcterms' => 'http://purl.org/dc/terms/',
        '@xsi:schemaLocation' => 'http://www.ndltd.org/standards/metadata/etdms/1.1/ http://www.ndltd.org/standards/metadata/etdms/1.1/etdms11.xsd http://purl.org/dc/elements/1.1/ http://www.ndltd.org/standards/metadata/etdms/1.1/etdmsdc.xsd http://purl.org/dc/terms/ ',

      ],
    ];
  }

  /**
   * Method to transform the provided entity into the desired metadata record.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to transform.
   *
   * @return string
   *   rendered XML.
   */
  public function transformRecord(ContentEntityInterface $entity) {
    $config = \Drupal::config('rest_oai_pmh.settings');
    $view_info = $config->get('lac_view');
    if (empty($view_info['view_machine_name'])) {
      \Drupal::logger('lac')->warning(
            $this->t("View machine name not set.")
        );
      return '';
    }
    $view = Views::getView($view_info['view_machine_name']);
    if (!isset($view)) {
      \Drupal::logger('lac')->warning(
            $this->t("View machine name not valid.")
        );
      return '';
    }
    if (!$view->access($view_info['view_display_name'])) {
      \Drupal::logger('lac')->warning(
            $this->t("View display name not valid or not set.")
        );
      return '';
    }

    $view->setDisplay($view_info['view_display_name']);
    $argument = [$entity->id()];
    $view->setArguments($argument);
    $view->preExecute();
    $view->execute();
    $view_result = $view->result;
    $view->render();

    foreach ($view_result as $row) {
      foreach ($view->field as $field) {
        $label = $field->label();
        $value = $field->advancedRender($row);

        if (!is_string($value)) {
          $value = $value->__toString();
        }

        if (!empty($value)) {
          $render_array['elements'][$label] = $value;
        }
      }
    }

    if (empty($render_array)) {
      return '';
    }

    return parent::build($render_array);
  }

}
