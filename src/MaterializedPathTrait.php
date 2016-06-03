<?php namespace Langaner\MaterializedPath;

use App;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Langaner\MaterializedPath\Exceptions\ModelNotFoundException;
use Langaner\MaterializedPath\Exceptions\MoveException;
use Langaner\MaterializedPath\Exceptions\NodeNotFoundException;
use Langaner\MaterializedPath\Exceptions\ParentNotFoundException;
use Langaner\MaterializedPath\Exceptions\SiblingNotFoundException;

trait MaterializedPathTrait
{
    /**
     * Parend column.
     *
     * @var string
     */
    protected $columnTreePid = 'parent_id';

    /**
     * Position column.
     *
     * @var string
     */
    protected $columnTreeOrder = 'position';

    /**
     * Path column.
     *
     * @var string
     */
    protected $columnTreePath = 'path';

    /**
     * Real path column.
     *
     * @var string
     */
    protected $columnTreeRealPath = 'real_path';

    /**
     * Alias column.
     *
     * @var string
     */
    protected $columnAlias = 'alias';

    /**
     * Level column.
     *
     * @var string
     */
    protected $columnTreeDepth = 'level';

    /**
     * Separator.
     *
     * @var string
     */
    protected $separator = '/';

    public function getConfig()
    {
        return App::make('config');
    }

    /**
     * Create nulled node.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function createRootObject()
    {
        $root = new $this;
        $root->fill([
            $this->getColumnTreePath() => $this->separator.'0'.$this->separator,
            $this->getColumnTreePid() => null,
            $this->getColumnTreeDepth() => 0,
            $this->getColumnTreeRealPath() => '',
            $this->getColumnTreeOrder() => 0
        ]);

        return $root;
    }

    /**
     * Make root node.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function makeRoot()
    {
        $this->fill([
            $this->getColumnTreePath() => $this->separator.'0'.$this->separator,
            $this->getColumnTreePid() => null,
            $this->getColumnTreeDepth() => 0,
            $this->getColumnTreeRealPath() => 'root',
            $this->getColumnAlias() => 'root',
            $this->getColumnTreeOrder() => (static::allRoot()->max($this->getColumnTreeOrder()) + 1)
        ]);

        $this->save();

        return $this;
    }

    /**
     * Make node first chield of parent.
     *
     * @param  int $parentId
     * @return \Illuminate\Database\Eloquent\Model
     *
     * @throws \Langaner\MaterializedPath\Exceptions\MoveException
     * @throws \Langaner\MaterializedPath\Exceptions\ParentNotFoundException
     */
    public function makeFirstChildOf($parentId)
    {
        if ($this->exists and $this->isAncestor($parentId)) {
            throw new MoveException('Cant move Ancestor to Descendant');
        }

        $parent = $this->newQuery()->find($parentId);

        if (!$parent) {
            throw new ParentNotFoundException('Parent doesnt exist');
        }

        $parent->childrenByDepth(1)->increment($this->getColumnTreeOrder());

        if ($this->exists) {
            $children = $this->childrenByDepth()->get();

            foreach($children as $child) {
                $path = str_replace($this->getTreePath(), $parent->getTreePath().$parent->getKey().$this->separator, $child->getTreePath());
                $realPath = str_replace($this->getTreeRealPath(), $parent->getTreeRealPath().$this->separator.$parent->{$this->columnAlias}, $child->getTreeRealPath());

                $child->update([
                            $child->getColumnTreePath() => $path,
                            $child->getColumnTreeRealPath() => $realPath,
                            $child->getColumnTreeDepth() => ($parent->getTreeDepth() + 1 + ($child->getTreeDepth() - $this->getTreeDepth())),
                        ]);
            }
        }

        $path = $parent->getTreePath().$parent->getKey().$this->separator;
        $realPath = $parent->getTreeRealPath().$this->separator.$this->{$this->columnAlias};

        $this->fill([
            $this->getColumnTreePath() => $path,
            $this->getColumnTreeRealPath() => $realPath,
            $this->getColumnTreePid() => $parent->getKey(),
            $this->getColumnTreeOrder() => 0,
            $this->getColumnTreeDepth() => ($parent->getTreeDepth() + 1)
        ]);

        $this->save();

        return $this;
    }

