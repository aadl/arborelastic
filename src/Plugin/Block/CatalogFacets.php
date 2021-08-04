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
          if ($i !== 'name') {
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
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    if ($type == 'community') {
      $checked = (isset($_GET['oldnews_date']) ? ' checked' : '');
      if ($checked) $years = explode(',', $_GET['oldnews_date']);
      $output .= '<br><div class="facets-toggle"><span class="facets-toggle-icon"><span class="facets-toggle-symbol">-</span></span>Year Range</div><span class="facets-section">';
      $output .= '<span class="facet-selection"><input type="checkbox" name="oldnews_date" value="0" id="facet-oldnews_date" class="facet-checkbox"' . $checked . '>';
      $output .= '<label for="facet-oldnews_date">Limit to Years</label><br>
        <input class="oldnews-date-search" type="text" value="' . ($years[0] ?? '') . '"> to <input class="oldnews-date-search" type="text" value="' . ($years[1] ?? '') . '">
        </span>';
      $output .= '</span>';
    }
    if ($user->hasPermission('access accountfix')) {
      if ($type == 'catalog') {
        $checked = (isset($_GET['active']) ? ' checked' : '');
        $output .= '<br><div class="facets-toggle"><span class="facets-toggle-icon"><span class="facets-toggle-symbol">-</span></span>Suppressed</div><span class="facets-section">';
          $output .= '<span class="facet-selection"><input type="checkbox" name="active" value="0,1" id="facet-active" class="facet-checkbox"' . $checked . '>';
          $output .= '<label for="facet-active">Include Suppressed</label></span>';
          $output .= '</span>';
        } elseif ($type == 'community') {
          $checked = (isset($_GET['photo_indexed']) ? ' checked' : '');
          $output .= '<br><div class="facets-toggle"><span class="facets-toggle-icon"><span class="facets-toggle-symbol">-</span></span>Photo Indexed</div><span class="facets-section">';
          $output .= '<span class="facet-selection"><input type="checkbox" name="photo_indexed" value="0" id="facet-photo_indexed" class="facet-checkbox"' . $checked . '>';
          $output .= '<label for="facet-photo_indexed">Not Indexed</label></span>';
          $output .= '</span>';
        }
    }
    $output .= '<button type="submit" class="button facets-apply">Apply</button>';
    $output .= '<button class="button facets-reset">Reset</button>';
    $output .= '</form>';

    return [
      '#markup' => $output,
      '#cache' => ['max-age' => 0],
      '#allowed_tags' => ['form', 'span', 'div', 'input', 'label', 'br', 'button']
    ];
  }

}
