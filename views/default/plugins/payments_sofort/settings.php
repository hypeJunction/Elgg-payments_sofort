<?php

$entity = elgg_extract('entity', $vars);

$fields = [
	'test_config_key',
	'live_config_key',
];

foreach ($fields as $field) {
	echo elgg_view_field([
		'#type' => 'text',
		'#label' => elgg_echo("payments:sofort:$field"),
		'name' => "params[$field]",
		'value' => $entity->$field,
	]);
}