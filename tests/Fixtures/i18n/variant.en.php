<?php

declare(strict_types=1);

return [
	'plural' => 'ru',
	'messages' => [
		'Save' => 'Save changes',
		'one item' => [':count base-one', ':count base-few', ':count base-many'],
		'Local forms' => ['base-one', 'base-few', 'base-many'],
		'Mixed' => 'Base singular',
	],
	'contexts' => [
		'inventory' => ['one item' => [':count context-one', ':count context-few', ':count context-many']],
		'menu' => ['Save' => 'Save menu'],
		'0' => ['404' => 'Not found'],
	],
	'obsolete' => ['Old' => 'Obsolete'],
];
