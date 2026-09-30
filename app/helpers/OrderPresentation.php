<?php

/**
 * Render the shipping status note while allowing only Shopee education links.
 * The upstream tooltip can arrive with its anchor entity-encoded.
 */
function renderOrderStatusDescription($description): string {
  $description = html_entity_decode((string)$description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $pattern = '#<a\s+href=["\'](https://seller\.shopee\.co\.id/edu/article/[0-9]+)["\']\s*>(.*?)</a>#is';
  preg_match_all($pattern, $description, $matches, PREG_OFFSET_CAPTURE);

  if (empty($matches[0])) {
    return htmlspecialchars(strip_tags($description), ENT_QUOTES, 'UTF-8');
  }

  $html = '';
  $offset = 0;
  foreach ($matches[0] as $index => [$match, $position]) {
    $html .= htmlspecialchars(substr($description, $offset, $position - $offset), ENT_QUOTES, 'UTF-8');
    $href = htmlspecialchars($matches[1][$index][0], ENT_QUOTES, 'UTF-8');
    $label = htmlspecialchars(strip_tags($matches[2][$index][0]), ENT_QUOTES, 'UTF-8');
    $html .= '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
    $offset = $position + strlen($match);
  }

  return $html . htmlspecialchars(substr($description, $offset), ENT_QUOTES, 'UTF-8');
}
