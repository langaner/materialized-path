## Materialized path trait for Laravel

[![Latest Stable Version](https://poser.pugx.org/langaner/materialized-path/v/stable)](https://packagist.org/packages/langaner/materialized-path) 
[![Total Downloads](https://poser.pugx.org/langaner/materialized-path/downloads)](https://packagist.org/packages/langaner/materialized-path) 
[![Latest Unstable Version](https://poser.pugx.org/langaner/materialized-path/v/unstable)](https://packagist.org/packages/langaner/materialized-path) 
[![License](https://poser.pugx.org/langaner/materialized-path/license)](https://packagist.org/packages/langaner/materialized-path)

### Installation

1) Add `langaner/materialized-path` to `composer.json`.

```
"langaner/materialized-path": "dev-master"
```

2)Run `composer update` to pull down the latest version of the package.

3)Now open up app/config/app.php and add the service provider to your providers array.

```
Langaner\MaterializedPath\MaterializedPathServiceProvider::class,
```

4)Add the trait to model what need tree implementation

```
use \Langaner\MaterializedPath\MaterializedPathTrait;
```

### Migrations

Table migration example `page_table`:

```php
	Schema::create('page_table', function(Blueprint $table)
	{
		$table->engine = 'InnoDB';
		$table->increments('id');
		// parent id
        $table->integer('parent_id')->unsigned()->nullable();
        // depth path consisting of models id
        $table->string('path');
        // depth path consisting of models alias
        $table->string('real_path')->unique();
        // alias field
        $table->string('alias')->unique();
        // order position
        $table->integer('position')->default(0)->index();
        // depth level
        $table->integer('level')->default(0)->index();

		$table->foreign('parent_id')->references('id')->on('page_table')->onDelete('cascade');
	});
```

### Available methods

```php
	// Make model is root
	with(new Page())->makeRoot();

	// Make model first children by parent id
	with(new Page())->makeFirstChildOf($parentId);

	// Make model last children by parent id
	with(new Page())->makeLastChildOf($parentId);

	// Make previous sibling
	$page = Page::find(2);
	$page->makePreviousSiblingOf(Page::find(1));

	// Make next sibling
	$page = Page::find(2);
	$page->makeNextSiblingOf(Page::find(1));

	// Update model data
	$page = Page::find(1);
	$page->position = 2;
	with(new Page())->updateNode($page);

	// Get parent
	Page::find(1)->parent()->get();
	
	// Get sibling
	Page::find(1)->sibling()->get();

	// Get childrens by depth
	Page::find(1)->childrenByDepth()->get();

	// Get parents by depth
	Page::find(1)->parentByDepth()->get();

	// Descendant check
	Page::find(1)->isDescendant(Page::find(2));

	// Ancestor check
	Page::find(1)->isAncestor(Page::find(2));

	// Is leaf
	Page::find(1)->isLeaf();

	// All leafs of model
	Page::allLeaf();

	// All roots of model
	Page::allRoot();

	// Get exploded path
	Page::find(1)->getExplodedPath();

	// Build real path by entities
	with(new Page())->buildRealPath($ids);

	// Build model tree
	Page::find(1)->buildTree();

	// Build model tree by parent id
	Page::find(1)->buildChidrenTree();
```

### Configuration

Publish assets `php artisan vendor:publish`

This command initialize config file, located under app/config/packages/langaner/materialized-path/materialized_path.php.

Set `with_translations` to `true` if you need translation in result model. This option required translation pack `https://github.com/dimsav/laravel-translatable`