<?php

namespace AhgCore\Traits;

trait HasNestedSet
{
    /**
     * Get all descendants using nested set lft/rgt.
     */
    public function scopeDescendantsOf($query, int $lft, int $rgt)
    {
        return $query->where('lft', '>', $lft)->where('rgt', '<', $rgt);
    }

    /**
     * Get all ancestors using nested set lft/rgt.
     */
    public function scopeAncestorsOf($query, int $lft, int $rgt)
    {
        return $query->where('lft', '<', $lft)->where('rgt', '>', $rgt);
    }

    /**
     * Check if this node is a leaf (no children).
     */
    public function isLeaf(): bool
    {
        return ($this->rgt - $this->lft) === 1;
    }

    /**
     * Get descendant count.
     */
    public function getDescendantCount(): int
    {
        return (int) (($this->rgt - $this->lft - 1) / 2);
    }
}