    /**
     * Make node last chield of parent.
     *
     * @param  int $parentId
     * @return \Illuminate\Database\Eloquent\Model
     *
     * @throws \Langaner\MaterializedPath\Exceptions\MoveException
     * @throws \Langaner\MaterializedPath\Exceptions\ParentNotFoundException
     */
    public function makeLastChildOf($parentId)
    {
        if ($this->exists and $this->isAncestor($parentId)) {
            throw new MoveException('Cant move Ancestor to Descendant');
        }

        $parent = $this->newQuery()->find($parentId);

        if (!$parent) {
            throw new ParentNotFoundException('Parent doesnt exist');
        }

        if ($this->exists) {
            $children = $this->childrenByDepth()->get();

            foreach($children as $child) {
                $path = str_replace($this->getTreePath(), $parent->getTreePath().$parent->getKey().$this->separator, $child->getTreePath());
                $realPath = str_replace($this->getTreeRealPath(), $parent->getTreeRealPath().$this->separator.$parent->{$this->columnAlias}, $child->getTreeRealPath());

                $child->update([
                            $child->getColumnTreePath() => $path,
                            $child->getColumnTreeRealPath() => $realPath,
                            $child->getColumnTreeDepth() => ($parent->getTreeDepth() + 1 + ($child->getTreeDepth() - $this->getTreeDepth())),
                        ]);
            }
        }

        $path = $parent->getTreePath().$parent->getKey().$this->separator;
        $realPath = $parent->getTreeRealPath().$this->separator.$this->{$this->columnAlias};

        $this->fill([
            $this->getColumnTreePath() => $path,
            $this->getColumnTreeRealPath() => $realPath,
            $this->getColumnTreePid() => $parent->getKey(),
            $this->getColumnTreeOrder() => ($parent->childrenByDepth(1)->max($parent->getColumnTreeOrder()) + 1),
            $this->getColumnTreeDepth() => ($parent->getTreeDepth() + 1)
        ]);

        $this->save();

        return $this;
    }

    /**
     * Update node.
     *
     * @param  int $node
     * @return \Illuminate\Database\Eloquent\Model
     * 
     * @throws \Langaner\MaterializedPath\Exceptions\NodeNotFoundException
     */
    public function updateNode(Model $node)
    {
        if (!$node) {
            throw new NodeNotFoundException('Node doesnt exist');
        }

        $parent = $this->newQuery()->find($node->parent_id);

        if ($parent === null) {
            $parent = $this->createRootObject();
        }

        $node->sibling()->where($this->getColumnTreeOrder(), '>=', $node->getTreeOrder())->where('id', '!=', $node->id)->increment($this->getColumnTreeOrder());

        $children = $node->childrenByDepth()->get();

        if ($parent->getTreeRealPath() === '') {
            $path = $this->separator.'0'.$this->separator;
            $realPath = $node->{$this->columnAlias};
        } else {
            $path = str_replace($node->getTreePath(), $parent->getTreePath().$parent->id.$this->separator, $node->getTreePath());
            $realPath = str_replace($node->getTreeRealPath(), $parent->getTreeRealPath().$this->separator.$node->{$this->columnAlias}, $node->getTreeRealPath());
        }

        $node->update([
            $node->getColumnTreePath() => str_replace('//', '/', $path),
            $node->getColumnTreeRealPath() => $realPath,
            $node->getColumnTreePid() => $parent->getKey(),
            $node->getColumnTreeOrder() => $node->getTreeOrder(),
            $node->getColumnTreeDepth() => (count($this->getExplodedPath($path)) - 1)
        ]);
        
        foreach($children as $child) {
            $path = str_replace($child->getTreePath(), $node->getTreePath().$node->getKey().$this->separator, $child->getTreePath());
            $realPath = str_replace($child->getTreeRealPath(), $node->getTreeRealPath().$this->separator.$child->{$this->columnAlias}, $child->getTreeRealPath());
            
            $child->update([
                $child->getColumnTreePath() => $path,
                $child->getColumnTreeRealPath() => $realPath,
            ]);
        }

        return $this;
    }

