<?php

/**
 * @param DOMAttr[] $pieces
 * @return string
 */
function mergeValues($glue,$pieces/*,$property='nodeValue'*/) {
    $values=[];
    foreach ($pieces as $node) $values[]=$node->nodeValue;
    return implode($glue,$values);
}