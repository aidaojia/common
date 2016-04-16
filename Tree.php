<?php

declare(strict_types=1);

namespace Aidaojia\Common;

class Tree
{
    private $basicData = [],
            $formatData = [],
            $formatTree = [];

    public function __construct(array $data)
    {
        $this->basicData = $data;

        return $this;
    }

    public function order($prefix = false)
    {
        $parentIds = [];
        foreach ($this->basicData as $v) {
            !in_array($v['parent_id'], $parentIds) and $parentIds[] = $v['parent_id'];
        }

        foreach ($this->basicData as $k => $v) {
            if (in_array($v['parent_id'], $parentIds)) {
                $this->formatData[$v['parent_id']][] = $v;
            }
        }
        unset($parentIds);

        if (empty($this->formatData)) {
            return [];
        }

        $this->_makeOrder(0, 0, $prefix);

        unset($this->formatData);

        return $this->formatTree;
    }

    // 递归生成树
    private function _makeOrder($pid = 0, $level = 0, $prefix = false)
    {
        if (!isset($this->formatData[$pid])) {
            return;
        }

        foreach ($this->formatData[$pid] as &$v) {
            if ($prefix) {
                $level > 0 && $v['name'] = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', $level).'└&nbsp;'.$v['name'];
            }

            $v['level'] = $level;
            $v['child_num'] = isset($this->formatData[$v['id']]) ? count($this->formatData[$v['id']]) : 0;

            $this->formatTree[$v['id']] = $v;
            $this->_makeOrder($v['id'], $level + 1, $prefix);
        }
        unset($v);
    }

    // 多维数组
    public function multiArray($pid = 0)
    {
        $treeData = [];
        foreach ($this->basicData as $k => $v) {
            if ($pid == $v['parent_id']) {
                $v['child'] = $this->multiArray($v['id']);
                $treeData[] = $v;
            }
        }

        return $treeData;
    }
}