    /**
     *
     * @param  Model $sibling
     * @param  string $op
     * @return \Illuminate\Database\Eloquent\Model
     *
     * @throws \Langaner\MaterializedPath\Exceptions\MoveException
     * @throws \Langaner\MaterializedPath\Exceptions\SiblingNotFoundException
     */
    protected function processSiblingOf(Model $sibling, $op)
    {
        if ($this->exists and $this->isAncestor($sibling)) {
            throw new MoveException('Cant move Ancestor to Descendant');
        }

        if (!$sibling) {
            throw new SiblingNotFoundException('Sibling doesnt exist');
        }

        $sibling->sibling()->where($this->getColumnTreeOrder(), $op, $sibling->getTreeOrder())->increment($this->getColumnTreeOrder());

        if ($this->exists) {
            $children = $this->childrenByDepth()->get();

            foreach($children as $child) {
                $path = str_replace($this->getTreePath(), $sibling->getTreePath(), $child->getTreePath());
                $realPath = $this->buildRealPath($this->getExplodedPath($path));

                $child->update([
                            $child->getColumnTreePath() => $path,
                            $child->getColumnTreeRealPath() => $realPath,
                            $child->getColumnTreeDepth() => ($sibling->getTreeDepth() + ($child->getTreeDepth() - $this->getTreeDepth())),
                        ]);
            }
        }

        $path = $sibling->getTreePath().$sibling->getKey().$this->separator;
        $realPath = $sibling->getTreeRealPath().$this->separator.$this->{$this->columnAlias};
        
        $this->fill([
            $this->getColumnTreePath() => $path,
            $this->getColumnTreeRealPath() => $realPath,
            $this->getColumnTreePid() => $sibling->id,
            $this->getColumnTreeOrder() => $sibling->getTreeOrder() + ($op == '>' ? 1 : 0),
            $this->getColumnTreeDepth() => (count($this->getExplodedPath($path)) - 1),
        ]);

        $this->save();

        return $this;
    }

    /**
     * Previous sibling.
     *
     * @param  Model $sibling
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function makePreviousSiblingOf(Model $sibling)
    {
        return $this->processSiblingOf($sibling, '>=');
    }

    /**
     * Next sibling.
     *
     * @param  Model $sibling
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function makeNextSiblingOf(Model $sibling)
    {
        return $this->processSiblingOf($sibling, '>');
    }

    /**
     * Get parent.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function parent()
    {
        return $this->newQuery()->where($this->getKeyName(), '=', $this->getTreePid());
    }

    /**
     * Get sibling.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function sibling()
    {
        return $this->newQuery()->where($this->columnTreePid, '=', $this->getTreePid());
    }

    /**
     * Get childrens by death.
     *
     * @param  int $depth
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function childrenByDepth($depth = 0)
    {
        $query = $this->newQuery();
        
        if ($depth == 1) {
            if ((int)$this->getKey() == 0) {
                $query->whereNull($this->columnTreePid);
            } else {
                $query->where($this->columnTreePid, '=', (int)$this->getKey());
            }
        } else {
            $query->where($this->columnTreePath, 'like', $this->getTreePath().(int)$this->getKey().$this->separator.'%');
        }

        if ($depth) {
            $query->where($this->columnTreeDepth, '<=', $this->getTreeDepth() + $depth);
        }

        $config = $this->getConfig();

        if ($config->get('materialized_path.with_translations') === true && method_exists($query->getModel(), 'translations')) {
            $query = $query->with('translations');
        }

        return $query;
    }

    /**
     * Get parents by death.
     *
     * @param  int $depth
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function parentByDepth($depth = 0)
    {
        $query = $this->newQuery();

        $ids = $this->getExplodedPath($this->getTreePath());

        if (count($ids) > 0) {
            $query->whereIn('id', $ids);
        }

        if ($depth) {
            $query->where($this->columnTreeDepth, '>=', $this->getTreeDepth() - $depth);
        }

        $config = $this->getConfig();
        
        if ($config->get('materialized_path.with_translations') === true && method_exists($query->getModel(), 'translations')) {
            $query = $query->with('translations');
        }

        return $query;
    }

    /**
     * Is descendant.
     *
     * @param  Model  $ancestor
     * @return boolean
     *
     * @throws \Langaner\MaterializedPath\Exceptions\ModelNotFoundException
     */
    public function isDescendant(Model $ancestor)
    {
        if (!$this->exists) {
            throw new ModelNotFoundException('Model doesnt exist');
        }

        $path = $ancestor->getTreePath().$ancestor->getKey().$this->separator;

        return strpos($this->getTreePath(), $path) !== false && $ancestor->getTreePath() !== $this->getTreePath();
    }

