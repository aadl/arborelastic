<?php
namespace Drupal\arborelastic\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a Search Facets Block.
 *
 * @Block(
 *   id = "catalog_facets_block",
 *   admin_label = @Translation("Catalog Facets Block"),
 * )
 */
class CatalogFacets extends BlockBase {

  public function build() {
    $type = $this->getConfiguration()['type'];
    include(\Drupal::root() . '/' . drupal_get_path('module', 'arborelastic') . '/src/config/facets.php');

    $output = '<form method="get">';
    $output .= '<span class="no-tabdesk-display">Filter</span>';
    $output .= '<span class="no-tabdesk-display" id="close-search-facets">X</span>';
    $output .= '<div><button class="button facets-apply">Apply</button>';
    $output .= '<button class="button facets-reset">Reset</button></div>';
    foreach ($facets[$type] as $k => $facet) {
      $name = $facet['name'];
      $output .= '<br><div class="facets-toggle"><span class="facets-toggle-icon"><span class="facets-toggle-symbol">-</span></span>' . $k . '</div><span class="facets-section">';
      if (isset($facet['has_subsections'])) {
        foreach ($facet as $i => $section) {
          if (isset($section['subsection'])) {
            if ($i != 'name') {
              $output .= '<div class="facets-toggle"><span class="facets-toggle-icon"><span class="facets-toggle-symbol">-</span></span>' . $i . '</div><span class="facets-section">';
              foreach ($section as $v => $sel) {
                $checked = '';
                if (isset($_GET[$name])) {
                  // strip &quot; where needed
                  $val = str_replace('&quot;', '"', $v);
                  $checked = (in_array($val, explode(',', $_GET[$name])) ? ' checked' : '');
                }
                if ($v != 'subsection') {
                  $output .= "<span class=\"facet-selection\"><input type=\"checkbox\" name=\"$name\" value=\"$v\" id=\"facet-$v\" class=\"facet-checkbox\" $checked>";
                  $output .= "<label for=\"facet-$v\">$sel</label></span>";
                }
              }
              $output .= '</span>';
            }
          }
        }
      } else {
        foreach ($facet as $i => $sel) {
          if ($i != 'name') {
            $checked = '';
            if (isset($_GET[$name])) {
              // strip &quot; where needed
              $val = str_replace('&quot;', '"', $i);
              $checked = (in_array($val, explode(',', $_GET[$name])) ? ' checked' : '');
            }
            $output .= "<span class=\"facet-selection\"><input type=\"checkbox\" name=\"$name\" value=\"$i\" id=\"facet-$i\" class=\"facet-checkbox\" $checked>";
            $output .= "<label for=\"facet-$i\">$sel</label></span>";
          }
        }
      }
      $output .= '</span>';
    }
    $output .= '<button type="submit" class="button facets-apply">Apply</button>';
    $output .= '<button class="button facets-reset">Reset</button>';
    $output .= '</form>';

    return [
      '#markup' => $output,
      '#allowed_tags' => ['form', 'span', 'div', 'input', 'label', 'br', 'button']
    ];
  }

}
