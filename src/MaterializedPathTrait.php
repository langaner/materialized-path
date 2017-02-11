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
            $this->getColumnTreeOrder() => 0
        ]);

        if ($this->useRealPath()) {
            $root->fill([
                $this->getColumnTreeRealPath() => '',
            ]);
        }

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
            $this->getColumnTreeOrder() => (static::allRoot()->max($this->getColumnTreeOrder()) + 1)
        ]);

        if ($this->useRealPath()) {
            $this->fill([
                $this->getColumnTreeRealPath() => $this->{$this->columnAlias} == '' ? 'root' : $this->{$this->columnAlias},
                $this->getColumnAlias() => $this->{$this->columnAlias} == '' ? 'root' : $this->{$this->columnAlias},
            ]);
        }

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
        $parent = $this->newQuery()->find($parentId);

        if (!$parent) {
            throw new ParentNotFoundException('Parent doesnt exist');
        }

        if ($this->exists and $this->isAncestorOf($parent)) {
            throw new MoveException('Cant move Ancestor to Descendant');
        }

        $parent->childrenByDepth(1)->increment($this->getColumnTreeOrder());

        if ($this->exists) {
            $children = $this->childrenByDepth()->get();

            foreach($children as $child) {
                $path = str_replace($this->getTreePath(), $parent->getTreePath().$parent->getKey().$this->separator, $child->getTreePath());
                $fields = [
                    $child->getColumnTreePath() => $path,
                    $child->getColumnTreeDepth() => ($parent->getTreeDepth() + 1 + ($child->getTreeDepth() - $this->getTreeDepth())),
                ];

                if ($this->useRealPath()) {
                    $realPath = str_replace($this->getTreeRealPath(), $parent->getTreeRealPath().$this->separator.$parent->{$this->columnAlias}, $child->getTreeRealPath());

                    $fields[$child->getColumnTreeRealPath()] = $realPath;
                }

                $child->update($fields);
            }
        }

        $path = $parent->getTreePath().$parent->getKey().$this->separator;
        

        $this->fill([
            $this->getColumnTreePath() => $path,
            $this->getColumnTreePid() => $parent->getKey(),
            $this->getColumnTreeOrder() => 0,
            $this->getColumnTreeDepth() => ($parent->getTreeDepth() + 1)
        ]);

        if ($this->useRealPath()) {
            $realPath = $parent->getTreeRealPath().$this->separator.$this->{$this->columnAlias};

            $this->fill([
                $this->getColumnTreeRealPath() => $realPath,
            ]);
        }

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
        $parent = $this->newQuery()->find($parentId);

        if (!$parent) {
            throw new ParentNotFoundException('Parent doesnt exist');
        }

        if ($this->exists and $this->isAncestorOf($parent)) {
            throw new MoveException('Cant move Ancestor to Descendant');
        }

        if ($this->exists) {
            $children = $this->childrenByDepth()->get();

            foreach($children as $child) {
                $path = str_replace($this->getTreePath(), $parent->getTreePath().$parent->getKey().$this->separator, $child->getTreePath());
                $fields = [
                    $child->getColumnTreePath() => $path,
                    $child->getColumnTreeDepth() => ($parent->getTreeDepth() + 1 + ($child->getTreeDepth() - $this->getTreeDepth())),
                ];

                if ($this->useRealPath()) {
                    $realPath = str_replace($this->getTreeRealPath(), $parent->getTreeRealPath().$this->separator.$parent->{$this->columnAlias}, $child->getTreeRealPath());

                    $fields[$child->getColumnTreeRealPath()] = $realPath;
                }

                $child->update($fields);
            }
        }

        $path = $parent->getTreePath().$parent->getKey().$this->separator;

        $this->fill([
            $this->getColumnTreePath() => $path,
            $this->getColumnTreePid() => $parent->getKey(),
            $this->getColumnTreeOrder() => ($parent->childrenByDepth(1)->max($parent->getColumnTreeOrder()) + 1),
            $this->getColumnTreeDepth() => ($parent->getTreeDepth() + 1)
        ]);

        if ($this->useRealPath()) {
            $realPath = $parent->getTreeRealPath().$this->separator.$this->{$this->columnAlias};

            $this->fill([
                $this->getColumnTreeRealPath() => $realPath,
            ]);
        }

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

            if ($this->useRealPath()) {
                $realPath = $node->{$this->columnAlias};
            }
        } else {
            $path = str_replace($node->getTreePath(), $parent->getTreePath().$parent->id.$this->separator, $node->getTreePath());

            if ($this->useRealPath()) {
                $realPath = str_replace($node->getTreeRealPath(), $parent->getTreeRealPath().$this->separator.$node->{$this->columnAlias}, $node->getTreeRealPath());
            }
        }

        $fields = [
            $node->getColumnTreePath() => str_replace('//', '/', $path),
            $node->getColumnTreePid() => $parent->getKey(),
            $node->getColumnTreeOrder() => $node->getTreeOrder(),
            $node->getColumnTreeDepth() => (count($this->getExplodedPath($path)) - 1)
        ];

        if ($this->useRealPath()) {
            $fields[$node->getColumnTreeRealPath()] = $realPath;
        }

        $node->update($fields);
        
        foreach($children as $child) {
            $path = str_replace($child->getTreePath(), $node->getTreePath().$node->getKey().$this->separator, $child->getTreePath());
            $fields = [
                $child->getColumnTreePath() => $path
            ];
            
            if ($this->useRealPath()) {
                $realPath = str_replace($child->getTreeRealPath(), $node->getTreeRealPath().$this->separator.$child->{$this->columnAlias}, $child->getTreeRealPath());
                $fields[$node->getColumnTreeRealPath()] = $realPath;
            }
            
            $child->update($fields);
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
        if ($this->exists and $this->isAncestorOf($sibling)) {
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
                $fields = [
                    $child->getColumnTreePath() => $path,
                    $child->getColumnTreeDepth() => ($sibling->getTreeDepth() + ($child->getTreeDepth() - $this->getTreeDepth())),
                ];
                
                if ($this->useRealPath()) {
                    $realPath = $this->buildRealPath($this->getExplodedPath($path));

                    $fields[$node->getColumnTreeRealPath()] = $realPath;
                }

                $child->update($fields);
            }
        }

        $path = $sibling->getTreePath().$sibling->getKey().$this->separator;
        
        $this->fill([
            $this->getColumnTreePath() => $path,
            $this->getColumnTreePid() => $sibling->id,
            $this->getColumnTreeOrder() => $sibling->getTreeOrder() + ($op == '>' ? 1 : 0),
            $this->getColumnTreeDepth() => (count($this->getExplodedPath($path)) - 1),
        ]);

        if ($this->useRealPath()) {
            $realPath = $sibling->getTreeRealPath().$this->separator.$this->{$this->columnAlias};

            $this->fill([
                $this->getColumnTreeRealPath() => $realPath,
            ]);
        }

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
    public function isDescendantOf(Model $ancestor)
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
    public function isAncestorOf(Model $descendant)
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
            $realPath[] = ($this->useRealPath() ? $entity->{$this->columnAlias} : $entity->id);
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
     * Get alias column.
     * 
     * @return string
     */
    public function getColumnAlias()
    {
        return $this->columnAlias;
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
     * Set alias column.
     * 
     * @return string
     */
    public function setColumnAlias($name)
    {
        $this->columnAlias = $name;
    }

    /**
     * User real path column(alias and real_path)
     * 
     * @return boolean
     */
    public function useRealPath()
    {
        return true;
    }

    /**
     * Build tree by parent node scope.
     *
     * @param  int $parentId
     * @param  Collection $nodes
     * @param  int $level
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function scopeBuildChidrenTree($query, $parentId = null, $nodes = null, $level = null)
    {
        $tree = new Collection;
        $parentId = $parentId === null ? $this->id : $parentId;

        if ($nodes === null) {
            $nodes = $this->childrenByDepth($level)->orderBy($this->getColumnTreeOrder(), 'ASC')->get();
        }

        foreach ($nodes as $node) {
            if($node->parent_id == $parentId) {
                $children = $node->buildChidrenTree($node->id, $nodes, $level);
                $node->children = $children;
                $tree->add($node);
            }
        }

        return $tree;
    }

    /**
     * Build tree scope.
     *
     * @param int $parentId
     * @param  int $level
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function scopeBuildTree($query, $parentId = null, $level = null)
    {
        $tree = new Collection;

        if (is_null($parentId)) {
            $roots = static::allRoot();
        } else {
            $config = $this->getConfig();

            $roots = $this->newQuery();

            if ($config->get('materialized_path.with_translations') === true && method_exists($roots->getModel(), 'translations')) {
                $query = $roots->with('translations');
            }

            $roots = $roots->where('id', $parentId);
        }
        
        if ($roots->count() > 0) {
            foreach ($roots->orderBy($this->getColumnTreeOrder(), 'ASC')->get() as $root) {
                $children = $root->buildChidrenTree($root->id, null, $level);
                $root->children = $children;
                $tree->add($root);
            }
        }

        return $tree;
    }
}