    /**
     * Is ancestor.
     *
     * @param  Model  $descendant
     * @return boolean
     * 
     * @throws \Langaner\MaterializedPath\Exceptions\ModelNotFoundException
     */
    public function isAncestor(Model $descendant)
    {
        if (!$this->exists) {
            throw new ModelNotFoundException('Model doesnt exist');
        }

        $path = $this->getTreePath().(int)$this->getKey().$this->separator;

        return strpos($descendant->getTreePath(), $path) !== false && $descendant->getTreePath() !== $this->getTreePath();
    }

    /**
     * Is Leaf.
     *
     * @return boolean
     *
     * @throws \Langaner\MaterializedPath\Exceptions\ModelNotFoundException
     */
    public function isLeaf()
    {
        if (!$this->exists) {
            throw new ModelNotFoundException('Model doesnt exist');
        }

        return !count($this->childrenByDepth(1)->get()->toArray());
    }

    /**
     * Get relative depth.
     *
     * @param Model $object
     * @return boolean
     */
    public function relativeDepth(Model $object)
    {
        return abs($this->getTreeDepth() - $object->getTreeDepth());
    }

    /**
     * Get all root.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function allRoot()
    {
        $instance = with(new static);
        $query = $instance->newQuery()->whereNull($instance->getColumnTreePid());

        $config = $instance->getConfig();

        if ($config->get('materialized_path.with_translations') === true && method_exists($query->getModel(), 'translations')) {
            $query = $query->with('translations');
        }

        return $query;
    }

    /**
     * Get all leaf.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function allLeaf()
    {
        $instance = with(new static);
        $query = $instance->newQuery();
        $query->select($instance->getTable().'.*');
        $query->leftJoin($instance->getTable().' as t_2', function($join) use ($instance) {
            $join->on($instance->getTable().'.'.$instance->getKeyName(), '=', 't_2.'.$instance->getColumnTreePid());
        })
        ->whereNull('t_2.id');

        $config = $instance->getConfig();

        if ($config->get('materialized_path.with_translations') === true && method_exists($query->getModel(), 'translations')) {
            $query = $query->with('translations');
        }

        return $query;
    }

    /**
     * Get exploded path.
     *
     * @param  string $path
     * @return array
     */
    public function getExplodedPath($path = null)
    {
        $path = $path == null ? $this->getTreeRealPath() : $path;
        $path = explode($this->separator, $path);

        return array_filter($path, function($item) {
            return '' !== $item;
        });
    }

    /**
     * Build real path.
     *
     * @param  array  $entitys
     * @return string
     */
    public function buildRealPath(array $entitys)
    {
        $realPath = [];
        $instance = with(new static);
        $query = $instance->newQuery();
        $result = $query->whereIn('id', $entitys)->get();

        foreach ($result as $entity) {
            $realPath[] = $entity->{$this->columnAlias};
        }

        return implode($this->separator, $realPath);
    }

    /**
     * Get parent id attribute.
     * 
     * @return int
     */
    public function getTreePid()
    {
        return $this->getAttribute($this->columnTreePid);
    }

    /**
     * Get order attribute.
     * 
     * @return int
     */
    public function getTreeOrder()
    {
        return $this->getAttribute($this->columnTreeOrder);
    }

    /**
     * Get path attribute.
     * 
     * @return string
     */
    public function getTreePath()
    {
        return $this->getAttribute($this->columnTreePath);
    }

    /**
     * Get real path attribute.
     * 
     * @return string
     */
    public function getTreeRealPath()
    {
        return $this->getAttribute($this->columnTreeRealPath);
    }

    /**
     * Get level attribute.
     * 
     * @return int
     */
    public function getTreeDepth()
    {
        return $this->getAttribute($this->columnTreeDepth);
    }

    /**
     * Get parent id column.
     * 
     * @return string
     */
    public function getColumnTreePid()
    {
        return $this->columnTreePid;
    }

    /**
     * Get order column.
     * 
     * @return string
     */
    public function getColumnTreeOrder()
    {
        return $this->columnTreeOrder;
    }

    /**
     * Get path column.
     * 
     * @return string
     */
    public function getColumnTreePath()
    {
        return $this->columnTreePath;
    }

    /**
     * Get real path column.
     * 
     * @return string
     */
    public function getColumnTreeRealPath()
    {
        return $this->columnTreeRealPath;
    }

    /**
     * Get level column.
     * 
     * @return string
     */
    public function getColumnTreeDepth()
    {
        return $this->columnTreeDepth;
    }

    /**
     * Set parent id column.
     * 
     * @return string
     */
    public function setColumnTreePid($name)
    {
        $this->columnTreePid = $name;
    }

    /**
     * Set order column.
     * 
     * @return string
     */
    public function setColumnTreeOrder($name)
    {
        $this->columnTreeOrder = $name;
    }

    /**
     * Set path column.
     * 
     * @return string
     */
    public function setColumnTreePath($name)
    {
        $this->columnTreePath = $name;
    }

    /**
     * Set real path column.
     * 
     * @return string
     */
    public function setColumnTreeRealPath($name)
    {
        $this->columnTreeRealPath = $name;
    }

    /**
     * Set level column.
     * 
     * @return string
     */
    public function setColumnTreeDepth($name)
    {
        $this->columnTreeDepth = $name;
    }

    /**
     * Build tree by parent node scope.
     *
     * @param  int $parentId
     * @param  Collection $nodes
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function scopeBuildChidrenTree($query, $parentId = null, $nodes = null)
    {
        $tree = new Collection;
        $parentId = $parentId === null ? $this->id : $parentId;

        if ($nodes === null) {
            $nodes = $this->childrenByDepth()->orderBy($this->getColumnTreeOrder(), 'ASC')->get();
        }

        foreach ($nodes as $node) {
            if($node->parent_id == $parentId) {
                $children = $node->buildChidrenTree($node->id, $nodes);
                $node->children = $children;
                $tree->add($node);
            }
        }

        return $tree;
    }

    /**
     * Build tree scope.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function scopeBuildTree($query)
    {
        $tree = new Collection;
        $roots = static::allRoot();
        $config = $this->getConfig();
        
        if ($roots->count() > 0) {
            if ($config->get('materialized_path.with_translations') == true && method_exists($roots->getModel(), 'translations')) {
                $roots = $roots->with('translations');
            }
            
            foreach ($roots->orderBy($this->getColumnTreeOrder(), 'ASC')->get() as $root) {
                $children = $root->buildChidrenTree($root->id, null);
                $root->children = $children;
                $tree->add($root);
            }
        }

        return $tree;
    }
}
